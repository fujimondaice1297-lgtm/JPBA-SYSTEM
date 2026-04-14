<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TournamentDrawReminderService
{
    public function defaultTemplate(Tournament $tournament, string $pendingType = 'either'): array
    {
        $subject = match ($pendingType) {
            'shift' => '【JPBA】シフト抽選のお願い：' . $tournament->name,
            'lane' => '【JPBA】レーン抽選のお願い：' . $tournament->name,
            default => '【JPBA】抽選手続きのご案内：' . $tournament->name,
        };

        $body = "【JPBA】抽選手続きのご案内\n\n"
            . "{name} 様\n\n"
            . "{tournament} について、現在 {pending_items} が未完了です。\n"
            . "{pending_deadline} までに会員ページからお手続きをお願いします。\n"
            . "{entry_url}\n\n"
            . "希望シフト：{preferred_shift}\n\n"
            . "{office_batch_notice}\n\n"
            . "※ 本メールはJPBAシステムから送信されています。";

        return [
            'subject' => $subject,
            'body' => $body,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    public function pendingEntriesQuery(Tournament $tournament, string $pendingType): Builder
    {
        $query = TournamentEntry::query()
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry');

        $useShift = (bool) ($tournament->use_shift_draw ?? false);
        $useLane = (bool) ($tournament->use_lane_draw ?? false);

        if ($pendingType === 'shift') {
            if (!$useShift) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereNull('shift');
        }

        if ($pendingType === 'lane') {
            if (!$useLane) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereNull('lane');
        }

        return $query->where(function (Builder $builder) use ($useShift, $useLane) {
            $applied = false;

            if ($useShift) {
                $builder->whereNull('shift');
                $applied = true;
            }

            if ($useLane) {
                if ($applied) {
                    $builder->orWhereNull('lane');
                } else {
                    $builder->whereNull('lane');
                    $applied = true;
                }
            }

            if (!$applied) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    public function personalizeBody(
        string $body,
        TournamentEntry $entry,
        Tournament $tournament,
        string $pendingType = 'either'
    ): string {
        $pendingItems = $this->pendingItemsForEntry($entry, $tournament, $pendingType);
        $deadline = $this->deadlineForPendingType($tournament, $pendingType);

        return str_replace(
            [
                '{name}',
                '{tournament}',
                '{pending_items}',
                '{preferred_shift}',
                '{entry_url}',
                '{pending_deadline}',
                '{office_batch_notice}',
            ],
            [
                (string) ($entry->bowler?->name_kanji ?? '選手'),
                (string) $tournament->name,
                implode(' / ', $pendingItems ?: [$this->pendingLabel($pendingType)]),
                (string) ($entry->preferred_shift_code ?: '指定なし'),
                url('/entry/select'),
                $deadline ? $deadline->format('Y年n月j日 H:i') : '大会設定の締切',
                '期日までにご対応がない場合は、事務局側で一斉抽選を行います。',
            ],
            $body
        );
    }

    public function sendManual(
        Tournament $tournament,
        string $pendingType,
        string $subject,
        string $body,
        ?string $fromAddress = null,
        ?string $fromName = null,
        ?string $dryRunTo = null
    ): array {
        $entries = $this->pendingEntriesQuery($tournament, $pendingType)
            ->with('bowler')
            ->orderBy('id')
            ->get()
            ->filter(fn (TournamentEntry $entry) => filled($entry->bowler?->email))
            ->values();

        $fromAddress = $fromAddress ?: config('mail.from.address');
        $fromName = $fromName ?: config('mail.from.name');

        if ($entries->isEmpty()) {
            return [
                'entry_count' => 0,
                'sent' => 0,
                'failed' => 0,
                'dry_run' => false,
            ];
        }

        if ($dryRunTo) {
            $entry = $entries->first();
            $mailBody = $this->personalizeBody($body, $entry, $tournament, $pendingType);

            Mail::raw($mailBody, function ($message) use ($dryRunTo, $fromAddress, $fromName, $subject) {
                $message->to($dryRunTo)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
            });

            return [
                'entry_count' => $entries->count(),
                'sent' => 1,
                'failed' => 0,
                'dry_run' => true,
            ];
        }

        $sent = 0;
        $failed = 0;
        $today = now()->toDateString();

        foreach ($entries as $entry) {
            try {
                $mailBody = $this->personalizeBody($body, $entry, $tournament, $pendingType);

                Mail::raw($mailBody, function ($message) use ($entry, $fromAddress, $fromName, $subject) {
                    $message->to($entry->bowler->email)
                        ->from($fromAddress, $fromName)
                        ->subject($subject);
                });

                $this->insertLog([
                    'tournament_id' => $tournament->id,
                    'tournament_entry_id' => $entry->id,
                    'reminder_kind' => 'manual',
                    'pending_type' => $pendingType,
                    'scheduled_for_date' => $today,
                    'dispatch_key' => null,
                    'recipient_email' => $entry->bowler->email,
                    'subject' => $subject,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                $sent++;
            } catch (\Throwable $e) {
                $this->insertLog([
                    'tournament_id' => $tournament->id,
                    'tournament_entry_id' => $entry->id,
                    'reminder_kind' => 'manual',
                    'pending_type' => $pendingType,
                    'scheduled_for_date' => $today,
                    'dispatch_key' => null,
                    'recipient_email' => (string) ($entry->bowler?->email ?? ''),
                    'subject' => $subject,
                    'status' => 'failed',
                    'sent_at' => null,
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                ]);

                $failed++;
            }
        }

        return [
            'entry_count' => $entries->count(),
            'sent' => $sent,
            'failed' => $failed,
            'dry_run' => false,
        ];
    }

    public function sendAutomatic(?Carbon $baseDate = null, ?int $tournamentId = null, bool $dryRun = false): array
    {
        $targetDate = ($baseDate ?: now())->copy()->startOfDay();

        $query = Tournament::query()->where(function (Builder $builder) {
            $builder
                ->where('shift_auto_draw_reminder_enabled', true)
                ->orWhere('lane_auto_draw_reminder_enabled', true);
        });

        if ($tournamentId) {
            $query->where('id', $tournamentId);
        }

        $tournaments = $query->orderBy('id')->get();

        $summary = [
            'target_date' => $targetDate->toDateString(),
            'checked_tournaments' => $tournaments->count(),
            'due_tournaments' => 0,
            'target_entries' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped_logged' => 0,
            'skipped_not_due' => 0,
            'skipped_no_targets' => 0,
            'dry_run_candidates' => 0,
        ];

        $details = [];

        foreach ($tournaments as $tournament) {
            $dueTypes = $this->duePendingTypes($tournament, $targetDate);

            if (empty($dueTypes)) {
                $summary['skipped_not_due']++;
                continue;
            }

            $summary['due_tournaments']++;

            foreach ($dueTypes as $pendingType) {
                $entries = $this->pendingEntriesQuery($tournament, $pendingType)
                    ->with('bowler')
                    ->orderBy('id')
                    ->get()
                    ->filter(fn (TournamentEntry $entry) => filled($entry->bowler?->email))
                    ->values();

                if ($entries->isEmpty()) {
                    $summary['skipped_no_targets']++;
                    $details[] = '[大会ID:' . $tournament->id . '][' . $pendingType . '] 送信対象なし';
                    continue;
                }

                $summary['target_entries'] += $entries->count();
                $template = $this->defaultTemplate($tournament, $pendingType);

                foreach ($entries as $entry) {
                    $dispatchKey = $this->makeAutoDispatchKey($tournament, $entry, $pendingType, $targetDate);

                    if (DB::table('tournament_draw_reminder_logs')->where('dispatch_key', $dispatchKey)->exists()) {
                        $summary['skipped_logged']++;
                        continue;
                    }

                    if ($dryRun) {
                        $summary['dry_run_candidates']++;
                        $details[] = '[DRY-RUN][大会ID:' . $tournament->id . '][' . $pendingType . '][entry:' . $entry->id . '] ' . ($entry->bowler?->email ?? '-');
                        continue;
                    }

                    $mailBody = $this->personalizeBody($template['body'], $entry, $tournament, $pendingType);

                    try {
                        Mail::raw($mailBody, function ($message) use ($entry, $template) {
                            $message->to($entry->bowler->email)
                                ->from($template['from_address'], $template['from_name'])
                                ->subject($template['subject']);
                        });

                        $this->insertLog([
                            'tournament_id' => $tournament->id,
                            'tournament_entry_id' => $entry->id,
                            'reminder_kind' => 'auto',
                            'pending_type' => $pendingType,
                            'scheduled_for_date' => $targetDate->toDateString(),
                            'dispatch_key' => $dispatchKey,
                            'recipient_email' => $entry->bowler->email,
                            'subject' => $template['subject'],
                            'status' => 'sent',
                            'sent_at' => now(),
                            'error_message' => null,
                        ]);

                        $summary['sent']++;
                    } catch (\Throwable $e) {
                        $this->insertLog([
                            'tournament_id' => $tournament->id,
                            'tournament_entry_id' => $entry->id,
                            'reminder_kind' => 'auto',
                            'pending_type' => $pendingType,
                            'scheduled_for_date' => $targetDate->toDateString(),
                            'dispatch_key' => $dispatchKey,
                            'recipient_email' => (string) ($entry->bowler?->email ?? ''),
                            'subject' => $template['subject'],
                            'status' => 'failed',
                            'sent_at' => null,
                            'error_message' => mb_substr($e->getMessage(), 0, 2000),
                        ]);

                        $summary['failed']++;
                        $details[] = '[FAILED][大会ID:' . $tournament->id . '][' . $pendingType . '][entry:' . $entry->id . '] ' . $e->getMessage();
                    }
                }
            }
        }

        return [
            'summary' => $summary,
            'details' => $details,
        ];
    }

    private function duePendingTypes(Tournament $tournament, Carbon $targetDate): array
    {
        $due = [];

        if (
            (bool) ($tournament->shift_auto_draw_reminder_enabled ?? false) &&
            (bool) ($tournament->use_shift_draw ?? false) &&
            !empty($tournament->shift_auto_draw_reminder_send_on)
        ) {
            $sendOn = Carbon::parse($tournament->shift_auto_draw_reminder_send_on)->startOfDay();

            if ($sendOn->isSameDay($targetDate)) {
                $due[] = 'shift';
            }
        }

        if (
            (bool) ($tournament->lane_auto_draw_reminder_enabled ?? false) &&
            (bool) ($tournament->use_lane_draw ?? false) &&
            !empty($tournament->lane_auto_draw_reminder_send_on)
        ) {
            $sendOn = Carbon::parse($tournament->lane_auto_draw_reminder_send_on)->startOfDay();

            if ($sendOn->isSameDay($targetDate)) {
                $due[] = 'lane';
            }
        }

        return $due;
    }

    private function deadlineForPendingType(Tournament $tournament, string $pendingType): ?Carbon
    {
        return match ($pendingType) {
            'shift' => ((bool) ($tournament->use_shift_draw ?? false) && $tournament->shift_draw_close_at)
                ? Carbon::parse($tournament->shift_draw_close_at)
                : null,
            'lane' => ((bool) ($tournament->use_lane_draw ?? false) && $tournament->lane_draw_close_at)
                ? Carbon::parse($tournament->lane_draw_close_at)
                : null,
            default => null,
        };
    }

    private function pendingItemsForEntry(TournamentEntry $entry, Tournament $tournament, string $pendingType): array
    {
        if ($pendingType === 'shift') {
            return ['シフト抽選'];
        }

        if ($pendingType === 'lane') {
            return ['レーン抽選'];
        }

        $pendingItems = [];

        if ((bool) $tournament->use_shift_draw && blank($entry->shift)) {
            $pendingItems[] = 'シフト抽選';
        }

        if ((bool) $tournament->use_lane_draw && blank($entry->lane)) {
            $pendingItems[] = 'レーン抽選';
        }

        return $pendingItems;
    }

    private function pendingLabel(string $pendingType): string
    {
        return match ($pendingType) {
            'shift' => 'シフト抽選',
            'lane' => 'レーン抽選',
            default => '抽選手続き',
        };
    }

    private function makeAutoDispatchKey(Tournament $tournament, TournamentEntry $entry, string $pendingType, Carbon $sendDate): string
    {
        return implode(':', [
            'auto',
            (string) $tournament->id,
            (string) $entry->id,
            $pendingType,
            $sendDate->toDateString(),
        ]);
    }

    private function insertLog(array $attributes): void
    {
        DB::table('tournament_draw_reminder_logs')->insert([
            'tournament_id' => $attributes['tournament_id'],
            'tournament_entry_id' => $attributes['tournament_entry_id'],
            'reminder_kind' => $attributes['reminder_kind'],
            'pending_type' => $attributes['pending_type'],
            'scheduled_for_date' => $attributes['scheduled_for_date'],
            'dispatch_key' => $attributes['dispatch_key'],
            'recipient_email' => $attributes['recipient_email'],
            'subject' => $attributes['subject'],
            'status' => $attributes['status'],
            'sent_at' => $attributes['sent_at'],
            'error_message' => $attributes['error_message'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
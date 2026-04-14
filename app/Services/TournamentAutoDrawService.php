<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TournamentAutoDrawService
{
    public function runDueAutoDraws(?Carbon $baseTime = null, ?int $tournamentId = null, bool $dryRun = false): array
    {
        $targetTime = ($baseTime ?: now())->copy();

        $query = Tournament::query()->where(function (Builder $builder) use ($targetTime) {
            $builder
                ->where(function (Builder $q) use ($targetTime) {
                    $q->where('use_shift_draw', true)
                        ->whereNotNull('shift_draw_close_at')
                        ->where('shift_draw_close_at', '<=', $targetTime);
                })
                ->orWhere(function (Builder $q) use ($targetTime) {
                    $q->where('use_lane_draw', true)
                        ->whereNotNull('lane_draw_close_at')
                        ->where('lane_draw_close_at', '<=', $targetTime);
                });
        });

        if ($tournamentId) {
            $query->where('id', $tournamentId);
        }

        $tournaments = $query->orderBy('id')->get();

        $summary = [
            'target_datetime' => $targetTime->format('Y-m-d H:i:s'),
            'checked_tournaments' => $tournaments->count(),
            'due_targets' => 0,
            'target_entries' => 0,
            'success' => 0,
            'failed' => 0,
            'dry_run_candidates' => 0,
            'skipped_no_targets' => 0,
        ];

        $details = [];

        foreach ($tournaments as $tournament) {
            foreach (['shift', 'lane'] as $targetType) {
                if (!$this->isDue($tournament, $targetType, $targetTime)) {
                    continue;
                }

                $entries = $this->pendingEntriesQuery($tournament, $targetType)
                    ->with('bowler')
                    ->orderBy('id')
                    ->get();

                if ($entries->isEmpty()) {
                    $summary['skipped_no_targets']++;
                    $details[] = '[大会ID:' . $tournament->id . '][' . $targetType . '] 対象の未抽選者なし';
                    continue;
                }

                $summary['due_targets']++;
                $summary['target_entries'] += $entries->count();

                if ($dryRun) {
                    $summary['dry_run_candidates'] += $entries->count();

                    foreach ($entries as $entry) {
                        $details[] = '[DRY-RUN][大会ID:' . $tournament->id . '][' . $targetType . '][entry:' . $entry->id . '] '
                            . ($entry->bowler?->license_no ?? '-') . ' '
                            . ($entry->bowler?->name_kanji ?? '-');
                    }

                    continue;
                }

                $targetSuccess = 0;
                $targetFailed = 0;
                $errors = [];

                foreach ($entries as $entry) {
                    $result = $targetType === 'shift'
                        ? $this->performShiftDraw($entry)
                        : $this->performLaneDraw($entry);

                    if ($result['ok']) {
                        $targetSuccess++;
                        $summary['success']++;
                    } else {
                        $targetFailed++;
                        $summary['failed']++;

                        $errors[] = [
                            'entry_id' => $entry->id,
                            'license_no' => $entry->bowler?->license_no,
                            'name_kanji' => $entry->bowler?->name_kanji,
                            'message' => $result['msg'],
                        ];

                        $details[] = '[FAILED][大会ID:' . $tournament->id . '][' . $targetType . '][entry:' . $entry->id . '] '
                            . ($entry->bowler?->license_no ?? '-') . ' '
                            . ($entry->bowler?->name_kanji ?? '-') . ' / '
                            . $result['msg'];
                    }
                }

                $this->insertLog([
                    'tournament_id' => $tournament->id,
                    'target_type' => $targetType,
                    'deadline_at' => $this->deadlineForTarget($tournament, $targetType)?->format('Y-m-d H:i:s'),
                    'executed_at' => now()->format('Y-m-d H:i:s'),
                    'total_pending' => $entries->count(),
                    'success_count' => $targetSuccess,
                    'failed_count' => $targetFailed,
                    'details_json' => [
                        'tournament_name' => $tournament->name,
                        'target_type' => $targetType,
                        'errors' => $errors,
                    ],
                ]);
            }
        }

        return [
            'summary' => $summary,
            'details' => $details,
        ];
    }

    public function pendingEntriesQuery(Tournament $tournament, string $targetType): Builder
    {
        $query = TournamentEntry::query()
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry');

        if ($targetType === 'shift') {
            return $query->whereNull('shift');
        }

        return $query->whereNull('lane');
    }

    private function isDue(Tournament $tournament, string $targetType, Carbon $targetTime): bool
    {
        return match ($targetType) {
            'shift' => (bool) ($tournament->use_shift_draw ?? false)
                && !empty($tournament->shift_draw_close_at)
                && Carbon::parse($tournament->shift_draw_close_at)->lte($targetTime),
            'lane' => (bool) ($tournament->use_lane_draw ?? false)
                && !empty($tournament->lane_draw_close_at)
                && Carbon::parse($tournament->lane_draw_close_at)->lte($targetTime),
            default => false,
        };
    }

    private function deadlineForTarget(Tournament $tournament, string $targetType): ?Carbon
    {
        return match ($targetType) {
            'shift' => !empty($tournament->shift_draw_close_at)
                ? Carbon::parse($tournament->shift_draw_close_at)
                : null,
            'lane' => !empty($tournament->lane_draw_close_at)
                ? Carbon::parse($tournament->lane_draw_close_at)
                : null,
            default => null,
        };
    }

    private function performShiftDraw(TournamentEntry $entry): array
    {
        $tournament = $entry->tournament()->first();

        if (!$tournament?->use_shift_draw) {
            return ['ok' => false, 'msg' => 'この大会はシフト抽選を行いません。'];
        }

        if ($entry->shift) {
            return ['ok' => false, 'msg' => 'すでにシフトが確定しています。'];
        }

        $codes = $this->shiftCodeCollection((string) ($tournament->shift_codes ?? ''));
        if ($codes->isEmpty()) {
            return ['ok' => false, 'msg' => 'シフト候補が未設定です。'];
        }

        $counts = DB::table('tournament_entries')
            ->select('shift', DB::raw('count(*) as c'))
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry')
            ->whereIn('shift', $codes->all())
            ->groupBy('shift')
            ->pluck('c', 'shift');

        $min = null;
        $candidates = [];

        foreach ($codes as $code) {
            $value = (int) ($counts[$code] ?? 0);

            if ($min === null || $value < $min) {
                $min = $value;
                $candidates = [$code];
            } elseif ($value === $min) {
                $candidates[] = $code;
            }
        }

        $preferred = trim((string) ($entry->preferred_shift_code ?? ''));
        if ($preferred !== '' && in_array($preferred, $candidates, true)) {
            $chosen = $preferred;
        } else {
            $chosen = $candidates[array_rand($candidates)];
        }

        $entry->update([
            'shift' => $chosen,
            'shift_drawn' => true,
        ]);

        return ['ok' => true, 'msg' => 'シフト「' . $chosen . '」が確定しました。'];
    }

    private function performLaneDraw(TournamentEntry $entry): array
    {
        $tournament = $entry->tournament()->first();

        if (!$tournament?->use_lane_draw) {
            return ['ok' => false, 'msg' => 'この大会はレーン抽選を行いません。'];
        }

        if ($tournament->use_shift_draw && !$entry->shift) {
            return ['ok' => false, 'msg' => '先にシフト抽選を完了してください。'];
        }

        if ($entry->lane) {
            return ['ok' => false, 'msg' => 'すでにレーンが確定しています。'];
        }

        if (!$tournament->lane_from || !$tournament->lane_to || $tournament->lane_from > $tournament->lane_to) {
            return ['ok' => false, 'msg' => '大会のレーン範囲が未設定です。'];
        }

        $mode = (string) ($tournament->lane_assignment_mode ?? 'single_lane');
        $from = (int) $tournament->lane_from;
        $to = (int) $tournament->lane_to;

        $usedQuery = DB::table('tournament_entries')
            ->select('lane', DB::raw('count(*) as c'))
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry')
            ->whereNotNull('lane');

        if ($tournament->use_shift_draw) {
            $usedQuery->where('shift', $entry->shift);
        }

        $usedCounts = $usedQuery
            ->groupBy('lane')
            ->pluck('c', 'lane');

        $candidateRows = [];

        for ($lane = $from; $lane <= $to; $lane++) {
            $used = (int) ($usedCounts[$lane] ?? 0);
            $capacity = 1;

            if ($mode === 'box') {
                $odd = (int) ($tournament->odd_lane_player_count ?? 0);
                $even = (int) ($tournament->even_lane_player_count ?? 0);
                $box = (int) ($tournament->box_player_count ?? 0);

                if ($odd < 1 || $even < 1 || ($odd + $even) !== $box) {
                    return ['ok' => false, 'msg' => 'BOX運用設定が不正です。'];
                }

                $capacity = ($lane % 2 === 1) ? $odd : $even;
            }

            if ($used >= $capacity) {
                continue;
            }

            $fillRatio = $capacity > 0 ? ($used / $capacity) : 999;

            $candidateRows[] = [
                'lane' => $lane,
                'used' => $used,
                'capacity' => $capacity,
                'fill_ratio' => $fillRatio,
            ];
        }

        if (empty($candidateRows)) {
            return ['ok' => false, 'msg' => '空きレーンがありません。'];
        }

        usort($candidateRows, function (array $a, array $b) {
            if ($a['fill_ratio'] === $b['fill_ratio']) {
                if ($a['used'] === $b['used']) {
                    return $a['lane'] <=> $b['lane'];
                }

                return $a['used'] <=> $b['used'];
            }

            return $a['fill_ratio'] <=> $b['fill_ratio'];
        });

        $bestRatio = $candidateRows[0]['fill_ratio'];
        $bestUsed = $candidateRows[0]['used'];

        $candidates = array_values(array_filter($candidateRows, function (array $row) use ($bestRatio, $bestUsed) {
            return $row['fill_ratio'] === $bestRatio && $row['used'] === $bestUsed;
        }));

        $picked = $candidates[array_rand($candidates)];
        $chosenLane = (int) $picked['lane'];
        $chosenCapacity = (int) $picked['capacity'];

        try {
            DB::transaction(function () use ($tournament, $entry, $chosenLane, $chosenCapacity) {
                $check = DB::table('tournament_entries')
                    ->where('tournament_id', $tournament->id)
                    ->where('status', 'entry')
                    ->where('lane', $chosenLane);

                if ($tournament->use_shift_draw) {
                    $check->where('shift', $entry->shift);
                }

                $currentCount = (int) $check->count();

                if ($currentCount >= $chosenCapacity) {
                    throw new \RuntimeException('選択中にレーンが埋まりました。');
                }

                $entry->update([
                    'lane' => $chosenLane,
                    'lane_drawn' => true,
                ]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }

        return ['ok' => true, 'msg' => 'レーン「' . $chosenLane . '」が確定しました。'];
    }

    private function shiftCodeCollection(string $shiftCodes)
    {
        return collect(explode(',', $shiftCodes))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
    }

    private function insertLog(array $attributes): void
    {
        DB::table('tournament_auto_draw_logs')->insert([
            'tournament_id' => $attributes['tournament_id'],
            'target_type' => $attributes['target_type'],
            'deadline_at' => $attributes['deadline_at'],
            'executed_at' => $attributes['executed_at'],
            'total_pending' => $attributes['total_pending'],
            'success_count' => $attributes['success_count'],
            'failed_count' => $attributes['failed_count'],
            'details_json' => json_encode($attributes['details_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
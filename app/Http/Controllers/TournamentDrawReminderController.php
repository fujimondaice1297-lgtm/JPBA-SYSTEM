<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TournamentDrawReminderController extends Controller
{
    public function create(Request $request, Tournament $tournament)
    {
        $pendingType = (string) $request->input('pending_type', 'either');
        $entries = $this->pendingEntriesQuery($tournament, $pendingType)
            ->with('bowler')
            ->orderBy('id')
            ->get();

        $mailReadyEntries = $entries->filter(function (TournamentEntry $entry) {
            return filled($entry->bowler?->email);
        })->values();

        $defaults = [
            'subject' => '【JPBA】抽選手続きのご案内：' . $tournament->name,
            'body' => "【JPBA】大会抽選手続きのご案内\n\n{name} 様\n\n{tournament} について、現在 {pending_items} が未完了です。\n会員ページからお手続きをお願いします。\n{entry_url}\n\n希望シフト：{preferred_shift}\n\n※ 本メールは自動送信ではなく、管理者からの一括送信です。",
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];

        return view('tournament_entries.reminder_form', compact(
            'tournament',
            'pendingType',
            'entries',
            'mailReadyEntries',
            'defaults'
        ));
    }

    public function store(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'pending_type' => ['required', 'in:shift,lane,either'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'from_address' => ['nullable', 'email'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'dry_run_to' => ['nullable', 'email'],
        ]);

        $entries = $this->pendingEntriesQuery($tournament, $data['pending_type'])
            ->with('bowler')
            ->get()
            ->filter(function (TournamentEntry $entry) {
                return filled($entry->bowler?->email);
            })
            ->values();

        if ($entries->isEmpty()) {
            return back()->withErrors('対象の未抽選選手（メールアドレスあり）がいません。')->withInput();
        }

        $fromAddress = $data['from_address'] ?: config('mail.from.address');
        $fromName = $data['from_name'] ?: config('mail.from.name');

        if (!empty($data['dry_run_to'])) {
            $entry = $entries->first();
            $body = $this->personalizeBody($data['body'], $entry, $tournament);

            Mail::raw($body, function ($message) use ($data, $fromAddress, $fromName) {
                $message->to($data['dry_run_to'])
                    ->from($fromAddress, $fromName)
                    ->subject($data['subject']);
            });

            return back()->with('success', 'テストメールを送信しました。');
        }

        $sent = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            try {
                $body = $this->personalizeBody($data['body'], $entry, $tournament);

                Mail::raw($body, function ($message) use ($entry, $data, $fromAddress, $fromName) {
                    $message->to($entry->bowler->email)
                        ->from($fromAddress, $fromName)
                        ->subject($data['subject']);
                });

                $sent++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return redirect()
            ->route('tournaments.draws.index', $tournament->id)
            ->with('success', "未抽選DM送信完了：成功 {$sent} 件 / 失敗 {$failed} 件");
    }

    private function pendingEntriesQuery(Tournament $tournament, string $pendingType): Builder
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

    private function personalizeBody(string $body, TournamentEntry $entry, Tournament $tournament): string
    {
        $pendingItems = [];
        if ((bool) $tournament->use_shift_draw && blank($entry->shift)) {
            $pendingItems[] = 'シフト抽選';
        }
        if ((bool) $tournament->use_lane_draw && blank($entry->lane)) {
            $pendingItems[] = 'レーン抽選';
        }

        return str_replace(
            ['{name}', '{tournament}', '{pending_items}', '{preferred_shift}', '{entry_url}'],
            [
                (string) ($entry->bowler?->name_kanji ?? '選手'),
                (string) $tournament->name,
                implode(' / ', $pendingItems ?: ['手続き']),
                (string) ($entry->preferred_shift_code ?: '指定なし'),
                route('tournament.entry.select'),
            ],
            $body
        );
    }
}
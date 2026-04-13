<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentEntryController extends Controller
{
    public function select()
    {
        $user = Auth::user();
        $proBowlerId = $user?->pro_bowler_id;
        $bowler = $proBowlerId ? ProBowler::query()->find($proBowlerId) : null;

        $eligibility = $this->resolveEntryEligibility($bowler);

        $tournaments = Tournament::query()
            ->where('entry_start', '<=', now())
            ->where('entry_end', '>=', now())
            ->orderBy('entry_start')
            ->orderBy('id')
            ->get();

        $entries = collect();
        if ($proBowlerId) {
            $entries = TournamentEntry::withCount('balls')
                ->where('pro_bowler_id', $proBowlerId)
                ->get()
                ->keyBy('tournament_id');
        }

        return view('member.entry_select', compact('tournaments', 'entries', 'bowler', 'eligibility'));
    }

    public function storeSelection(Request $request)
    {
        $user = Auth::user();
        $proBowlerId = $user?->pro_bowler_id;
        $bowler = $proBowlerId ? ProBowler::query()->find($proBowlerId) : null;

        $eligibility = $this->resolveEntryEligibility($bowler);
        if (!$eligibility['allowed']) {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', $eligibility['message']);
        }

        $request->validate([
            'entries' => 'array',
            'entries.*' => 'in:entry,no_entry',
            'preferred_shifts' => 'array',
            'preferred_shifts.*' => 'nullable|string|max:20',
        ]);

        $openTournaments = Tournament::query()
            ->where('entry_start', '<=', now())
            ->where('entry_end', '>=', now())
            ->get()
            ->keyBy('id');

        foreach ($request->input('entries', []) as $tournamentId => $choice) {
            $tournamentId = (int) $tournamentId;

            if (!$openTournaments->has($tournamentId)) {
                continue;
            }

            $tournament = $openTournaments->get($tournamentId);
            $preferredShift = $this->normalizePreferredShift(
                (string) $request->input("preferred_shifts.{$tournamentId}", ''),
                $tournament
            );

            if ($choice === 'entry') {
                TournamentEntry::updateOrCreate(
                    [
                        'pro_bowler_id' => $proBowlerId,
                        'tournament_id' => $tournamentId,
                    ],
                    [
                        'status' => 'entry',
                        'preferred_shift_code' => $preferredShift,
                    ]
                );
            } else {
                TournamentEntry::updateOrCreate(
                    [
                        'pro_bowler_id' => $proBowlerId,
                        'tournament_id' => $tournamentId,
                    ],
                    [
                        'status' => 'no_entry',
                        'preferred_shift_code' => null,
                        'shift' => null,
                        'lane' => null,
                        'checked_in_at' => null,
                        'shift_drawn' => false,
                        'lane_drawn' => false,
                    ]
                );
            }
        }

        return redirect()
            ->route('tournament.entry.select')
            ->with('success', 'エントリー状況を更新しました。');
    }

    public function checkIn(TournamentEntry $entry)
    {
        $user = Auth::user();
        $userProBowlerId = (int) ($user?->pro_bowler_id ?? 0);

        if ($userProBowlerId <= 0 || $userProBowlerId !== (int) $entry->pro_bowler_id) {
            abort(403, '自分のエントリー以外は操作できません。');
        }

        $bowler = ProBowler::query()->find($entry->pro_bowler_id);
        $eligibility = $this->resolveEntryEligibility($bowler);

        if (!$eligibility['allowed']) {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', $eligibility['message']);
        }

        if ($entry->status !== 'entry') {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', 'エントリー有効時のみチェックインできます。');
        }

        $tournament = $entry->tournament()->first();

        $requiresShift = (bool) ($tournament?->use_shift_draw ?? false);
        $requiresLane = (bool) ($tournament?->use_lane_draw ?? false);

        if ($requiresShift && blank($entry->shift)) {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', '先にシフト抽選を完了してください。');
        }

        if ($requiresLane && blank($entry->lane)) {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', '先にレーン抽選を完了してください。');
        }

        if (!is_null($entry->checked_in_at)) {
            return redirect()
                ->route('tournament.entry.select')
                ->with('success', 'すでにチェックイン済みです。');
        }

        $entry->update([
            'checked_in_at' => now(),
        ]);

        return redirect()
            ->route('tournament.entry.select')
            ->with('success', 'チェックインを受け付けました。');
    }

    private function normalizePreferredShift(string $preferredShift, Tournament $tournament): ?string
    {
        if (!(bool) ($tournament->use_shift_draw ?? false)) {
            return null;
        }

        if (!(bool) ($tournament->accept_shift_preference ?? false)) {
            return null;
        }

        $preferredShift = trim($preferredShift);
        if ($preferredShift === '') {
            return null;
        }

        $available = collect(explode(',', (string) ($tournament->shift_codes ?? '')))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        return in_array($preferredShift, $available, true) ? $preferredShift : null;
    }

    private function resolveEntryEligibility(?ProBowler $bowler): array
    {
        if (!$bowler) {
            return [
                'allowed' => false,
                'message' => '選手情報が未結線のため、大会エントリーを利用できません。管理者に確認してください。',
                'member_class_label' => '-',
                'official_entry_label' => '不可',
                'active_label' => '未結線',
            ];
        }

        $memberClass = (string) ($bowler->member_class ?? '');
        $memberClassLabel = $this->memberClassLabel($memberClass);
        $officialEntryAllowed = (bool) ($bowler->can_enter_official_tournament ?? false);
        $isActive = (bool) ($bowler->is_active ?? false);

        if (!$isActive) {
            return [
                'allowed' => false,
                'message' => '現在の会員状態が無効のため、大会エントリー対象外です。',
                'member_class_label' => $memberClassLabel,
                'official_entry_label' => $officialEntryAllowed ? '可' : '不可',
                'active_label' => '無効',
            ];
        }

        if ($memberClass !== 'player') {
            return [
                'allowed' => false,
                'message' => $memberClassLabel . 'のため、大会エントリー対象外です。',
                'member_class_label' => $memberClassLabel,
                'official_entry_label' => $officialEntryAllowed ? '可' : '不可',
                'active_label' => '有効',
            ];
        }

        if (!$officialEntryAllowed) {
            return [
                'allowed' => false,
                'message' => '現在の会員区分では公式戦出場対象外として登録されています。',
                'member_class_label' => $memberClassLabel,
                'official_entry_label' => '不可',
                'active_label' => '有効',
            ];
        }

        return [
            'allowed' => true,
            'message' => '大会エントリー可能です。',
            'member_class_label' => $memberClassLabel,
            'official_entry_label' => '可',
            'active_label' => '有効',
        ];
    }

    private function memberClassLabel(?string $memberClass): string
    {
        return match ($memberClass) {
            'player' => '競技者',
            'pro_instructor' => 'プロインストラクター',
            'honorary_or_overseas' => '名誉プロ・海外プロ',
            'other' => 'その他',
            default => '-',
        };
    }
}
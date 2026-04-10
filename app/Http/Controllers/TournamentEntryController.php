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
            ->whereDate('entry_start', '<=', now())
            ->whereDate('entry_end', '>=', now())
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
        ]);

        $openTournamentIds = Tournament::query()
            ->whereDate('entry_start', '<=', now())
            ->whereDate('entry_end', '>=', now())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($request->input('entries', []) as $tournamentId => $choice) {
            $tournamentId = (int) $tournamentId;

            if (!in_array($tournamentId, $openTournamentIds, true)) {
                continue;
            }

            TournamentEntry::updateOrCreate(
                [
                    'pro_bowler_id' => $proBowlerId,
                    'tournament_id' => $tournamentId,
                ],
                [
                    'status' => $choice,
                ]
            );
        }

        return redirect()
            ->route('tournament.entry.select')
            ->with('success', 'エントリー状況を更新しました。');
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
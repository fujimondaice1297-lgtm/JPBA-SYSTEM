<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TournamentEntryAdminController extends Controller
{
    public function index(Request $request, Tournament $tournament)
    {
        $status = (string) $request->input('status', 'active');
        $keyword = trim((string) $request->input('q', ''));

        $query = TournamentEntry::query()
            ->with('bowler')
            ->withCount('balls')
            ->where('tournament_id', $tournament->id);

        if ($status === 'active') {
            $query->whereIn('status', ['entry', 'waiting']);
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        $this->applyBowlerKeyword($query, $keyword);

        $entries = $query
            ->orderByRaw("case when status = 'entry' then 0 when status = 'waiting' then 1 else 2 end")
            ->orderByRaw("case when waitlist_priority is null then 1 else 0 end")
            ->orderBy('waitlist_priority')
            ->orderByRaw("case when shift is null then 1 else 0 end")
            ->orderBy('shift')
            ->orderByRaw("case when lane is null then 1 else 0 end")
            ->orderBy('lane')
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString();

        $entries->through(function (TournamentEntry $entry) {
            $eligibility = $this->resolveEligibility($entry->bowler);
            $entry->eligibility_short = $eligibility['short'];
            $entry->eligibility_message = $eligibility['message'];

            return $entry;
        });

        $summary = $this->buildSummary($tournament);

        return view('tournament_entries.admin_index', compact(
            'tournament',
            'entries',
            'summary',
            'status',
            'keyword'
        ));
    }

    public function draws(Request $request, Tournament $tournament)
    {
        $keyword = trim((string) $request->input('q', ''));
        $pendingDraw = $request->boolean('pending_draw');

        $query = TournamentEntry::query()
            ->with('bowler')
            ->withCount('balls')
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry');

        if ($pendingDraw) {
            $query->where(function (Builder $q) {
                $q->whereNull('shift')
                    ->orWhereNull('lane');
            });
        }

        $this->applyBowlerKeyword($query, $keyword);

        $entries = $query
            ->orderByRaw("case when shift is null then 0 else 1 end")
            ->orderBy('shift')
            ->orderByRaw("case when lane is null then 0 else 1 end")
            ->orderBy('lane')
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString();

        $summary = $this->buildSummary($tournament);

        return view('tournament_entries.admin_draws', compact(
            'tournament',
            'entries',
            'summary',
            'keyword',
            'pendingDraw'
        ));
    }

    public function storeWaitlist(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'license_no' => ['required', 'string', 'max:255'],
            'waitlist_priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'waitlist_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $licenseNo = trim((string) $data['license_no']);

        $bowler = ProBowler::query()
            ->where('license_no', $licenseNo)
            ->first();

        if (!$bowler) {
            return redirect()
                ->route('tournaments.entries.index', $tournament->id)
                ->withErrors(['license_no' => '該当ライセンスNoの選手が見つかりません。'])
                ->withInput();
        }

        $existing = TournamentEntry::query()
            ->where('tournament_id', $tournament->id)
            ->where('pro_bowler_id', $bowler->id)
            ->first();

        if ($existing && $existing->status === 'entry') {
            return redirect()
                ->route('tournaments.entries.index', $tournament->id)
                ->withErrors(['license_no' => 'この選手はすでに参加登録済みです。'])
                ->withInput();
        }

        $entry = TournamentEntry::query()->updateOrCreate(
            [
                'tournament_id' => $tournament->id,
                'pro_bowler_id' => $bowler->id,
            ],
            [
                'status' => 'waiting',
                'waitlist_priority' => $data['waitlist_priority'] ?? null,
                'waitlisted_at' => $existing?->waitlisted_at ?? now(),
                'waitlist_note' => $data['waitlist_note'] ?: null,
                'promoted_from_waitlist_at' => null,
                'shift' => null,
                'lane' => null,
                'checked_in_at' => null,
                'shift_drawn' => false,
                'lane_drawn' => false,
            ]
        );

        return redirect()
            ->route('tournaments.entries.index', $tournament->id)
            ->with('success', 'ウェイティング登録を保存しました。 [' . $entry->bowler?->license_no . ' ' . ($entry->bowler?->name_kanji ?? '') . ']');
    }

    public function promoteWaitlist(TournamentEntry $entry)
    {
        if ((string) $entry->status !== 'waiting') {
            return redirect()
                ->route('tournaments.entries.index', $entry->tournament_id)
                ->with('error', 'ウェイティング行のみ繰り上げできます。');
        }

        $entry->update([
            'status' => 'entry',
            'promoted_from_waitlist_at' => now(),
            'shift' => null,
            'lane' => null,
            'checked_in_at' => null,
            'shift_drawn' => false,
            'lane_drawn' => false,
        ]);

        return redirect()
            ->route('tournaments.entries.index', $entry->tournament_id)
            ->with('success', 'ウェイティングから参加へ繰り上げました。');
    }

    private function applyBowlerKeyword(Builder $query, string $keyword): void
    {
        if ($keyword === '') {
            return;
        }

        $query->whereHas('bowler', function (Builder $q) use ($keyword) {
            $q->where('license_no', 'like', '%' . $keyword . '%')
                ->orWhere('name_kanji', 'like', '%' . $keyword . '%')
                ->orWhere('name_kana', 'like', '%' . $keyword . '%');
        });
    }

    private function buildSummary(Tournament $tournament): array
    {
        $base = TournamentEntry::query()->where('tournament_id', $tournament->id);

        return [
            'entry_count' => (clone $base)->where('status', 'entry')->count(),
            'waitlist_count' => (clone $base)->where('status', 'waiting')->count(),
            'checked_in_count' => (clone $base)->whereNotNull('checked_in_at')->count(),
            'pending_shift_count' => (clone $base)->where('status', 'entry')->whereNull('shift')->count(),
            'pending_lane_count' => (clone $base)->where('status', 'entry')->whereNull('lane')->count(),
        ];
    }

    private function resolveEligibility(?ProBowler $bowler): array
    {
        if (!$bowler) {
            return [
                'short' => '未結線',
                'message' => '選手情報未結線',
            ];
        }

        $memberClass = (string) ($bowler->member_class ?? '');
        $isActive = (bool) ($bowler->is_active ?? false);
        $canEnter = (bool) ($bowler->can_enter_official_tournament ?? false);

        if (!$isActive) {
            return [
                'short' => '会員無効',
                'message' => '現在の会員状態が無効です。',
            ];
        }

        if ($memberClass !== 'player') {
            return [
                'short' => $this->memberClassLabel($memberClass),
                'message' => '競技参加対象外の会員区分です。',
            ];
        }

        if (!$canEnter) {
            return [
                'short' => '公式戦対象外',
                'message' => '公式戦出場対象外として登録されています。',
            ];
        }

        return [
            'short' => '参加権利あり',
            'message' => '通常参加対象です。',
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
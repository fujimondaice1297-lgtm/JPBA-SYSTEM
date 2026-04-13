<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TournamentEntryPublicController extends Controller
{
    public function index(Request $request, Tournament $tournament)
    {
        $keyword = trim((string) $request->input('q', ''));

        $query = TournamentEntry::query()
            ->with('bowler')
            ->withCount('balls')
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', ['entry', 'waiting']);

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

        $summary = [
            'entry_count' => TournamentEntry::query()->where('tournament_id', $tournament->id)->where('status', 'entry')->count(),
            'waitlist_count' => TournamentEntry::query()->where('tournament_id', $tournament->id)->where('status', 'waiting')->count(),
            'checked_in_count' => TournamentEntry::query()->where('tournament_id', $tournament->id)->whereNotNull('checked_in_at')->count(),
        ];

        return view('member.tournament_entries_index', compact(
            'tournament',
            'entries',
            'summary',
            'keyword'
        ));
    }

    public function draws(Request $request, Tournament $tournament)
    {
        $keyword = trim((string) $request->input('q', ''));

        $query = TournamentEntry::query()
            ->with('bowler')
            ->withCount('balls')
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry');

        $this->applyBowlerKeyword($query, $keyword);

        $entries = $query
            ->orderByRaw("case when shift is null then 0 else 1 end")
            ->orderBy('shift')
            ->orderByRaw("case when lane is null then 0 else 1 end")
            ->orderBy('lane')
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString();

        $summary = [
            'entry_count' => TournamentEntry::query()->where('tournament_id', $tournament->id)->where('status', 'entry')->count(),
            'pending_shift_count' => TournamentEntry::query()->where('tournament_id', $tournament->id)->where('status', 'entry')->whereNull('shift')->count(),
            'pending_lane_count' => TournamentEntry::query()->where('tournament_id', $tournament->id)->where('status', 'entry')->whereNull('lane')->count(),
            'checked_in_count' => TournamentEntry::query()->where('tournament_id', $tournament->id)->whereNotNull('checked_in_at')->count(),
        ];

        return view('member.tournament_draws_index', compact(
            'tournament',
            'entries',
            'summary',
            'keyword'
        ));
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
}
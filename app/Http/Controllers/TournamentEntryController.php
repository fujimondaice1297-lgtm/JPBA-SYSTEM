<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentEntryController extends Controller
{
    public function select()
    {
        $proBowlerId = Auth::user()->pro_bowler_id;

        $tournaments = Tournament::whereDate('entry_start', '<=', now())
            ->whereDate('entry_end', '>=', now())
            ->get();

        // ここを修正：balls_count を eager load
        $entries = TournamentEntry::withCount('balls')
            ->where('pro_bowler_id', $proBowlerId)
            ->get()
            ->keyBy('tournament_id');

        return view('member.entry_select', compact('tournaments', 'entries'));
    }

    public function storeSelection(Request $request)
    {
        $proBowlerId = Auth::user()->pro_bowler_id;

        $request->validate([
            'entries' => 'array',
            'entries.*' => 'in:entry,no_entry',
        ]);

        foreach ($request->input('entries', []) as $tournamentId => $choice) {
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

        return redirect()->route('tournament.entry.select')
            ->with('success', 'エントリー状況を更新しました。');
    }
}


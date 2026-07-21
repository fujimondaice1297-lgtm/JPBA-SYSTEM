<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\TournamentPrioritySyncService;

class TournamentPriorityController extends Controller
{
    public function sync(Tournament $tournament, TournamentPrioritySyncService $service)
    {
        $summary = $service->sync($tournament);

        return back()->with('success', sprintf(
            '優先出場権を更新しました。年度シード %d名／資格ルール %d件／自動反映 %d名／選手未特定 %d名',
            $summary['annual_seed_count'],
            $summary['rule_count'],
            $summary['synced_count'],
            $summary['missing_bowler_count'],
        ));
    }
}

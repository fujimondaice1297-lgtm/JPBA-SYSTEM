<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\ProBowlerTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TitleSyncController extends Controller
{
    public function sync(Request $request, $tournamentId)
    {
        // ここでは生SQL想定で取りに行く（好きにEloquentへ置換可）
        $tournament = DB::table('tournaments')->where('id', $tournamentId)->first();
        abort_if(!$tournament, 404);

        // 優勝者（rank=1）かつ ライセンスNo.が入っている
        $winners = DB::table('tournament_results')
            ->where('tournament_id', $tournamentId)
            ->where('rank', 1) // 列名が違うならここを調整
            ->whereNotNull('license_no')
            ->get();

        $created = 0;

        foreach ($winners as $w) {
            $bowler = ProBowler::where('license_no', $w->license_no)->first();
            if (!$bowler) continue;

            // 重複防止：同一大会＋同一選手は一意
            $exists = ProBowlerTitle::where('pro_bowler_id', $bowler->id)
                ->where('tournament_id', $tournament->id)
                ->exists();

            if ($exists) continue;

            ProBowlerTitle::create([
                'pro_bowler_id' => $bowler->id,
                'tournament_id' => $tournament->id,
                'title_name'    => $tournament->name,
                'year'          => (int)$tournament->year,
                'won_date'      => $tournament->end_date ?? null,
                'source'        => 'result',
            ]);
            $created++;
        }

        return back()->with('success', "タイトル同期完了（新規 {$created} 件）");
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tournament;
use App\Models\PrizeDistribution;
use App\Models\DistributionPattern;

class PrizeDistributionController extends Controller
{
    public function index(Tournament $tournament)
    {
        $prizeDistributions = PrizeDistribution::where('tournament_id', $tournament->id)
            ->orderBy('rank')->get();

        return view('prize_distributions.index', compact('tournament', 'prizeDistributions'));
    }

    public function create(Tournament $tournament)
    {
        $patterns = DistributionPattern::where('type', 'prize')->get();
        $existingDistributions = $tournament->prizeDistributions()->orderBy('rank')->get();
        return view('prize_distributions.create', compact('tournament', 'patterns', 'existingDistributions'));
    }

    public function store(Request $request, Tournament $tournament)
    {
        // 1) パターン適用
        if ($request->filled('pattern_id')) {
            $pattern = DistributionPattern::findOrFail($request->pattern_id);

            foreach ($pattern->prizeDistributions as $row) {
                PrizeDistribution::updateOrCreate(
                    ['tournament_id' => $tournament->id, 'rank' => (int)$row->rank],
                    ['amount' => (int)$row->amount, 'pattern_id' => $pattern->id]
                );
            }

            return redirect()
                ->route('tournaments.prize_distributions.index', $tournament)
                ->with('success', '賞金配分（パターン）を保存しました。');
        }

        // 2) 手入力（enabled[] が無くても保存できるように正規化）
        $ranks   = (array) $request->input('rank',   []);
        $amounts = (array) $request->input('amount', []);
        $enabled = (array) $request->input('enabled', []); // 任意

        foreach ($ranks as $i => $rank) {
            $rank   = (int)($rank ?? 0);
            $amount = isset($amounts[$i]) ? (int)$amounts[$i] : null;

            // 入力が空の場合はスキップ
            if ($rank <= 0 || $amount === null) continue;

            // enabled[] がある場合はその行だけ通す（ない場合は全行通す）
            if ($enabled && !in_array($rank, $enabled)) continue;

            PrizeDistribution::updateOrCreate(
                ['tournament_id' => $tournament->id, 'rank' => $rank],
                ['amount'        => $amount, 'pattern_id' => null]
            );
        }

        return redirect()
            ->route('tournaments.prize_distributions.index', $tournament)
            ->with('success', '賞金配分を保存しました。');
    }


    public function show(string $id) {}

    public function edit(Tournament $tournament, PrizeDistribution $prize_distribution)
    {
        return view('prize_distributions.edit', compact('tournament', 'prize_distribution'));
    }

    public function update(Request $request, Tournament $tournament, PrizeDistribution $prize_distribution)
    {
        $prize_distribution->update([
            'rank'   => $request->input('rank'),
            'amount' => $request->input('amount'),
        ]);

        return redirect()->route('tournaments.prize_distributions.index', $tournament->id)
            ->with('success', '更新しました');
    }

    public function destroy(Tournament $tournament, PrizeDistribution $prize_distribution)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'この操作は許可されていません。');
        }
        $prize_distribution->delete();

        return redirect()->route('tournaments.prize_distributions.index', $tournament->id)
            ->with('success', '削除しました');
    }
}

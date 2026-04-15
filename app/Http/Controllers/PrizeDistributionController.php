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
        if ($request->filled('pattern_id')) {
            $pattern = DistributionPattern::findOrFail($request->pattern_id);

            foreach ($pattern->prizeDistributions as $row) {
                PrizeDistribution::updateOrCreate(
                    ['tournament_id' => $tournament->id, 'rank' => (int) $row->rank],
                    ['amount' => (int) $row->amount, 'pattern_id' => $pattern->id]
                );
            }

            return redirect()
                ->route('tournaments.results.index', $tournament)
                ->with('success', '賞金配分（パターン）を保存しました。大会成績一覧で「賞金・ポイント反映」を実行してください。');
        }

        $ranks = (array) $request->input('rank', []);
        $amounts = (array) $request->input('amount', []);
        $enabled = (array) $request->input('enabled', []);

        foreach ($ranks as $i => $rank) {
            $rank = (int) ($rank ?? 0);
            $amount = isset($amounts[$i]) ? (int) $amounts[$i] : null;

            if ($rank <= 0 || $amount === null) {
                continue;
            }

            if ($enabled && !in_array($rank, $enabled)) {
                continue;
            }

            PrizeDistribution::updateOrCreate(
                ['tournament_id' => $tournament->id, 'rank' => $rank],
                ['amount' => $amount, 'pattern_id' => null]
            );
        }

        return redirect()
            ->route('tournaments.results.index', $tournament)
            ->with('success', '賞金配分を保存しました。大会成績一覧で「賞金・ポイント反映」を実行してください。');
    }

    public function show(string $id) {}

    public function edit(Tournament $tournament, PrizeDistribution $prize_distribution)
    {
        return view('prize_distributions.edit', compact('tournament', 'prize_distribution'));
    }

    public function update(Request $request, Tournament $tournament, PrizeDistribution $prize_distribution)
    {
        $prize_distribution->update([
            'rank' => $request->input('rank'),
            'amount' => $request->input('amount'),
        ]);

        return redirect()
            ->route('tournaments.results.index', $tournament)
            ->with('success', '賞金配分を更新しました。大会成績一覧で「賞金・ポイント反映」を実行してください。');
    }

    public function destroy(Tournament $tournament, PrizeDistribution $prize_distribution)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'この操作は許可されていません。');
        }

        $prize_distribution->delete();

        return redirect()
            ->route('tournaments.results.index', $tournament)
            ->with('success', '賞金配分を削除しました。大会成績一覧で再度「賞金・ポイント反映」を実行してください。');
    }
}
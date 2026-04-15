<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tournament;
use App\Models\PointDistribution;
use App\Models\DistributionPattern;

class PointDistributionController extends Controller
{
    public function index(Tournament $tournament)
    {
        $pointDistributions = PointDistribution::where('tournament_id', $tournament->id)
            ->orderBy('rank')->get();

        return view('point_distributions.index', compact('tournament', 'pointDistributions'));
    }

    public function create(Tournament $tournament)
    {
        $patterns = DistributionPattern::where('type', 'point')->get();
        $existingDistributions = $tournament->pointDistributions()->orderBy('rank')->get();

        return view('point_distributions.create', compact('tournament', 'patterns', 'existingDistributions'));
    }

    public function store(Request $request, Tournament $tournament)
    {
        if ($request->filled('pattern_id')) {
            $pattern = DistributionPattern::findOrFail($request->pattern_id);

            foreach ($pattern->pointDistributions as $row) {
                PointDistribution::updateOrCreate(
                    ['tournament_id' => $tournament->id, 'rank' => (int) $row->rank],
                    ['points' => (int) $row->points, 'pattern_id' => $pattern->id]
                );
            }

            return redirect()
                ->route('tournaments.results.index', $tournament)
                ->with('success', 'ポイント配分（パターン）を保存しました。大会成績一覧で「賞金・ポイント反映」を実行してください。');
        }

        $ranks = (array) $request->input('rank', []);
        $points = (array) $request->input('points', []);
        $enabled = (array) $request->input('enabled', []);

        foreach ($ranks as $i => $rank) {
            $rank = (int) ($rank ?? 0);
            $point = isset($points[$i]) ? (int) $points[$i] : null;

            if ($rank <= 0 || $point === null) {
                continue;
            }

            if ($enabled && !in_array($rank, $enabled)) {
                continue;
            }

            PointDistribution::updateOrCreate(
                ['tournament_id' => $tournament->id, 'rank' => $rank],
                ['points' => $point, 'pattern_id' => null]
            );
        }

        return redirect()
            ->route('tournaments.results.index', $tournament)
            ->with('success', 'ポイント配分を保存しました。大会成績一覧で「賞金・ポイント反映」を実行してください。');
    }

    public function show(string $id) {}

    public function edit(Tournament $tournament, PointDistribution $point_distribution)
    {
        return view('point_distributions.edit', [
            'tournament' => $tournament,
            'pointDistribution' => $point_distribution,
        ]);
    }

    public function update(Request $request, Tournament $tournament, PointDistribution $point_distribution)
    {
        $point_distribution->update([
            'rank' => $request->input('rank'),
            'points' => $request->input('points'),
        ]);

        return redirect()
            ->route('tournaments.results.index', $tournament)
            ->with('success', 'ポイント配分を更新しました。大会成績一覧で「賞金・ポイント反映」を実行してください。');
    }

    public function destroy(Tournament $tournament, PointDistribution $point_distribution)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'この操作は許可されていません。');
        }

        $point_distribution->delete();

        return redirect()
            ->route('tournaments.results.index', $tournament)
            ->with('success', 'ポイント配分を削除しました。大会成績一覧で再度「賞金・ポイント反映」を実行してください。');
    }
}
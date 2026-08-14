<?php

namespace App\Http\Controllers;

use App\Models\ScoreSeriesDefinition;
use App\Models\Tournament;
use App\Services\AchievementDetectionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScoreSeriesDefinitionController extends Controller
{
    public function index(Request $request)
    {
        $tournamentId = $request->integer('tournament_id') ?: null;
        $tournaments = Tournament::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'start_date']);
        $definitions = ScoreSeriesDefinition::query()
            ->with('tournament')
            ->when($tournamentId, fn ($query) => $query->where('tournament_id', $tournamentId))
            ->orderByDesc('tournament_id')
            ->orderBy('stage')
            ->orderBy('start_game')
            ->get();

        return view('record_types.series_definitions', compact(
            'tournaments',
            'definitions',
            'tournamentId'
        ));
    }

    public function store(Request $request, AchievementDetectionService $detection)
    {
        $validated = $this->validateDefinition($request);
        $validated['is_800_eligible'] = $request->boolean('is_800_eligible');
        $validated['is_enabled'] = $request->boolean('is_enabled');
        $validated['source'] = 'manual';

        ScoreSeriesDefinition::query()->create($validated);
        $detection->scanTournament((int) $validated['tournament_id']);

        return back()->with('success', '3ゲームシリーズを登録し、保存済みスコアを再判定しました。');
    }

    public function update(
        Request $request,
        ScoreSeriesDefinition $scoreSeriesDefinition,
        AchievementDetectionService $detection
    ) {
        $oldTournamentId = (int) $scoreSeriesDefinition->tournament_id;
        $validated = $this->validateDefinition($request);
        $validated['is_800_eligible'] = $request->boolean('is_800_eligible');
        $validated['is_enabled'] = $request->boolean('is_enabled');
        $scoreSeriesDefinition->update($validated);

        $detection->reconcileSeriesDefinition($scoreSeriesDefinition);
        $detection->scanTournament($oldTournamentId);
        if ($oldTournamentId !== (int) $scoreSeriesDefinition->tournament_id) {
            $detection->scanTournament((int) $scoreSeriesDefinition->tournament_id);
        }

        return back()->with('success', '3ゲームシリーズを更新し、保存済みスコアを再判定しました。');
    }

    public function destroy(ScoreSeriesDefinition $scoreSeriesDefinition)
    {
        if ($scoreSeriesDefinition->records()->exists()) {
            return back()->withErrors([
                'definition' => 'この設定から検出された記録があるため削除できません。無効へ変更してください。',
            ]);
        }

        $scoreSeriesDefinition->delete();

        return back()->with('success', '3ゲームシリーズ設定を削除しました。');
    }

    private function validateDefinition(Request $request): array
    {
        $validated = $request->validate([
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'stage' => ['required', 'string', 'max:255'],
            'shift' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['M', 'F'])],
            'label' => ['required', 'string', 'max:255'],
            'start_game' => ['required', 'integer', 'min:1', 'max:99'],
            'end_game' => ['required', 'integer', 'min:1', 'max:99'],
            'is_800_eligible' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        if ((int) $validated['end_game'] - (int) $validated['start_game'] + 1 !== 3) {
            throw ValidationException::withMessages([
                'end_game' => '800シリーズ対象は、開始ゲームから終了ゲームまで正確に3ゲームで指定してください。',
            ]);
        }

        return $validated;
    }
}

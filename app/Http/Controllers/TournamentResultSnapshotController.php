<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use App\Models\TournamentResult;
use App\Models\ProBowler;
use App\Services\RoundRobinService;
use App\Services\StepLadderService;
use App\Services\ShootoutService;
use App\Services\TournamentResultSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class TournamentResultSnapshotController extends Controller
{
    public function index(Request $request, $tournament): View
    {
        $tournament = $this->resolveTournament($tournament);

        $gender = $this->normalizeGender($request->query('gender'));
        $shift = $this->normalizeText($request->query('shift'));

        $availableGenders = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->distinct()
            ->orderBy('gender')
            ->pluck('gender')
            ->filter(fn ($value) => in_array((string) $value, ['M', 'F'], true))
            ->values()
            ->all();

        $availableShifts = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->when($gender !== null, fn ($q) => $q->where('gender', $gender))
            ->whereNotNull('shift')
            ->where('shift', '!=', '')
            ->distinct()
            ->orderBy('shift')
            ->pluck('shift')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();

        $stageCounts = $this->detectStageGameCounts(
            tournamentId: (int) $tournament->id,
            gender: $gender,
            shift: $shift,
        );

        $presets = $this->buildPresets(
            tournamentId: (int) $tournament->id,
            stageCounts: $stageCounts,
            gender: $gender,
            shift: $shift,
            reflectedBy: auth()->id(),
        );

        $currentSnapshots = TournamentResultSnapshot::query()
            ->with(['reflectedBy'])
            ->where('tournament_id', $tournament->id)
            ->when(
                $gender !== null,
                fn ($q) => $q->where('gender', $gender),
                fn ($q) => $q->whereNull('gender')
            )
            ->when(
                $shift !== null,
                fn ($q) => $q->where('shift', $shift),
                fn ($q) => $q->whereNull('shift')
            )
            ->where('is_current', true)
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->get();

        $currentSnapshotsByCode = $currentSnapshots
            ->groupBy('result_code')
            ->map(fn (Collection $items) => $items->first());

        /** @var TournamentResultSnapshot|null $currentFinalSnapshot */
        $currentFinalSnapshot = $currentSnapshots->first(fn ($snapshot) => (bool) $snapshot->is_final)
            ?? $currentSnapshotsByCode->get('final_total');

        $finalResultsCount = DB::table('tournament_results')
            ->where('tournament_id', $tournament->id)
            ->count();

        $snapshots = TournamentResultSnapshot::query()
            ->with(['reflectedBy'])
            ->withCount('rows')
            ->where('tournament_id', $tournament->id)
            ->when(
                $gender !== null,
                fn ($q) => $q->where('gender', $gender),
                fn ($q) => $q->whereNull('gender')
            )
            ->when(
                $shift !== null,
                fn ($q) => $q->where('shift', $shift),
                fn ($q) => $q->whereNull('shift')
            )
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->get();

        return view('tournament_result_snapshots.index', [
            'tournament' => $tournament,
            'gender' => $gender,
            'shift' => $shift,
            'availableGenders' => $availableGenders,
            'availableShifts' => $availableShifts,
            'stageCounts' => $stageCounts,
            'presets' => $presets,
            'snapshots' => $snapshots,
            'currentFinalSnapshot' => $currentFinalSnapshot,
            'currentSnapshotsByCode' => $currentSnapshotsByCode,
            'finalResultsCount' => $finalResultsCount,
        ]);
    }

    public function reflect(
        Request $request,
        $tournament,
        TournamentResultSnapshotService $service,
        RoundRobinService $roundRobinService,
        StepLadderService $stepLadderService,
        ShootoutService $shootoutService
    ): RedirectResponse {
        $tournament = $this->resolveTournament($tournament);

        $request->validate([
            'preset_key' => 'required|string',
            'gender' => 'nullable|in:M,F',
            'shift' => 'nullable|string|max:16',
        ]);

        $gender = $this->normalizeGender($request->input('gender'));
        $shift = $this->normalizeText($request->input('shift'));

        $stageCounts = $this->detectStageGameCounts(
            tournamentId: (int) $tournament->id,
            gender: $gender,
            shift: $shift,
        );

        $presets = collect($this->buildPresets(
            tournamentId: (int) $tournament->id,
            stageCounts: $stageCounts,
            gender: $gender,
            shift: $shift,
            reflectedBy: auth()->id(),
        ))->keyBy('preset_key');

        $presetKey = (string) $request->input('preset_key');
        $preset = $presets->get($presetKey);

        if (!$preset) {
            return back()->withErrors(['preset_key' => '反映対象が見つかりません。'])->withInput();
        }

        $resultType = (string) ($preset['definition']['result_type'] ?? 'total_pin');

        if ($resultType === 'round_robin') {
            $snapshot = $this->createRoundRobinSnapshot($preset['definition'], $roundRobinService);
        } elseif ($resultType === 'step_ladder') {
            $snapshot = $this->createStepLadderSnapshot($preset['definition'], $stepLadderService);
        } elseif ($resultType === 'shootout') {
            $snapshot = $this->createShootoutSnapshot($preset['definition'], $shootoutService);
        } else {
            $snapshot = $service->createTotalPinSnapshot($preset['definition']);
        }

        if ($snapshot->is_final && (bool) $snapshot->getAttribute('synced_to_tournament_results')) {
            return redirect()
                ->route('tournaments.results.index', $tournament->id)
                ->with('success', '最終成績を反映しました。大会成績一覧に同期済みです。');
        }

        $message = '正式成績スナップショットを作成しました: ' . $snapshot->result_name;
        if ($snapshot->is_final) {
            $message .= '（性別またはシフト条件付きのため、大会成績一覧への同期は行っていません）';
        }

        return redirect()
            ->route('tournaments.result_snapshots.index', [
                'tournament' => $tournament->id,
                'gender' => $gender,
                'shift' => $shift,
            ])
            ->with('ok', $message);
    }

    public function show(Request $request, $tournament, $snapshot): View
    {
        $tournament = $this->resolveTournament($tournament);
        $snapshot = $this->resolveSnapshot($tournament, $snapshot);

        $stageCounts = $this->detectStageGameCounts(
            tournamentId: (int) $tournament->id,
            gender: $snapshot->gender,
            shift: $snapshot->shift,
        );

        $stageColumns = [
            [
                'stage' => '決勝',
                'label' => '決勝（' . ((int) ($stageCounts['決勝'] ?? 0)) . 'ゲーム）',
                'games' => (int) ($stageCounts['決勝'] ?? 0),
            ],
            [
                'stage' => 'ラウンドロビン',
                'label' => 'ラウンドロビン（' . ((int) ($stageCounts['ラウンドロビン'] ?? 0)) . 'ゲーム）',
                'games' => (int) ($stageCounts['ラウンドロビン'] ?? 0),
            ],
            [
                'stage' => '準決勝',
                'label' => '準決勝（' . ((int) ($stageCounts['準決勝'] ?? 0)) . 'ゲーム）',
                'games' => (int) ($stageCounts['準決勝'] ?? 0),
            ],
            [
                'stage' => '準々決勝',
                'label' => '準々決勝（' . ((int) ($stageCounts['準々決勝'] ?? 0)) . 'ゲーム）',
                'games' => (int) ($stageCounts['準々決勝'] ?? 0),
            ],
            [
                'stage' => '予選',
                'label' => '予選（' . ((int) ($stageCounts['予選'] ?? 0)) . 'ゲーム）',
                'games' => (int) ($stageCounts['予選'] ?? 0),
            ],
        ];

        $rows = DB::table('tournament_result_snapshot_rows')
            ->select([
                'id',
                'ranking',
                'pro_bowler_id',
                'pro_bowler_license_no',
                'display_name',
                'scratch_pin',
                'carry_pin',
                'total_pin',
                'games',
                'average',
            ])
            ->where('snapshot_id', $snapshot->id)
            ->orderBy('ranking')
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString();

        $calculationDefinition = is_array($snapshot->calculation_definition)
            ? $snapshot->calculation_definition
            : (json_decode((string) $snapshot->calculation_definition, true) ?: []);

        $sourceSets = array_values(array_filter(
            (array) ($calculationDefinition['source_sets'] ?? []),
            fn ($set) => is_array($set)
        ));

        $stagePinMap = $this->buildStagePinMap(
            tournamentId: (int) $tournament->id,
            gender: $snapshot->gender,
            shift: $snapshot->shift,
            sourceSets: $sourceSets,
            rows: $rows->getCollection(),
        );

        $currentSnapshots = TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->when(
                $snapshot->gender !== null,
                fn ($q) => $q->where('gender', $snapshot->gender),
                fn ($q) => $q->whereNull('gender')
            )
            ->when(
                $snapshot->shift !== null,
                fn ($q) => $q->where('shift', $snapshot->shift),
                fn ($q) => $q->whereNull('shift')
            )
            ->where('is_current', true)
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->get();

        $currentSnapshotsByCode = $currentSnapshots
            ->groupBy('result_code')
            ->map(fn (Collection $items) => $items->first());

        $showPresets = $this->buildPresets(
            tournamentId: (int) $tournament->id,
            stageCounts: $stageCounts,
            gender: $snapshot->gender,
            shift: $snapshot->shift,
            reflectedBy: auth()->id(),
        );

        $sameResultSnapshots = TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->where('result_code', $snapshot->result_code)
            ->when(
                $snapshot->gender !== null,
                fn ($q) => $q->where('gender', $snapshot->gender),
                fn ($q) => $q->whereNull('gender')
            )
            ->when(
                $snapshot->shift !== null,
                fn ($q) => $q->where('shift', $snapshot->shift),
                fn ($q) => $q->whereNull('shift')
            )
            ->orderByDesc('is_current')
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'result_name',
                'result_code',
                'is_current',
                'is_final',
                'reflected_at',
            ]);

        $finalResultsCount = DB::table('tournament_results')
            ->where('tournament_id', $tournament->id)
            ->count();

        return view('tournament_result_snapshots.show', [
            'tournament' => $tournament,
            'snapshot' => $snapshot,
            'rows' => $rows,
            'finalResultsCount' => $finalResultsCount,
            'backQuery' => array_filter([
                'gender' => $snapshot->gender,
                'shift' => $snapshot->shift,
            ], fn ($value) => $value !== null && $value !== ''),
            'stageColumns' => $stageColumns,
            'stagePinMap' => $stagePinMap,
            'showPresets' => $showPresets,
            'currentSnapshotsByCode' => $currentSnapshotsByCode,
            'sameResultSnapshots' => $sameResultSnapshots,
        ]);
    }

    /**
     * @return array<string,int>
     */
    private function detectStageGameCounts(int $tournamentId, ?string $gender, ?string $shift): array
    {
        $rows = DB::table('game_scores')
            ->select('stage', DB::raw('MAX(game_number) as max_game'))
            ->where('tournament_id', $tournamentId)
            ->when($gender !== null, fn ($q) => $q->where('gender', $gender))
            ->when($shift !== null, fn ($q) => $q->where('shift', $shift))
            ->groupBy('stage')
            ->get();

        $raw = [];
        foreach ($rows as $row) {
            $stage = trim((string) ($row->stage ?? ''));
            $maxGame = (int) ($row->max_game ?? 0);
            if ($stage !== '' && $maxGame > 0) {
                $raw[$stage] = $maxGame;
            }
        }

        $ordered = [];
        foreach (['予選', '準々決勝', '準決勝', 'ラウンドロビン', '決勝'] as $stage) {
            if (isset($raw[$stage])) {
                $ordered[$stage] = $raw[$stage];
                unset($raw[$stage]);
            }
        }
        foreach ($raw as $stage => $maxGame) {
            $ordered[$stage] = $maxGame;
        }

        return $ordered;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildPresets(
        int $tournamentId,
        array $stageCounts,
        ?string $gender,
        ?string $shift,
        ?int $reflectedBy
    ): array {
        $presets = [];

        $prelimGames = (int) ($stageCounts['予選'] ?? 0);
        $quarterGames = (int) ($stageCounts['準々決勝'] ?? 0);
        $semiGames = (int) ($stageCounts['準決勝'] ?? 0);
        $roundRobinGames = (int) ($stageCounts['ラウンドロビン'] ?? 0);
        $shootoutGames = (int) ($stageCounts['シュートアウト'] ?? 0);
        $finalGames = (int) ($stageCounts['決勝'] ?? 0);

        $flowType = trim((string) DB::table('tournaments')->where('id', $tournamentId)->value('result_flow_type'));
        $resultCarrySettings = $this->loadResultCarrySettings($tournamentId);

        $usesStepLadderFinal = in_array($flowType, [
            'prelim_to_rr_to_final',
            'prelim_to_quarterfinal_to_rr_to_final',
        ], true);

        $usesShootoutFinal = in_array($flowType, [
            'prelim_to_shootout_to_final',
            'prelim_to_quarterfinal_to_shootout_to_final',
            'prelim_to_semifinal_to_shootout_to_final',
        ], true);

        if ($prelimGames > 0) {
            $half = intdiv($prelimGames, 2);
            if ($half > 0 && $half < $prelimGames) {
                $presets[] = $this->makePreset(
                    tournamentId: $tournamentId,
                    resultCode: 'prelim_first_half',
                    resultName: '予選前半成績',
                    stageName: '予選',
                    gender: $gender,
                    shift: $shift,
                    reflectedBy: $reflectedBy,
                    sourceSets: [
                        ['stage' => '予選', 'game_from' => 1, 'game_to' => $half, 'bucket' => 'scratch'],
                    ],
                    descriptionLines: [
                        'scratch: 予選 1G-' . $half . 'G',
                    ],
                );

                $presets[] = $this->makePreset(
                    tournamentId: $tournamentId,
                    resultCode: 'prelim_second_half',
                    resultName: '予選後半成績',
                    stageName: '予選',
                    gender: $gender,
                    shift: $shift,
                    reflectedBy: $reflectedBy,
                    sourceSets: [
                        ['stage' => '予選', 'game_from' => $half + 1, 'game_to' => $prelimGames, 'bucket' => 'scratch'],
                    ],
                    descriptionLines: [
                        'scratch: 予選 ' . ($half + 1) . 'G-' . $prelimGames . 'G',
                    ],
                );
            }

            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'prelim_total',
                resultName: '予選通算成績',
                stageName: '予選',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: [
                    ['stage' => '予選', 'game_from' => 1, 'game_to' => $prelimGames, 'bucket' => 'scratch'],
                ],
                descriptionLines: [
                    'scratch: 予選 1G-' . $prelimGames . 'G',
                ],
            );
        }

        if ($quarterGames > 0) {
            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'quarterfinal_stage',
                resultName: '準々決勝成績',
                stageName: '準々決勝',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: [
                    ['stage' => '準々決勝', 'game_from' => 1, 'game_to' => $quarterGames, 'bucket' => 'scratch'],
                ],
                descriptionLines: [
                    'scratch: 準々決勝 1G-' . $quarterGames . 'G',
                ],
            );

            if ($prelimGames > 0) {
                $presets[] = $this->makePreset(
                    tournamentId: $tournamentId,
                    resultCode: 'quarterfinal_total',
                    resultName: '準々決勝通算成績',
                    stageName: '準々決勝',
                    gender: $gender,
                    shift: $shift,
                    reflectedBy: $reflectedBy,
                    sourceSets: [
                        ['stage' => '予選', 'game_from' => 1, 'game_to' => $prelimGames, 'bucket' => 'carry'],
                        ['stage' => '準々決勝', 'game_from' => 1, 'game_to' => $quarterGames, 'bucket' => 'scratch'],
                    ],
                    descriptionLines: [
                        'carry: 予選 1G-' . $prelimGames . 'G',
                        'scratch: 準々決勝 1G-' . $quarterGames . 'G',
                    ],
                );
            }
        }

        if ($semiGames > 0) {
            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'semifinal_stage',
                resultName: '準決勝成績',
                stageName: '準決勝',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: [
                    ['stage' => '準決勝', 'game_from' => 1, 'game_to' => $semiGames, 'bucket' => 'scratch'],
                ],
                descriptionLines: [
                    'scratch: 準決勝 1G-' . $semiGames . 'G',
                ],
            );

            $sourceSets = [];
            $descriptionLines = [];
            if ($prelimGames > 0) {
                $sourceSets[] = ['stage' => '予選', 'game_from' => 1, 'game_to' => $prelimGames, 'bucket' => 'carry'];
                $descriptionLines[] = 'carry: 予選 1G-' . $prelimGames . 'G';
            }
            if ($quarterGames > 0) {
                $sourceSets[] = ['stage' => '準々決勝', 'game_from' => 1, 'game_to' => $quarterGames, 'bucket' => 'carry'];
                $descriptionLines[] = 'carry: 準々決勝 1G-' . $quarterGames . 'G';
            }
            $sourceSets[] = ['stage' => '準決勝', 'game_from' => 1, 'game_to' => $semiGames, 'bucket' => 'scratch'];
            $descriptionLines[] = 'scratch: 準決勝 1G-' . $semiGames . 'G';

            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'semifinal_total',
                resultName: '準決勝通算成績',
                stageName: '準決勝',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: $sourceSets,
                descriptionLines: $descriptionLines,
            );
        }


        if ($roundRobinGames > 0) {
            $sourceSets = [];
            $descriptionLines = [];

            if ($quarterGames > 0) {
                $sourceSets[] = ['stage' => '準々決勝', 'game_from' => 1, 'game_to' => $quarterGames, 'bucket' => 'carry'];
                $descriptionLines[] = 'carry: 準々決勝 1G-' . $quarterGames . 'G';
            } elseif ($prelimGames > 0) {
                $sourceSets[] = ['stage' => '予選', 'game_from' => 1, 'game_to' => $prelimGames, 'bucket' => 'carry'];
                $descriptionLines[] = 'carry: 予選 1G-' . $prelimGames . 'G';
            }

            $sourceSets[] = ['stage' => 'ラウンドロビン', 'game_from' => 1, 'game_to' => $roundRobinGames, 'bucket' => 'scratch'];
            $descriptionLines[] = 'scratch: ラウンドロビン 1G-' . $roundRobinGames . 'G';
            $descriptionLines[] = 'bonus: 勝敗ボーナス込みで順位を確定';

            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'round_robin_total',
                resultName: 'ラウンドロビン最終成績',
                stageName: 'ラウンドロビン',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: $sourceSets,
                descriptionLines: $descriptionLines,
                resultType: 'round_robin',
            );
        }

        if ($usesStepLadderFinal && $roundRobinGames > 0 && $finalGames > 0) {
            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'step_ladder_final',
                resultName: '決勝ステップラダー最終成績',
                stageName: '決勝',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: [
                    ['stage' => '決勝', 'game_from' => 1, 'game_to' => $finalGames, 'bucket' => 'scratch'],
                ],
                descriptionLines: [
                    'seed: ラウンドロビン最終成績 上位3名',
                    'scratch: 決勝ステップラダー 1G-' . $finalGames . 'G',
                    'ranking: 優勝 / 準優勝 / 3位 を正式順位として反映',
                ],
                isFinal: true,
                resultType: 'step_ladder',
            );
        }

        if ($usesShootoutFinal && $shootoutGames > 0) {
            $seedSourceResultCode = trim((string) DB::table('tournaments')->where('id', $tournamentId)->value('shootout_seed_source_result_code'))
                ?: $this->defaultShootoutSeedSourceResultCode($flowType);

            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'shootout_final',
                resultName: 'シュートアウト最終成績',
                stageName: 'シュートアウト',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: [
                    ['stage' => 'シュートアウト', 'game_from' => 1, 'game_to' => $shootoutGames, 'bucket' => 'scratch'],
                ],
                descriptionLines: [
                    'seed: ' . $this->shootoutSeedSourceLabel($seedSourceResultCode) . ' 上位8名',
                    'scratch: シュートアウト 1st / 2nd / 優勝決定戦',
                    'ranking: 勝者は次マッチへ進出、敗退者順位は元通過順位を引き継ぎ',
                ],
                isFinal: true,
                resultType: 'shootout',
            );
        }

        if ($finalGames > 0 && !$usesStepLadderFinal) {
            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'final_stage',
                resultName: '決勝成績',
                stageName: '決勝',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: [
                    ['stage' => '決勝', 'game_from' => 1, 'game_to' => $finalGames, 'bucket' => 'scratch'],
                ],
                descriptionLines: [
                    'scratch: 決勝 1G-' . $finalGames . 'G',
                ],
            );

            $sourceSets = [];
            $descriptionLines = [];
            if ($prelimGames > 0) {
                $sourceSets[] = ['stage' => '予選', 'game_from' => 1, 'game_to' => $prelimGames, 'bucket' => 'carry'];
                $descriptionLines[] = 'carry: 予選 1G-' . $prelimGames . 'G';
            }
            if ($quarterGames > 0) {
                $sourceSets[] = ['stage' => '準々決勝', 'game_from' => 1, 'game_to' => $quarterGames, 'bucket' => 'carry'];
                $descriptionLines[] = 'carry: 準々決勝 1G-' . $quarterGames . 'G';
            }
            if ($semiGames > 0) {
                $sourceSets[] = ['stage' => '準決勝', 'game_from' => 1, 'game_to' => $semiGames, 'bucket' => 'carry'];
                $descriptionLines[] = 'carry: 準決勝 1G-' . $semiGames . 'G';
            }
            $sourceSets[] = ['stage' => '決勝', 'game_from' => 1, 'game_to' => $finalGames, 'bucket' => 'scratch'];
            $descriptionLines[] = 'scratch: 決勝 1G-' . $finalGames . 'G';

            $presets[] = $this->makePreset(
                tournamentId: $tournamentId,
                resultCode: 'final_total',
                resultName: '最終成績',
                stageName: '決勝',
                gender: $gender,
                shift: $shift,
                reflectedBy: $reflectedBy,
                sourceSets: $sourceSets,
                descriptionLines: $descriptionLines,
                isFinal: true,
            );
        }

        if (!empty($resultCarrySettings)) {
            $presets = array_map(
                fn (array $preset): array => $this->applyResultCarrySettingsToPreset(
                    preset: $preset,
                    stageCounts: $stageCounts,
                    carrySettings: $resultCarrySettings
                ),
                $presets
            );
        }

        return $presets;
    }


    /**
     * @param array<int,array<string,mixed>> $sourceSets
     * @param array<int,string> $descriptionLines
     * @return array<string,mixed>
     */

    private function loadResultCarrySettings(int $tournamentId): array
    {
        $raw = DB::table('tournaments')
            ->where('id', $tournamentId)
            ->value('result_carry_settings');

        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function applyResultCarrySettingsToPreset(
        array $preset,
        array $stageCounts,
        array $carrySettings
    ): array {
        $definition = (array) ($preset['definition'] ?? []);
        $resultCode = (string) ($definition['result_code'] ?? ($preset['preset_key'] ?? ''));
        $resultType = (string) ($definition['result_type'] ?? 'total_pin');

        // まずは total_pin 系の反映単位だけに適用する。
        // round_robin / step_ladder は専用Service側の持ち込み判定があるため、別フェーズで接続する。
        if ($resultCode === '' || $resultType !== 'total_pin') {
            return $preset;
        }

        $setting = $carrySettings[$resultCode] ?? null;

        if (!is_array($setting)) {
            return $preset;
        }

        $sourceStages = array_values(array_filter(
            (array) ($setting['source_stages'] ?? []),
            fn ($stage) => is_string($stage) && trim($stage) !== ''
        ));

        if (empty($sourceStages)) {
            return $preset;
        }

        $validStages = [];

        foreach ($sourceStages as $stage) {
            $stage = trim((string) $stage);
            $games = (int) ($stageCounts[$stage] ?? 0);

            if ($stage === '' || $games <= 0) {
                continue;
            }

            $validStages[] = [
                'stage' => $stage,
                'games' => $games,
            ];
        }

        if (empty($validStages)) {
            return $preset;
        }

        $lastIndex = count($validStages) - 1;
        $sourceSets = [];
        $descriptionLines = [];

        foreach ($validStages as $index => $stageInfo) {
            $bucket = $index === $lastIndex ? 'scratch' : 'carry';
            $stage = $stageInfo['stage'];
            $games = (int) $stageInfo['games'];

            $sourceSets[] = [
                'stage' => $stage,
                'game_from' => 1,
                'game_to' => $games,
                'bucket' => $bucket,
            ];

            $descriptionLines[] = $bucket . ': ' . $stage . ' 1G-' . $games . 'G';
        }

        $calculationDefinition = (array) ($definition['calculation_definition'] ?? []);
        $calculationDefinition['source_sets'] = $sourceSets;
        $calculationDefinition['carry_setting'] = [
            'source' => 'tournaments.result_carry_settings',
            'result_code' => $resultCode,
            'source_stages' => array_map(fn (array $row): string => $row['stage'], $validStages),
        ];

        $preset['description_lines'] = $descriptionLines;
        $preset['definition']['stage_name'] = $validStages[$lastIndex]['stage'];
        $preset['definition']['calculation_definition'] = $calculationDefinition;

        return $preset;
    }

    private function makePreset(
        int $tournamentId,
        string $resultCode,
        string $resultName,
        ?string $stageName,
        ?string $gender,
        ?string $shift,
        ?int $reflectedBy,
        array $sourceSets,
        array $descriptionLines,
        bool $isFinal = false,
        string $resultType = 'total_pin'
    ): array {
        return [
            'preset_key' => $resultCode,
            'result_name' => $resultName,
            'description_lines' => $descriptionLines,
            'definition' => [
                'tournament_id' => $tournamentId,
                'result_code' => $resultCode,
                'result_name' => $resultName,
                'result_type' => $resultType,
                'stage_name' => $stageName,
                'gender' => $gender,
                'shift' => $shift,
                'is_final' => $isFinal,
                'is_published' => false,
                'reflected_by' => $reflectedBy,
                'notes' => null,
                'calculation_definition' => [
                    'source_sets' => $sourceSets,
                ],
            ],
        ];
    }


    private function createRoundRobinSnapshot(array $definition, RoundRobinService $roundRobinService): TournamentResultSnapshot
    {
        $tournamentId = (int) ($definition['tournament_id'] ?? 0);
        $gender = $this->normalizeGender($definition['gender'] ?? null);
        $shift = $this->normalizeText($definition['shift'] ?? null);
        $calculationDefinition = (array) ($definition['calculation_definition'] ?? []);
        $sourceSets = array_values(array_filter((array) ($calculationDefinition['source_sets'] ?? []), fn ($set) => is_array($set)));

        $rr = $roundRobinService->build([
            'tournament_id' => $tournamentId,
            'gender' => $gender ?? '',
            'shift' => $shift ?? '',
            'upto_game' => 99,
        ]);

        if (($rr['missing_carry_snapshot'] ?? false) || empty($rr['players'])) {
            throw new \InvalidArgumentException('ラウンドロビンの持込元 snapshot が見つかりません。先に直前ステージの正式成績反映を実行してください。');
        }

        $players = (array) ($rr['players'] ?? []);
        $meta = (array) ($rr['meta'] ?? []);
        $roundRobinGames = (int) ($meta['round_robin_games'] ?? 0);
        $carryGameCount = 0;
        $carryStageNames = [];

        foreach ($sourceSets as $set) {
            if (($set['bucket'] ?? 'scratch') !== 'carry') {
                continue;
            }
            $carryGameCount += max(0, ((int) ($set['game_to'] ?? 0)) - ((int) ($set['game_from'] ?? 0)) + 1);
            $stage = trim((string) ($set['stage'] ?? ''));
            if ($stage !== '' && !in_array($stage, $carryStageNames, true)) {
                $carryStageNames[] = $stage;
            }
        }

        return DB::transaction(function () use ($definition, $players, $calculationDefinition, $roundRobinGames, $carryGameCount, $carryStageNames, $tournamentId, $gender, $shift) {
            $this->closeCurrentSnapshots(
                tournamentId: $tournamentId,
                resultCode: (string) ($definition['result_code'] ?? ''),
                gender: $gender,
                shift: $shift,
            );

            $snapshot = TournamentResultSnapshot::create([
                'tournament_id' => $tournamentId,
                'result_code' => (string) ($definition['result_code'] ?? ''),
                'result_name' => (string) ($definition['result_name'] ?? ''),
                'result_type' => 'round_robin',
                'stage_name' => (string) ($definition['stage_name'] ?? 'ラウンドロビン'),
                'gender' => $gender,
                'shift' => $shift,
                'games_count' => $roundRobinGames + $carryGameCount,
                'carry_game_count' => $carryGameCount,
                'carry_stage_names' => $carryStageNames === [] ? null : $carryStageNames,
                'calculation_definition' => $calculationDefinition,
                'reflected_at' => Carbon::now(),
                'reflected_by' => isset($definition['reflected_by']) && $definition['reflected_by'] !== '' ? (int) $definition['reflected_by'] : null,
                'is_final' => (bool) ($definition['is_final'] ?? false),
                'is_published' => (bool) ($definition['is_published'] ?? false),
                'is_current' => true,
                'notes' => $definition['notes'] ?? null,
            ]);

            foreach (array_values($players) as $index => $row) {
                $carryPin = (int) ($row['carry_pin'] ?? 0);
                $rrPin = (int) ($row['rr_total_pin'] ?? 0);
                $bonusPoints = (int) ($row['bonus_points'] ?? 0);
                $carryGames = (int) ($row['carry_games'] ?? 0);
                $rrGames = count((array) ($row['rr_scores'] ?? []));
                $scoreAverage = ($carryGames + $rrGames) > 0
                    ? round(($carryPin + $rrPin) / ($carryGames + $rrGames), 2)
                    : null;
                $licenseNo = $this->normalizeText($row['license_no'] ?? null);
                $displayName = trim((string) ($row['display_name'] ?? ''));
                $amateurName = $licenseNo === null ? ($displayName !== '' ? $displayName : null) : null;

                TournamentResultSnapshotRow::create([
                    'snapshot_id' => $snapshot->id,
                    'ranking' => $index + 1,
                    'pro_bowler_id' => isset($row['pro_bowler_id']) ? (int) $row['pro_bowler_id'] ?: null : null,
                    'pro_bowler_license_no' => $licenseNo,
                    'amateur_name' => $amateurName,
                    'display_name' => $displayName !== '' ? $displayName : $amateurName,
                    'gender' => $gender,
                    'shift' => $shift,
                    'entry_number' => null,
                    'scratch_pin' => $rrPin,
                    'carry_pin' => $carryPin,
                    'total_pin' => (int) ($row['overall_total_points'] ?? 0),
                    'games' => $carryGames + $rrGames,
                    'average' => $scoreAverage,
                    'tie_break_value' => (float) ($row['overall_total_points'] ?? 0),
                    'points' => $bonusPoints,
                    'prize_money' => null,
                ]);
            }

            return $snapshot->load(['rows', 'tournament', 'reflectedBy']);
        });
    }


    private function createStepLadderSnapshot(array $definition, StepLadderService $stepLadderService): TournamentResultSnapshot
    {
        $tournamentId = (int) ($definition['tournament_id'] ?? 0);
        $gender = $this->normalizeGender($definition['gender'] ?? null);
        $shift = $this->normalizeText($definition['shift'] ?? null);

        $stepLadder = $stepLadderService->build([
            'tournament_id' => $tournamentId,
            'gender' => $gender ?? '',
            'shift' => $shift ?? '',
            'upto_game' => 2,
        ]);

        if (($stepLadder['missing_seed_snapshot'] ?? false) || empty($stepLadder['seeds'])) {
            throw new \InvalidArgumentException('決勝ステップラダーの元になるラウンドロビン最終成績が見つかりません。先に「ラウンドロビン最終成績」を反映してください。');
        }

        $semifinal = (array) ($stepLadder['semifinal'] ?? []);
        $final = (array) ($stepLadder['final'] ?? []);
        $standings = array_values((array) ($stepLadder['standings'] ?? []));
        $meta = (array) ($stepLadder['meta'] ?? []);

        if (($semifinal['status'] ?? '') !== 'done') {
            throw new \InvalidArgumentException('決勝ステップラダー1G（2位通過 vs 3位通過）が未確定です。先に1Gを入力してください。');
        }

        if (($final['status'] ?? '') !== 'done') {
            throw new \InvalidArgumentException('決勝ステップラダー2G（優勝決定戦）が未確定です。先に2Gを入力してください。');
        }

        if (count($standings) < 3) {
            throw new \InvalidArgumentException('決勝ステップラダーの順位を3名分作成できませんでした。入力内容を確認してください。');
        }

        $finalRows = $this->buildStepLadderFinalSnapshotRows(
            tournamentId: $tournamentId,
            gender: $gender,
            shift: $shift,
            standings: $standings,
            semifinal: $semifinal,
            final: $final,
            meta: $meta,
        );

        if (count($finalRows) < 3) {
            throw new \InvalidArgumentException('決勝ステップラダーの最終順位を作成できませんでした。ラウンドロビン最終成績と決勝入力を確認してください。');
        }

        $calculationDefinition = (array) ($definition['calculation_definition'] ?? []);
        $calculationDefinition['step_ladder'] = [
            'seed_snapshot_code' => (string) ($meta['seed_snapshot_code'] ?? 'round_robin_total'),
            'seed_snapshot_id' => $meta['seed_snapshot_id'] ?? null,
            'semifinal_status' => (string) ($semifinal['status'] ?? ''),
            'final_status' => (string) ($final['status'] ?? ''),
            'final_rows_count' => count($finalRows),
            'ranking_policy' => '1-3 step ladder, 4+ round robin order, then previous stage order',
        ];

        return DB::transaction(function () use (
            $definition,
            $calculationDefinition,
            $finalRows,
            $tournamentId,
            $gender,
            $shift
        ) {
            $this->closeCurrentSnapshots(
                tournamentId: $tournamentId,
                resultCode: (string) ($definition['result_code'] ?? ''),
                gender: $gender,
                shift: $shift,
            );

            $snapshot = TournamentResultSnapshot::create([
                'tournament_id' => $tournamentId,
                'result_code' => (string) ($definition['result_code'] ?? 'step_ladder_final'),
                'result_name' => (string) ($definition['result_name'] ?? '決勝ステップラダー最終成績'),
                'result_type' => 'step_ladder',
                'stage_name' => (string) ($definition['stage_name'] ?? '決勝'),
                'gender' => $gender,
                'shift' => $shift,
                'games_count' => 2,
                'carry_game_count' => 0,
                'carry_stage_names' => null,
                'calculation_definition' => $calculationDefinition,
                'reflected_at' => Carbon::now(),
                'reflected_by' => isset($definition['reflected_by']) && $definition['reflected_by'] !== '' ? (int) $definition['reflected_by'] : null,
                'is_final' => true,
                'is_published' => (bool) ($definition['is_published'] ?? false),
                'is_current' => true,
                'notes' => $definition['notes'] ?? null,
            ]);

            foreach ($finalRows as $row) {
                TournamentResultSnapshotRow::create(array_merge($row, [
                    'snapshot_id' => $snapshot->id,
                ]));
            }

            $syncedToTournamentResults = $this->syncFinalSnapshotToTournamentResults($snapshot);

            $snapshot = $snapshot->load(['rows', 'tournament', 'reflectedBy']);
            $snapshot->setAttribute('synced_to_tournament_results', $syncedToTournamentResults);

            return $snapshot;
        });
    }

    private function createShootoutSnapshot(array $definition, ShootoutService $shootoutService): TournamentResultSnapshot
    {
        $tournamentId = (int) ($definition['tournament_id'] ?? 0);
        $gender = $this->normalizeGender($definition['gender'] ?? null);
        $shift = $this->normalizeText($definition['shift'] ?? null);

        $tournament = Tournament::query()->find($tournamentId);
        if (!$tournament) {
            throw new \InvalidArgumentException('大会が見つかりません。');
        }

        $flowType = trim((string) ($tournament->result_flow_type ?? 'legacy_standard')) ?: 'legacy_standard';
        $seedSourceResultCode = trim((string) ($tournament->shootout_seed_source_result_code ?? ''))
            ?: $this->defaultShootoutSeedSourceResultCode($flowType);

        $seedSnapshot = $this->findCurrentSnapshotByCode($tournamentId, $seedSourceResultCode, $gender, $shift);
        if (!$seedSnapshot) {
            throw new \InvalidArgumentException('シュートアウト進出元 snapshot（' . $seedSourceResultCode . '）が見つかりません。先に進出元ステージの正式成績反映を実行してください。');
        }

        $seedEntries = $this->buildShootoutSeedEntriesFromSnapshot((int) $seedSnapshot->id, 8);
        if (count($seedEntries) < 8) {
            throw new \InvalidArgumentException('シュートアウト進出者を8名分作成できませんでした。進出元snapshotを確認してください。');
        }

        $shootout = $shootoutService->buildStandard8(
            seedEntries: $seedEntries,
            matchScores: $this->loadShootoutMatchScores($tournamentId)
        );

        $matches = collect((array) ($shootout['matches'] ?? []))->keyBy('match_key');
        foreach (['SO1' => '1stマッチ', 'SO2' => '2ndマッチ', 'SO3' => '優勝決定戦'] as $matchKey => $label) {
            $match = (array) ($matches->get($matchKey) ?? []);
            if (($match['is_tied'] ?? false) === true) {
                throw new \InvalidArgumentException($label . ' が同点です。勝者を確定できるスコアに修正してください。');
            }
            if (($match['is_complete'] ?? false) !== true) {
                throw new \InvalidArgumentException($label . ' が未確定です。先にスコアを入力してください。');
            }
        }

        $standings = $shootoutService->buildFinalStandings($shootout);
        if (count($standings) < 8) {
            throw new \InvalidArgumentException('シュートアウト最終順位を8名分作成できませんでした。入力内容を確認してください。');
        }

        $baseRows = $this->loadSnapshotRowsAsArrays((int) $seedSnapshot->id);
        $baseRowsByIdentity = [];
        foreach ($baseRows as $row) {
            $key = $this->identityKeyFromSnapshotArray($row);
            if ($key !== '' && !isset($baseRowsByIdentity[$key])) {
                $baseRowsByIdentity[$key] = $row;
            }
        }

        $maxShootoutGames = max(array_map(fn (array $standing) => (int) ($standing['shootout_games'] ?? 0), $standings));

        $finalRows = [];
        foreach ($standings as $standing) {
            $node = (array) ($standing['node'] ?? []);
            $key = $this->identityKeyFromShootoutNode($node);
            $baseRow = $key !== '' ? ($baseRowsByIdentity[$key] ?? null) : null;

            $finalRows[] = $this->makeShootoutFinalRowPayload(
                node: $node,
                baseRow: is_array($baseRow) ? $baseRow : null,
                rank: (int) ($standing['ranking'] ?? (count($finalRows) + 1)),
                shootoutPin: (int) ($standing['shootout_pin'] ?? 0),
                shootoutGames: (int) ($standing['shootout_games'] ?? 0),
                gender: $gender,
                shift: $shift,
            );
        }

        $calculationDefinition = (array) ($definition['calculation_definition'] ?? []);
        $calculationDefinition['shootout'] = [
            'format' => 'standard_8',
            'seed_source_result_code' => $seedSourceResultCode,
            'seed_snapshot_id' => (int) $seedSnapshot->id,
            'ranking_policy' => 'winner advances; losers keep source seed order within losing match',
            'match_summary' => $shootout['summary'] ?? [],
            'final_rows_count' => count($finalRows),
        ];

        $seedGames = (int) ($seedSnapshot->games_count ?? 0);

        return DB::transaction(function () use (
            $definition,
            $calculationDefinition,
            $finalRows,
            $seedSnapshot,
            $seedGames,
            $maxShootoutGames,
            $tournamentId,
            $gender,
            $shift
        ) {
            $this->closeCurrentSnapshots(
                tournamentId: $tournamentId,
                resultCode: (string) ($definition['result_code'] ?? 'shootout_final'),
                gender: $gender,
                shift: $shift,
            );

            $snapshot = TournamentResultSnapshot::create([
                'tournament_id' => $tournamentId,
                'result_code' => (string) ($definition['result_code'] ?? 'shootout_final'),
                'result_name' => (string) ($definition['result_name'] ?? 'シュートアウト最終成績'),
                'result_type' => 'shootout',
                'stage_name' => (string) ($definition['stage_name'] ?? 'シュートアウト'),
                'gender' => $gender,
                'shift' => $shift,
                'games_count' => $seedGames + $maxShootoutGames,
                'carry_game_count' => $seedGames,
                'carry_stage_names' => [(string) ($seedSnapshot->result_name ?? '進出元成績')],
                'calculation_definition' => $calculationDefinition,
                'reflected_at' => Carbon::now(),
                'reflected_by' => isset($definition['reflected_by']) && $definition['reflected_by'] !== '' ? (int) $definition['reflected_by'] : null,
                'is_final' => true,
                'is_published' => (bool) ($definition['is_published'] ?? false),
                'is_current' => true,
                'notes' => $definition['notes'] ?? null,
            ]);

            foreach ($finalRows as $row) {
                TournamentResultSnapshotRow::create(array_merge($row, [
                    'snapshot_id' => $snapshot->id,
                ]));
            }

            $syncedToTournamentResults = $this->syncFinalSnapshotToTournamentResults($snapshot);

            $snapshot = $snapshot->load(['rows', 'tournament', 'reflectedBy']);
            $snapshot->setAttribute('synced_to_tournament_results', $syncedToTournamentResults);

            return $snapshot;
        });
    }

    private function makeShootoutFinalRowPayload(
        array $node,
        ?array $baseRow,
        int $rank,
        int $shootoutPin,
        int $shootoutGames,
        ?string $gender,
        ?string $shift
    ): array {
        $proBowlerId = isset($node['pro_bowler_id']) ? (int) $node['pro_bowler_id'] : 0;
        if ($proBowlerId <= 0 && $baseRow && isset($baseRow['pro_bowler_id'])) {
            $proBowlerId = (int) $baseRow['pro_bowler_id'];
        }

        $licenseNo = $this->normalizeText($node['pro_bowler_license_no'] ?? null)
            ?? ($baseRow ? $this->normalizeText($baseRow['pro_bowler_license_no'] ?? null) : null);

        $displayName = trim((string) ($node['display_name'] ?? ''));
        if ($displayName === '' && $baseRow) {
            $displayName = trim((string) ($baseRow['display_name'] ?? $baseRow['amateur_name'] ?? ''));
        }
        if ($displayName === '') {
            $displayName = $licenseNo ?? 'unknown';
        }

        $carryPin = (int) ($baseRow['total_pin'] ?? ($node['total_pin'] ?? 0));
        $carryGames = (int) ($baseRow['games'] ?? ($node['games'] ?? 0));
        $games = $carryGames + max(0, $shootoutGames);
        $totalPin = $carryPin + max(0, $shootoutPin);
        $average = $games > 0 ? round($totalPin / $games, 2) : null;

        return [
            'ranking' => $rank,
            'pro_bowler_id' => $proBowlerId > 0 ? $proBowlerId : null,
            'pro_bowler_license_no' => $licenseNo,
            'amateur_name' => $proBowlerId > 0 ? null : $displayName,
            'display_name' => $displayName,
            'gender' => $gender ?? ($baseRow ? $this->normalizeGender($baseRow['gender'] ?? null) : null),
            'shift' => $shift ?? ($baseRow ? $this->normalizeText($baseRow['shift'] ?? null) : null),
            'entry_number' => $baseRow['entry_number'] ?? null,
            'scratch_pin' => max(0, $shootoutPin),
            'carry_pin' => $carryPin,
            'total_pin' => $totalPin,
            'games' => $games,
            'average' => $average,
            'tie_break_value' => (float) (100000 - $rank),
            'points' => null,
            'prize_money' => null,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildShootoutSeedEntriesFromSnapshot(int $snapshotId, int $qualifierCount): array
    {
        $rows = DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $snapshotId)
            ->orderBy('ranking')
            ->orderByDesc('total_pin')
            ->orderBy('id')
            ->limit($qualifierCount)
            ->get();

        $entries = [];
        foreach ($rows as $index => $row) {
            $seed = $index + 1;
            $displayName = trim((string) ($row->display_name ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($row->amateur_name ?? ''));
            }
            if ($displayName === '') {
                $displayName = trim((string) ($row->pro_bowler_license_no ?? ('seed' . $seed)));
            }

            $entries[] = [
                'seed' => $seed,
                'display_name' => $displayName,
                'pro_bowler_id' => $row->pro_bowler_id ?? null,
                'pro_bowler_license_no' => $row->pro_bowler_license_no ?? null,
                'amateur_name' => $row->amateur_name ?? null,
                'source_row_id' => $row->id ?? null,
                'participant_key' => $this->shootoutParticipantKeyFromSnapshotRow($row, $seed),
                'source_ranking' => $row->ranking ?? null,
                'total_pin' => $row->total_pin ?? null,
                'games' => $row->games ?? null,
                'average' => $row->average ?? null,
            ];
        }

        return $entries;
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function loadShootoutMatchScores(int $tournamentId): array
    {
        $rows = DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'シュートアウト')
            ->where('entry_number', 'like', 'SO:%')
            ->orderBy('game_number')
            ->orderBy('entry_number')
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $entryNumber = trim((string) ($row->entry_number ?? ''));
            if (!preg_match('/^SO:(SO[123]):([ABCD])$/', $entryNumber, $m)) {
                continue;
            }

            $scores[$m[1]][$m[2]] = [
                'score' => (int) ($row->score ?? 0),
                'row_id' => (int) ($row->id ?? 0),
                'license_number' => $row->license_number ?? null,
                'name' => $row->name ?? null,
                'pro_bowler_id' => $row->pro_bowler_id ?? null,
            ];
        }

        return $scores;
    }

    private function shootoutParticipantKeyFromSnapshotRow(object $row, int $seed): string
    {
        $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            return 'pro_bowler:' . $proBowlerId;
        }

        $license = trim((string) ($row->pro_bowler_license_no ?? ''));
        if ($license !== '') {
            return 'license:' . strtoupper($license);
        }

        $displayName = $this->normalizeNameForMatch($row->display_name ?? $row->amateur_name ?? null);
        if ($displayName !== '') {
            return 'name:' . $displayName;
        }

        return 'seed:' . $seed;
    }

    private function identityKeyFromShootoutNode(array $node): string
    {
        $proBowlerId = isset($node['pro_bowler_id']) ? (int) $node['pro_bowler_id'] : 0;
        $licenseNo = $this->normalizeText($node['pro_bowler_license_no'] ?? null);
        $displayName = $this->normalizeText($node['display_name'] ?? null)
            ?? $this->normalizeText($node['amateur_name'] ?? null);

        return $this->identityKeyFromValues($proBowlerId, $licenseNo, $displayName);
    }

    private function defaultShootoutSeedSourceResultCode(string $flowType): string
    {
        return match ($flowType) {
            'prelim_to_quarterfinal_to_shootout_to_final' => 'quarterfinal_total',
            'prelim_to_semifinal_to_shootout_to_final' => 'semifinal_total',
            default => 'prelim_total',
        };
    }

    private function shootoutSeedSourceLabel(string $resultCode): string
    {
        return match ($resultCode) {
            'quarterfinal_total' => '準々決勝通算成績',
            'semifinal_total' => '準決勝通算成績',
            default => '予選通算成績',
        };
    }

    /**
     * @param array<int,array<string,mixed>> $standings
     * @return array<int,array<string,mixed>>
     */
    private function buildStepLadderFinalSnapshotRows(
        int $tournamentId,
        ?string $gender,
        ?string $shift,
        array $standings,
        array $semifinal,
        array $final,
        array $meta
    ): array {
        $seedSnapshotId = isset($meta['seed_snapshot_id']) ? (int) $meta['seed_snapshot_id'] : 0;
        $roundRobinSnapshot = $seedSnapshotId > 0
            ? DB::table('tournament_result_snapshots')->where('id', $seedSnapshotId)->first()
            : null;

        if (!$roundRobinSnapshot) {
            $roundRobinSnapshot = $this->findCurrentSnapshotByCode($tournamentId, 'round_robin_total', $gender, $shift);
        }

        $roundRobinRows = $roundRobinSnapshot
            ? $this->loadSnapshotRowsAsArrays((int) $roundRobinSnapshot->id)
            : [];

        $baseSnapshot = $this->findStepLadderBaseSnapshot($tournamentId, $gender, $shift);
        $baseRows = $baseSnapshot
            ? $this->loadSnapshotRowsAsArrays((int) $baseSnapshot->id)
            : [];

        $lookupRows = [];
        foreach (array_merge($roundRobinRows, $baseRows) as $row) {
            $key = $this->identityKeyFromSnapshotArray($row);
            if ($key !== '' && !isset($lookupRows[$key])) {
                $lookupRows[$key] = $row;
            }
        }

        $usedKeys = [];
        $finalRows = [];
        $nextRank = 1;

        foreach ($standings as $standing) {
            if ($nextRank > 3) {
                break;
            }

            $player = (array) ($standing['player'] ?? []);
            $key = $this->identityKeyFromStepLadderPlayer($player);
            $baseRow = $key !== '' ? ($lookupRows[$key] ?? null) : null;
            $stepScore = $this->resolveStepLadderPinAndGamesForPlayer($player, $semifinal, $final);

            $finalRows[] = $this->makeStepLadderPodiumRowPayload(
                player: $player,
                baseRow: is_array($baseRow) ? $baseRow : null,
                rank: $nextRank,
                stepPin: $stepScore['pin'],
                stepGames: $stepScore['games'],
                gender: $gender,
                shift: $shift,
            );

            if ($key !== '') {
                $usedKeys[$key] = true;
            }
            if (is_array($baseRow)) {
                $baseKey = $this->identityKeyFromSnapshotArray($baseRow);
                if ($baseKey !== '') {
                    $usedKeys[$baseKey] = true;
                }
            }

            $nextRank++;
        }

        foreach ($roundRobinRows as $row) {
            $key = $this->identityKeyFromSnapshotArray($row);
            if ($key !== '' && isset($usedKeys[$key])) {
                continue;
            }

            $finalRows[] = $this->makeSnapshotCarryForwardRowPayload($row, $nextRank, $gender, $shift);
            if ($key !== '') {
                $usedKeys[$key] = true;
            }
            $nextRank++;
        }

        foreach ($baseRows as $row) {
            $key = $this->identityKeyFromSnapshotArray($row);
            if ($key !== '' && isset($usedKeys[$key])) {
                continue;
            }

            $finalRows[] = $this->makeSnapshotCarryForwardRowPayload($row, $nextRank, $gender, $shift);
            if ($key !== '') {
                $usedKeys[$key] = true;
            }
            $nextRank++;
        }

        return $finalRows;
    }

    private function findStepLadderBaseSnapshot(int $tournamentId, ?string $gender, ?string $shift): ?object
    {
        $flowType = trim((string) DB::table('tournaments')->where('id', $tournamentId)->value('result_flow_type'));

        $codes = $flowType === 'prelim_to_quarterfinal_to_rr_to_final'
            ? ['quarterfinal_total', 'prelim_total', 'semifinal_total', 'round_robin_total']
            : ['prelim_total', 'quarterfinal_total', 'semifinal_total', 'round_robin_total'];

        foreach (array_values(array_unique($codes)) as $code) {
            $snapshot = $this->findCurrentSnapshotByCode($tournamentId, $code, $gender, $shift);
            if ($snapshot) {
                return $snapshot;
            }
        }

        return null;
    }

    private function findCurrentSnapshotByCode(int $tournamentId, string $resultCode, ?string $gender, ?string $shift): ?object
    {
        $candidates = [
            ['gender' => $gender, 'shift' => $shift],
        ];

        if ($gender !== null || $shift !== null) {
            $candidates[] = ['gender' => null, 'shift' => null];
        }

        foreach ($candidates as $candidate) {
            $query = DB::table('tournament_result_snapshots')
                ->where('tournament_id', $tournamentId)
                ->where('result_code', $resultCode)
                ->where('is_current', true);

            if ($candidate['gender'] === null) {
                $query->whereNull('gender');
            } else {
                $query->where('gender', $candidate['gender']);
            }

            if ($candidate['shift'] === null) {
                $query->whereNull('shift');
            } else {
                $query->where('shift', $candidate['shift']);
            }

            $snapshot = $query->orderByDesc('reflected_at')->orderByDesc('id')->first();
            if ($snapshot) {
                return $snapshot;
            }
        }

        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadSnapshotRowsAsArrays(int $snapshotId): array
    {
        return DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $snapshotId)
            ->orderBy('ranking')
            ->orderBy('id')
            ->get([
                'ranking',
                'pro_bowler_id',
                'pro_bowler_license_no',
                'amateur_name',
                'display_name',
                'gender',
                'shift',
                'entry_number',
                'scratch_pin',
                'carry_pin',
                'total_pin',
                'games',
                'average',
                'tie_break_value',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function makeStepLadderPodiumRowPayload(
        array $player,
        ?array $baseRow,
        int $rank,
        ?int $stepPin,
        int $stepGames,
        ?string $gender,
        ?string $shift
    ): array {
        $proBowlerId = isset($player['pro_bowler_id']) ? (int) $player['pro_bowler_id'] : 0;
        if ($proBowlerId <= 0 && $baseRow && isset($baseRow['pro_bowler_id'])) {
            $proBowlerId = (int) $baseRow['pro_bowler_id'];
        }

        $licenseNo = $this->normalizeText($player['license_no'] ?? null)
            ?? ($baseRow ? $this->normalizeText($baseRow['pro_bowler_license_no'] ?? null) : null);

        $displayName = trim((string) ($player['display_name'] ?? ''));
        if ($displayName === '' && $baseRow) {
            $displayName = trim((string) ($baseRow['display_name'] ?? $baseRow['amateur_name'] ?? ''));
        }
        if ($displayName === '') {
            $displayName = $licenseNo ?? 'unknown';
        }

        $carryPin = (int) ($baseRow['total_pin'] ?? 0);
        $carryGames = (int) ($baseRow['games'] ?? 0);
        $scratchPin = $stepPin ?? 0;
        $games = $carryGames + max(0, $stepGames);
        $totalPin = $carryPin + $scratchPin;
        $average = $games > 0 ? round($totalPin / $games, 2) : null;

        return [
            'ranking' => $rank,
            'pro_bowler_id' => $proBowlerId > 0 ? $proBowlerId : null,
            'pro_bowler_license_no' => $licenseNo,
            'amateur_name' => $proBowlerId > 0 ? null : $displayName,
            'display_name' => $displayName,
            'gender' => $gender ?? ($baseRow ? $this->normalizeGender($baseRow['gender'] ?? null) : null),
            'shift' => $shift ?? ($baseRow ? $this->normalizeText($baseRow['shift'] ?? null) : null),
            'entry_number' => $baseRow['entry_number'] ?? null,
            'scratch_pin' => $scratchPin,
            'carry_pin' => $carryPin,
            'total_pin' => $totalPin,
            'games' => $games,
            'average' => $average,
            'tie_break_value' => (float) (100000 - $rank),
            'points' => null,
            'prize_money' => null,
        ];
    }

    private function makeSnapshotCarryForwardRowPayload(array $row, int $rank, ?string $gender, ?string $shift): array
    {
        $displayName = $this->normalizeText($row['display_name'] ?? null)
            ?? $this->normalizeText($row['amateur_name'] ?? null)
            ?? $this->normalizeText($row['pro_bowler_license_no'] ?? null)
            ?? 'unknown';

        $proBowlerId = isset($row['pro_bowler_id']) && $row['pro_bowler_id'] !== null
            ? (int) $row['pro_bowler_id']
            : null;

        return [
            'ranking' => $rank,
            'pro_bowler_id' => $proBowlerId && $proBowlerId > 0 ? $proBowlerId : null,
            'pro_bowler_license_no' => $this->normalizeText($row['pro_bowler_license_no'] ?? null),
            'amateur_name' => $proBowlerId ? null : ($this->normalizeText($row['amateur_name'] ?? null) ?? $displayName),
            'display_name' => $displayName,
            'gender' => $gender ?? $this->normalizeGender($row['gender'] ?? null),
            'shift' => $shift ?? $this->normalizeText($row['shift'] ?? null),
            'entry_number' => $row['entry_number'] ?? null,
            'scratch_pin' => (int) ($row['scratch_pin'] ?? 0),
            'carry_pin' => (int) ($row['carry_pin'] ?? 0),
            'total_pin' => (int) ($row['total_pin'] ?? 0),
            'games' => (int) ($row['games'] ?? 0),
            'average' => isset($row['average']) && $row['average'] !== null ? (float) $row['average'] : null,
            'tie_break_value' => (float) (100000 - $rank),
            'points' => null,
            'prize_money' => null,
        ];
    }

    private function identityKeyFromStepLadderPlayer(array $player): string
    {
        $proBowlerId = isset($player['pro_bowler_id']) ? (int) $player['pro_bowler_id'] : 0;
        $licenseNo = $this->normalizeText($player['license_no'] ?? null);
        $displayName = $this->normalizeText($player['display_name'] ?? null);

        return $this->identityKeyFromValues($proBowlerId, $licenseNo, $displayName);
    }

    private function identityKeyFromSnapshotArray(array $row): string
    {
        $proBowlerId = isset($row['pro_bowler_id']) ? (int) $row['pro_bowler_id'] : 0;
        $licenseNo = $this->normalizeText($row['pro_bowler_license_no'] ?? null);
        $displayName = $this->normalizeText($row['display_name'] ?? null)
            ?? $this->normalizeText($row['amateur_name'] ?? null);

        return $this->identityKeyFromValues($proBowlerId, $licenseNo, $displayName);
    }

    private function identityKeyFromValues(int $proBowlerId, ?string $licenseNo, ?string $displayName): string
    {
        if ($proBowlerId > 0) {
            return 'pro:' . $proBowlerId;
        }

        $digits = preg_replace('/\D+/', '', (string) $licenseNo);
        if (is_string($digits) && $digits !== '') {
            return 'lic:' . $digits;
        }

        $name = $this->normalizeNameForMatch($displayName);
        if ($name !== '') {
            return 'name:' . $name;
        }

        return '';
    }


    /**
     * @return array{pin:?int,games:int}
     */
    private function resolveStepLadderPinAndGamesForPlayer(array $player, array $semifinal, array $final): array
    {
        $pin = 0;
        $games = 0;

        if ($this->isSameStepLadderPlayer($player, (array) ($semifinal['top'] ?? [])) && isset($semifinal['top_score'])) {
            $pin += (int) $semifinal['top_score'];
            $games++;
        }

        if ($this->isSameStepLadderPlayer($player, (array) ($semifinal['bottom'] ?? [])) && isset($semifinal['bottom_score'])) {
            $pin += (int) $semifinal['bottom_score'];
            $games++;
        }

        if ($this->isSameStepLadderPlayer($player, (array) ($final['top'] ?? [])) && isset($final['top_score'])) {
            $pin += (int) $final['top_score'];
            $games++;
        }

        if ($this->isSameStepLadderPlayer($player, (array) ($final['bottom'] ?? [])) && isset($final['bottom_score'])) {
            $pin += (int) $final['bottom_score'];
            $games++;
        }

        return [
            'pin' => $games > 0 ? $pin : null,
            'games' => $games,
        ];
    }

    private function isSameStepLadderPlayer(array $a, array $b): bool
    {
        $keyA = trim((string) ($a['participant_key'] ?? ''));
        $keyB = trim((string) ($b['participant_key'] ?? ''));

        if ($keyA !== '' && $keyB !== '') {
            return $keyA === $keyB;
        }

        $licenseA = $this->extractLast4Digits($a['license_no'] ?? null);
        $licenseB = $this->extractLast4Digits($b['license_no'] ?? null);
        if ($licenseA !== '' && $licenseB !== '') {
            return $licenseA === $licenseB;
        }

        return $this->normalizeNameForMatch($a['display_name'] ?? null) !== ''
            && $this->normalizeNameForMatch($a['display_name'] ?? null) === $this->normalizeNameForMatch($b['display_name'] ?? null);
    }

    private function syncFinalSnapshotToTournamentResults(TournamentResultSnapshot $snapshot): bool
    {
        if ($snapshot->gender !== null || $snapshot->shift !== null) {
            return false;
        }

        $tournament = Tournament::query()->find($snapshot->tournament_id);
        if (!$tournament) {
            return false;
        }

        $rankingYear = $this->resolveRankingYear($tournament);
        $pointMap = $this->loadPointMap((int) $snapshot->tournament_id);
        $prizeMap = $this->loadPrizeMap((int) $snapshot->tournament_id);

        TournamentResult::query()
            ->where('tournament_id', $snapshot->tournament_id)
            ->delete();

        $hasProBowlerId = Schema::hasColumn('tournament_results', 'pro_bowler_id');
        $hasLicenseNo = Schema::hasColumn('tournament_results', 'pro_bowler_license_no');
        $hasPoints = Schema::hasColumn('tournament_results', 'points');
        $hasPrizeMoney = Schema::hasColumn('tournament_results', 'prize_money');
        $hasAmateurName = Schema::hasColumn('tournament_results', 'amateur_name');

        $rows = $snapshot->rows()->orderBy('ranking')->orderBy('id')->get();

        foreach ($rows as $row) {
            $resolvedBowler = $this->resolveBowlerFromSnapshotRow($row);
            $resolvedProBowlerId = $resolvedBowler?->id ?? ($row->pro_bowler_id !== null ? (int) $row->pro_bowler_id : null);
            $resolvedLicenseNo = $resolvedBowler?->license_no ?? $this->normalizeText($row->pro_bowler_license_no);
            $resolvedDisplayName = $resolvedBowler?->name_kanji
                ?? $resolvedBowler?->name_kana
                ?? $this->normalizeText($row->display_name)
                ?? $this->normalizeText($row->amateur_name);

            if ($resolvedDisplayName === null) {
                $resolvedDisplayName = '参加者' . (string) ((int) $row->ranking);
            }

            // tournament_results.pro_bowler_license_no は既存互換のため NOT NULL の環境がある。
            // アマ・ダミーなどライセンス未解決の参加者は amateur_name を正として残しつつ、
            // tournament_results への保存時だけ衝突しにくい内部キーを入れて NOT NULL 制約を満たす。
            $tournamentResultLicenseNo = $resolvedLicenseNo
                ?? $this->makeFallbackTournamentResultLicenseNo($snapshot, $row, $resolvedDisplayName);

            $points = $resolvedProBowlerId !== null ? (int) ($pointMap[(int) $row->ranking] ?? 0) : 0;
            $prizeMoney = $resolvedProBowlerId !== null ? (int) ($prizeMap[(int) $row->ranking] ?? 0) : 0;

            $payload = [
                'tournament_id' => (int) $snapshot->tournament_id,
                'ranking_year' => $rankingYear,
                'ranking' => (int) $row->ranking,
                'total_pin' => (int) $row->total_pin,
                'games' => (int) $row->games,
                'average' => $row->average !== null ? (float) $row->average : null,
            ];

            if ($hasLicenseNo) {
                $payload['pro_bowler_license_no'] = $tournamentResultLicenseNo;
            }
            if ($hasProBowlerId) {
                $payload['pro_bowler_id'] = $resolvedProBowlerId;
            }
            if ($hasPoints) {
                $payload['points'] = $points;
            }
            if ($hasPrizeMoney) {
                $payload['prize_money'] = $prizeMoney;
            }
            if ($hasAmateurName) {
                $payload['amateur_name'] = $resolvedProBowlerId === null ? $resolvedDisplayName : null;
            }

            TournamentResult::query()->create($payload);

            $row->forceFill([
                'pro_bowler_id' => $resolvedProBowlerId,
                'pro_bowler_license_no' => $resolvedLicenseNo,
                'display_name' => $resolvedDisplayName,
                'amateur_name' => $resolvedProBowlerId === null ? $resolvedDisplayName : null,
                'points' => $points,
                'prize_money' => $prizeMoney,
            ])->save();
        }

        return true;
    }

    private function makeFallbackTournamentResultLicenseNo(TournamentResultSnapshot $snapshot, object $row, ?string $displayName): string
    {
        $ranking = (int) ($row->ranking ?? 0);
        $rowId = (int) ($row->id ?? 0);
        $nameKey = $this->normalizeNameForMatch($displayName)
            ?: $this->normalizeNameForMatch($row->display_name ?? null)
            ?: $this->normalizeNameForMatch($row->amateur_name ?? null)
            ?: 'unknown';

        $hash = substr(sha1((string) $snapshot->tournament_id . '|' . (string) $ranking . '|' . (string) $rowId . '|' . $nameKey), 0, 8);

        return 'AMATEUR-' . (string) $snapshot->tournament_id . '-' . str_pad((string) $ranking, 3, '0', STR_PAD_LEFT) . '-' . $hash;
    }
    private function resolveBowlerFromSnapshotRow(object $row): ?ProBowler
    {
        if ($row->pro_bowler_id !== null) {
            $byId = ProBowler::query()->find((int) $row->pro_bowler_id);
            if ($byId) {
                return $byId;
            }
        }

        $license = $this->normalizeText($row->pro_bowler_license_no ?? null);
        if ($license === null) {
            return null;
        }

        $normalizedLicense = strtoupper(trim($license));
        $last4 = $this->extractLast4Digits($normalizedLicense);
        if ($last4 === '') {
            return null;
        }

        $exact = ProBowler::query()
            ->whereRaw('upper(license_no) = ?', [$normalizedLicense])
            ->first();
        if ($exact) {
            return $exact;
        }

        $query = ProBowler::query()
            ->whereRaw("right(regexp_replace(upper(license_no), '[^0-9]', '', 'g'), 4) = ?", [$last4]);

        $gender = strtoupper(trim((string) ($row->gender ?? '')));
        if (in_array($gender, ['M', 'F'], true)) {
            $query->whereRaw('upper(left(license_no, 1)) = ?', [$gender]);
        }

        $candidates = $query->orderBy('id')->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function resolveRankingYear(Tournament $tournament): int
    {
        if (!empty($tournament->year)) {
            return (int) $tournament->year;
        }

        if (!empty($tournament->start_date)) {
            try {
                return Carbon::parse($tournament->start_date)->year;
            } catch (\Throwable) {
                // no-op
            }
        }

        return (int) now()->year;
    }

    /**
     * @return array<int,int>
     */
    private function loadPointMap(int $tournamentId): array
    {
        return DB::table('point_distributions')
            ->where('tournament_id', $tournamentId)
            ->pluck('points', 'rank')
            ->mapWithKeys(fn ($points, $rank) => [(int) $rank => (int) $points])
            ->all();
    }

    /**
     * @return array<int,int>
     */
    private function loadPrizeMap(int $tournamentId): array
    {
        return DB::table('prize_distributions')
            ->where('tournament_id', $tournamentId)
            ->pluck('amount', 'rank')
            ->mapWithKeys(fn ($amount, $rank) => [(int) $rank => (int) $amount])
            ->all();
    }


    private function closeCurrentSnapshots(int $tournamentId, string $resultCode, ?string $gender, ?string $shift): void
    {
        TournamentResultSnapshot::query()
            ->where('tournament_id', $tournamentId)
            ->where('result_code', $resultCode)
            ->when(
                $gender !== null,
                fn ($q) => $q->where('gender', $gender),
                fn ($q) => $q->whereNull('gender')
            )
            ->when(
                $shift !== null,
                fn ($q) => $q->where('shift', $shift),
                fn ($q) => $q->whereNull('shift')
            )
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    private function buildStagePinMap(
        int $tournamentId,
        ?string $gender,
        ?string $shift,
        array $sourceSets,
        Collection $rows
    ): array {
        $result = [];
        if ($rows->isEmpty()) {
            return $result;
        }

        $allowedRangesByStage = [];
        foreach ($sourceSets as $set) {
            $stage = trim((string) ($set['stage'] ?? ''));
            $gameFrom = (int) ($set['game_from'] ?? 0);
            $gameTo = (int) ($set['game_to'] ?? 0);

            if ($stage === '' || $gameFrom <= 0 || $gameTo <= 0 || $gameTo < $gameFrom) {
                continue;
            }

            $allowedRangesByStage[$stage][] = [
                'from' => $gameFrom,
                'to' => $gameTo,
            ];
        }

        foreach ($rows as $row) {
            $result[(int) $row->id] = [
                '決勝' => null,
                'ラウンドロビン' => null,
                '準決勝' => null,
                '準々決勝' => null,
                '予選' => null,
            ];

            foreach (array_keys($allowedRangesByStage) as $allowedStage) {
                if (array_key_exists($allowedStage, $result[(int) $row->id])) {
                    $result[(int) $row->id][$allowedStage] = 0;
                }
            }
        }

        $identityIndex = $this->buildIdentityIndex($rows);

        $scoreRows = DB::table('game_scores')
            ->select([
                'stage',
                'game_number',
                'score',
                'pro_bowler_id',
                'license_number',
                'name',
            ])
            ->where('tournament_id', $tournamentId)
            ->when($gender !== null, fn ($q) => $q->where('gender', $gender))
            ->when($shift !== null, fn ($q) => $q->where('shift', $shift))
            ->whereIn('stage', array_keys($allowedRangesByStage))
            ->orderBy('stage')
            ->orderBy('game_number')
            ->orderBy('id')
            ->get();

        foreach ($scoreRows as $scoreRow) {
            $stage = trim((string) ($scoreRow->stage ?? ''));
            $gameNumber = (int) ($scoreRow->game_number ?? 0);
            $ranges = $allowedRangesByStage[$stage] ?? [];

            $matchedRange = false;
            foreach ($ranges as $range) {
                if ($gameNumber >= $range['from'] && $gameNumber <= $range['to']) {
                    $matchedRange = true;
                    break;
                }
            }

            if (!$matchedRange) {
                continue;
            }

            $rowId = $this->matchSnapshotRowId($scoreRow, $identityIndex);
            if ($rowId === null) {
                continue;
            }

            if (!array_key_exists($stage, $result[$rowId])) {
                continue;
            }

            if ($result[$rowId][$stage] === null) {
                $result[$rowId][$stage] = 0;
            }

            $result[$rowId][$stage] += (int) ($scoreRow->score ?? 0);
        }

        return $result;
    }

    private function buildIdentityIndex(Collection $rows): array
    {
        $index = [
            'by_pro_bowler_id' => [],
            'by_last4' => [],
            'by_name' => [],
        ];

        foreach ($rows as $row) {
            $rowId = (int) $row->id;

            $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
            if ($proBowlerId > 0) {
                $index['by_pro_bowler_id'][$proBowlerId][] = $rowId;
            }

            $last4 = $this->extractLast4Digits($row->pro_bowler_license_no ?? null);
            if ($last4 !== '') {
                $index['by_last4'][$last4][] = $rowId;
            }

            $normalizedName = $this->normalizeNameForMatch($row->display_name ?? null);
            if ($normalizedName !== '') {
                $index['by_name'][$normalizedName][] = $rowId;
            }
        }

        return $index;
    }

    private function matchSnapshotRowId(object $scoreRow, array $index): ?int
    {
        $proBowlerId = (int) ($scoreRow->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            $candidates = array_values(array_unique($index['by_pro_bowler_id'][$proBowlerId] ?? []));
            if (count($candidates) === 1) {
                return (int) $candidates[0];
            }
        }

        $last4 = $this->extractLast4Digits($scoreRow->license_number ?? null);
        if ($last4 !== '') {
            $candidates = array_values(array_unique($index['by_last4'][$last4] ?? []));
            if (count($candidates) === 1) {
                return (int) $candidates[0];
            }
        }

        $normalizedName = $this->normalizeNameForMatch($scoreRow->name ?? null);
        if ($normalizedName !== '') {
            $candidates = array_values(array_unique($index['by_name'][$normalizedName] ?? []));
            if (count($candidates) === 1) {
                return (int) $candidates[0];
            }
        }

        return null;
    }

    private function extractLast4Digits(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        return substr($digits, -4);
    }

    private function normalizeNameForMatch(mixed $value): string
    {
        $name = preg_replace('/\s+/u', '', trim((string) $value));
        return is_string($name) ? $name : '';
    }

    private function normalizeGender(mixed $value): ?string
    {
        $gender = trim((string) $value);
        return in_array($gender, ['M', 'F'], true) ? $gender : null;
    }

    private function normalizeText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function resolveTournament(mixed $routeValue): Tournament
    {
        if ($routeValue instanceof Tournament) {
            return $routeValue;
        }

        $value = trim((string) $routeValue);
        if ($value === '') {
            abort(404);
        }

        $query = Tournament::query();

        if (ctype_digit($value)) {
            $tournament = $query->find((int) $value);
            if ($tournament) {
                return $tournament;
            }
        }

        $tournament = $query->where('name', $value)->first();
        if ($tournament) {
            return $tournament;
        }

        abort(404);
    }

    private function resolveSnapshot(Tournament $tournament, mixed $routeValue): TournamentResultSnapshot
    {
        if ($routeValue instanceof TournamentResultSnapshot) {
            if ((int) $routeValue->tournament_id !== (int) $tournament->id) {
                abort(404);
            }
            return $routeValue;
        }

        $value = trim((string) $routeValue);
        if ($value === '' || !ctype_digit($value)) {
            abort(404);
        }

        $snapshot = TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->whereKey((int) $value)
            ->first();

        if ($snapshot) {
            return $snapshot;
        }

        abort(404);
    }
}
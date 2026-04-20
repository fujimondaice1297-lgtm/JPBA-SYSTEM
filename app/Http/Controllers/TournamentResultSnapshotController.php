<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentResultSnapshot;
use App\Services\TournamentResultSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $currentFinalSnapshot = $currentSnapshotsByCode->get('final_total');

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
        TournamentResultSnapshotService $service
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

        $snapshot = $service->createTotalPinSnapshot($preset['definition']);

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
        foreach (['予選', '準々決勝', '準決勝', '決勝'] as $stage) {
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
        $finalGames = (int) ($stageCounts['決勝'] ?? 0);

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

        if ($finalGames > 0) {
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

        return $presets;
    }

    /**
     * @param array<int,array<string,mixed>> $sourceSets
     * @param array<int,string> $descriptionLines
     * @return array<string,mixed>
     */
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
        bool $isFinal = false
    ): array {
        return [
            'preset_key' => $resultCode,
            'result_name' => $resultName,
            'description_lines' => $descriptionLines,
            'definition' => [
                'tournament_id' => $tournamentId,
                'result_code' => $resultCode,
                'result_name' => $resultName,
                'result_type' => 'total_pin',
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
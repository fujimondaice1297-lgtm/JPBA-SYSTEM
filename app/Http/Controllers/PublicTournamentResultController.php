<?php

namespace App\Http\Controllers;

use App\Models\FlashNews;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentResult;
use App\Services\RoundRobinService;
use App\Services\ScoreService;
use App\Services\TournamentResultCarryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicTournamentResultController extends Controller
{
    private const PUBLIC_STATUSES = [
        'in_progress',
        'provisional',
        'final',
        'archived',
        'completed',
    ];

    public function index(Request $request): View
    {
        $filters = [
            'year' => trim((string) $request->query('year', '')),
            'keyword' => trim((string) $request->query('keyword', '')),
        ];

        $query = $this->publishedTournamentQuery()
            ->withCount(['gameScores', 'officialResults']);

        if ($filters['year'] !== '' && ctype_digit($filters['year'])) {
            $year = (int) $filters['year'];
            $query->where(function ($subQuery) use ($year): void {
                $subQuery->where('year', $year)
                    ->orWhereYear('start_date', $year);
            });
        }

        if ($filters['keyword'] !== '') {
            $keyword = '%'.$filters['keyword'].'%';
            $query->where(function ($subQuery) use ($keyword): void {
                $subQuery->where('name', 'like', $keyword)
                    ->orWhere('venue_name', 'like', $keyword);
            });
        }

        $tournaments = $query
            ->orderByRaw('start_date desc nulls last')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $years = $this->publishedTournamentQuery()
            ->selectRaw('coalesce(year, extract(year from start_date)::int) as display_year')
            ->distinct()
            ->orderByDesc('display_year')
            ->pluck('display_year')
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();

        return view('public.tournaments.live_results', [
            'publicConfig' => config('jpba_public', []),
            'filters' => $filters,
            'tournaments' => $tournaments,
            'years' => $years,
            'externalLinks' => FlashNews::query()->latest('updated_at')->latest('id')->get(),
        ]);
    }

    public function live(
        Request $request,
        Tournament $tournament,
        ScoreService $scoreService,
        TournamentResultCarryService $carryService,
        RoundRobinService $roundRobinService
    ): View {
        $this->ensurePublished($tournament);

        $stageRows = DB::table('game_scores')
            ->select([
                'stage',
                DB::raw('MAX(game_number) AS max_game'),
                DB::raw('MAX(id) AS latest_id'),
                DB::raw('MAX(updated_at) AS last_updated_at'),
            ])
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('stage')
            ->groupBy('stage')
            ->get()
            ->filter(fn ($row) => trim((string) $row->stage) !== '' && (int) $row->max_game > 0)
            ->values();

        abort_if($stageRows->isEmpty(), 404);

        $latestStage = (string) $stageRows->sortByDesc('latest_id')->first()->stage;
        $requestedStage = trim((string) $request->query('stage', $latestStage));
        $selectedStageRow = $stageRows->first(fn ($row) => (string) $row->stage === $requestedStage)
            ?? $stageRows->first(fn ($row) => (string) $row->stage === $latestStage);
        $stage = (string) $selectedStageRow->stage;
        $maxGame = max(1, (int) $selectedStageRow->max_game);
        $uptoGame = min($maxGame, max(1, (int) $request->integer('upto_game', $maxGame)));

        $stageGameCounts = $stageRows
            ->mapWithKeys(fn ($row) => [(string) $row->stage => (int) $row->max_game])
            ->all();

        $sourceSets = $carryService->liveSourceSetsForStage(
            tournament: $tournament,
            stage: $stage,
            stageGameCounts: $stageGameCounts,
            currentUptoGame: $uptoGame
        );

        $isRoundRobinPointRanking = false;
        $roundRobinData = null;

        if ($stage === 'ラウンドロビン' && (int) $tournament->round_robin_qualifier_count >= 4) {
            $candidate = $roundRobinService->build([
                'tournament_id' => (int) $tournament->id,
                'upto_game' => $uptoGame,
            ]);

            if (empty($candidate['missing_carry_snapshot']) && ! empty($candidate['players'])) {
                $roundRobinData = $candidate;
                $isRoundRobinPointRanking = true;
            }
        }

        $rankingData = $roundRobinData
            ? $this->mapRoundRobinRankingData($roundRobinData)
            : $scoreService->getRankings([
                'tournament_id' => (int) $tournament->id,
                'stage' => $stage,
                'upto_game' => $uptoGame,
                'border_value' => null,
                'stage_settings' => $stageGameCounts,
                'enabled_stages' => array_keys($stageGameCounts),
                'source_sets' => $sourceSets,
            ]);

        $carrySourceSets = array_values(array_filter(
            $sourceSets,
            fn (array $sourceSet): bool => (string) ($sourceSet['bucket'] ?? '') === 'carry'
        ));
        $carryGameCount = array_sum(array_map(
            fn (array $sourceSet): int => max(
                0,
                (int) ($sourceSet['game_to'] ?? 0) - (int) ($sourceSet['game_from'] ?? 1) + 1
            ),
            $carrySourceSets
        ));
        $carryStageLabel = implode('＋', array_values(array_unique(array_map(
            fn (array $sourceSet): string => (string) ($sourceSet['stage'] ?? ''),
            $carrySourceSets
        ))));

        if ($roundRobinData) {
            $carryGameCount = max(
                $carryGameCount,
                (int) collect($roundRobinData['players'] ?? [])->max('carry_games')
            );
        }

        $entryLookup = $this->entryLookup($tournament);
        $keyword = trim((string) $request->query('keyword', ''));
        $rows = collect($rankingData['rows'] ?? [])
            ->map(function (array $row) use ($entryLookup, $stage, $uptoGame, $carryGameCount): array {
                $rawIds = (array) ($row['raw_ids'] ?? []);
                $row['entry'] = $this->findEntryPayload(
                    $entryLookup,
                    isset($rawIds['pro_bowler_id']) ? (int) $rawIds['pro_bowler_id'] : null,
                    (string) ($rawIds['license_number'] ?? '')
                );

                if (! array_key_exists('carry_pin', $row)) {
                    $currentStagePin = 0;
                    foreach ((array) ($row['breakdown'][$stage] ?? []) as $gameNumber => $score) {
                        if ((int) $gameNumber <= $uptoGame) {
                            $currentStagePin += (int) $score;
                        }
                    }

                    $row['carry_pin'] = max(0, (int) ($row['total'] ?? 0) - $currentStagePin);
                    $row['carry_games'] = $carryGameCount;
                }

                return $row;
            })
            ->when($keyword !== '', function (Collection $collection) use ($keyword): Collection {
                $needle = mb_strtolower($keyword);

                return $collection->filter(function (array $row) use ($needle): bool {
                    $rawIds = (array) ($row['raw_ids'] ?? []);
                    $target = mb_strtolower(implode(' ', [
                        (string) ($rawIds['name'] ?? ''),
                        (string) ($rawIds['license_number'] ?? ''),
                        (string) ($row['display_license'] ?? ''),
                    ]));

                    return str_contains($target, $needle);
                })->values();
            });

        $rankings = $this->paginateCollection($rows, $request, 100);
        $gameNumbers = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->where('stage', $stage)
            ->whereBetween('game_number', [1, $uptoGame])
            ->distinct()
            ->orderBy('game_number')
            ->pluck('game_number')
            ->map(fn ($gameNumber) => (int) $gameNumber)
            ->values()
            ->all();

        $stageOrder = array_flip(['予選', '準々決勝', '準決勝', 'ラウンドロビン', '決勝', 'シュートアウト', 'トーナメント']);
        $stageOptions = $stageRows
            ->sortBy(fn ($row) => $stageOrder[(string) $row->stage] ?? 99)
            ->values();

        return view('public.tournaments.live', [
            'publicConfig' => config('jpba_public', []),
            'tournament' => $tournament,
            'rankings' => $rankings,
            'stage' => $stage,
            'stageOptions' => $stageOptions,
            'uptoGame' => $uptoGame,
            'maxGame' => $maxGame,
            'gameNumbers' => $gameNumbers,
            'keyword' => $keyword,
            'lastUpdatedAt' => $selectedStageRow->last_updated_at,
            'carryGameCount' => $carryGameCount,
            'carryStageLabel' => $carryStageLabel,
            'isRoundRobinPointRanking' => $isRoundRobinPointRanking,
        ]);
    }

    /**
     * @param  array<string,mixed>  $roundRobinData
     * @return array{meta:array<string,mixed>,rows:array<int,array<string,mixed>>}
     */
    private function mapRoundRobinRankingData(array $roundRobinData): array
    {
        $rows = collect($roundRobinData['players'] ?? [])->map(function (array $player): array {
            $scores = (array) ($player['rr_scores'] ?? []);
            ksort($scores, SORT_NUMERIC);

            $license = trim((string) ($player['license_no'] ?? ''));
            $carryGames = (int) ($player['carry_games'] ?? 0);
            $gamesCounted = $carryGames + count($scores);
            $carryPin = (int) ($player['carry_pin'] ?? 0);
            $roundRobinPin = (int) ($player['rr_total_pin'] ?? array_sum($scores));
            $totalPin = $carryPin + $roundRobinPin;
            $bonusPoints = (int) ($player['bonus_points'] ?? 0);
            $totalPoints = $totalPin + $bonusPoints;

            return [
                'id' => (string) ($player['participant_key'] ?? ''),
                'rank' => (int) ($player['rank'] ?? 0),
                'display_license' => $license,
                'raw_ids' => [
                    'license' => preg_replace('/\D+/', '', $license) ?: '',
                    'license_number' => $license,
                    'entry' => null,
                    'name' => (string) ($player['display_name'] ?? ''),
                    'pro_bowler_id' => $player['pro_bowler_id'] ?? null,
                    'tournament_participant_id' => null,
                ],
                'breakdown' => ['ラウンドロビン' => $scores],
                'stage_totals' => ['ラウンドロビン' => $roundRobinPin],
                'carry_pin' => $carryPin,
                'carry_games' => $carryGames,
                'games_counted' => $gamesCounted,
                'total' => $totalPin,
                'round_robin' => [
                    'stage_pin' => $roundRobinPin,
                    'record' => (string) ($player['record'] ?? ''),
                    'bonus_points' => $bonusPoints,
                    'total_points' => $totalPoints,
                    'over_under_points' => $totalPoints - ($gamesCounted * 200),
                ],
            ];
        })->values()->all();

        return [
            'meta' => (array) ($roundRobinData['meta'] ?? []),
            'rows' => $rows,
        ];
    }

    public function results(Request $request, Tournament $tournament): View
    {
        $this->ensurePublished($tournament);

        abort_unless($tournament->officialResults()->exists(), 404);

        $keyword = trim((string) $request->query('keyword', ''));
        $query = TournamentResult::query()
            ->with(['player', 'bowler'])
            ->where('tournament_id', $tournament->id);

        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function ($subQuery) use ($like): void {
                $subQuery->where('amateur_name', 'like', $like)
                    ->orWhere('pro_bowler_license_no', 'like', $like)
                    ->orWhereHas('player', fn ($playerQuery) => $playerQuery->where('name_kanji', 'like', $like))
                    ->orWhereHas('bowler', fn ($bowlerQuery) => $bowlerQuery->where('name_kanji', 'like', $like));
            });
        }

        $results = $query
            ->orderByRaw('ranking asc nulls last')
            ->orderByDesc('total_pin')
            ->paginate(100)
            ->withQueryString();

        $entryLookup = $this->entryLookup($tournament);
        $results->getCollection()->transform(function (TournamentResult $result) use ($entryLookup): TournamentResult {
            $bowler = $result->player ?: $result->bowler;
            $result->setRelation('publicBowler', $bowler);
            $result->setAttribute('public_entry', $this->findEntryPayload(
                $entryLookup,
                $bowler?->id ? (int) $bowler->id : ($result->pro_bowler_id ? (int) $result->pro_bowler_id : null),
                (string) $result->pro_bowler_license_no
            ));

            return $result;
        });

        return view('public.tournaments.results', [
            'publicConfig' => config('jpba_public', []),
            'tournament' => $tournament,
            'results' => $results,
            'keyword' => $keyword,
        ]);
    }

    private function publishedTournamentQuery()
    {
        return Tournament::query()
            ->whereIn('setup_status', self::PUBLIC_STATUSES)
            ->where(function ($query): void {
                $query->whereHas('gameScores')
                    ->orWhereHas('officialResults');
            });
    }

    private function ensurePublished(Tournament $tournament): void
    {
        abort_unless(in_array((string) $tournament->setup_status, self::PUBLIC_STATUSES, true), 404);
    }

    private function entryLookup(Tournament $tournament): array
    {
        $entries = TournamentEntry::query()
            ->with(['bowler'])
            ->withCount('balls')
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry')
            ->get();

        $lookup = [];

        foreach ($entries as $entry) {
            $payload = [
                'id' => (int) $entry->id,
                'pro_bowler_id' => (int) $entry->pro_bowler_id,
                'name' => (string) ($entry->bowler?->name_kanji ?? ''),
                'license_no' => (string) ($entry->bowler?->license_no ?? ''),
                'ball_count' => (int) $entry->balls_count,
                'photo_url' => $entry->bowler?->public_photo_url,
            ];

            if ((int) $entry->pro_bowler_id > 0) {
                $lookup['pro:'.(int) $entry->pro_bowler_id] = $payload;
            }

            $licenseKey = $this->normalizeLicense((string) ($entry->bowler?->license_no ?? ''));
            if ($licenseKey !== '') {
                $lookup['license:'.$licenseKey] = $payload;
            }
        }

        return $lookup;
    }

    private function findEntryPayload(array $lookup, ?int $proBowlerId, string $licenseNo): ?array
    {
        if ($proBowlerId && isset($lookup['pro:'.$proBowlerId])) {
            return $lookup['pro:'.$proBowlerId];
        }

        $licenseKey = $this->normalizeLicense($licenseNo);

        return $licenseKey !== '' ? ($lookup['license:'.$licenseKey] ?? null) : null;
    }

    private function normalizeLicense(string $licenseNo): string
    {
        return strtoupper(preg_replace('/\s+/u', '', trim($licenseNo)) ?? '');
    }

    private function paginateCollection(Collection $rows, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}

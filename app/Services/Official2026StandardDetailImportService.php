<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ScoreImportBatch;
use App\Models\Tournament;
use App\Models\TournamentResultOutput;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultSnapshot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Official2026StandardDetailImportService
{
    private const DATASET_PATH = 'data/jpba_official_2026_standard_detail_scores.json';

    private const IMPORT_MARKER = 'jpba_official_2026_standard_detail';

    private const AGGREGATE_MARKER = 'jpba_official_2026_results';

    private const EXPECTED_EVENT_COUNT = 12;

    private const EXPECTED_SCORE_COUNT = 14595;

    public function __construct(
        private readonly Official2026TournamentResultsImportService $officialResults,
        private readonly ScoreImportOcrEngineBoundaryService $ocrBoundary,
        private readonly ScoreImportCommitService $commitService,
        private readonly TournamentResultPublicationService $publicationService,
        private readonly TournamentResultCompletenessService $completenessService,
    ) {}

    /** @return array<string,mixed> */
    public function import(bool $write = false, string $adminEmail = 'yamaguchi@jpba.or.jp'): array
    {
        $detail = $this->dataset();
        $aggregate = $this->officialResults->dataset();
        $aggregateEvents = collect($aggregate['events'])->keyBy('key');
        $admin = User::query()->where('email', $adminEmail)->first();
        $errors = [];
        $events = [];

        if ($admin === null) {
            $errors[] = "Administrator was not found: {$adminEmail}";
        }

        foreach ($detail['events'] as $event) {
            $aggregateEvent = $aggregateEvents->get($event['key']);
            $eventErrors = $this->validateEvent($event, $aggregateEvent, $aggregate);
            $tournament = $this->findTournament((string) $event['key']);
            $participantResolution = ['map' => [], 'errors' => []];

            if ($tournament === null) {
                $eventErrors[] = 'The imported tournament shell was not found.';
            } else {
                $participantResolution = $this->resolveParticipantLicenses($tournament, $event);
                $eventErrors = [
                    ...$eventErrors,
                    ...$participantResolution['errors'],
                ];
            }

            $scoreComparison = $tournament === null
                ? null
                : $this->compareExistingScores($tournament, $event, $participantResolution['map']);
            if (($scoreComparison['actual_count'] ?? 0) > 0
                && ($scoreComparison['difference_count'] ?? 0) > 0) {
                $eventErrors[] = 'Existing game scores differ from the official detail dataset.';
            }

            $eventErrors = array_values(array_unique($eventErrors));
            $events[] = [
                'key' => $event['key'],
                'tournament_id' => $tournament?->id,
                'tournament_name' => $tournament?->name,
                'expected_score_count' => (int) $event['expected_score_count'],
                'expected_player_count' => (int) $event['expected_player_count'],
                'stage_plan' => $this->stagePlan($event),
                'existing_score_comparison' => $scoreComparison,
                'errors' => $eventErrors,
            ];

            foreach ($eventErrors as $eventError) {
                $errors[] = $event['key'].': '.$eventError;
            }
        }

        $report = [
            'mode' => $write ? 'write' : 'dry-run',
            'dataset' => $detail['dataset'],
            'dataset_sha256' => hash_file('sha256', database_path(self::DATASET_PATH)),
            'source_checked_at' => $detail['source_checked_at'],
            'event_count' => count($detail['events']),
            'expected_score_count' => array_sum(array_column($detail['events'], 'expected_score_count')),
            'admin_id' => $admin?->id,
            'events' => $events,
            'errors' => $errors,
            'repaired' => [],
        ];

        if (! $write || $errors !== []) {
            return $report;
        }

        return DB::transaction(function () use ($detail, $aggregateEvents, $admin, $report): array {
            foreach ($detail['events'] as $event) {
                $tournament = $this->findTournament((string) $event['key'], true);
                if ($tournament === null) {
                    throw new RuntimeException("{$event['key']}: tournament disappeared before repair.");
                }
                $aggregateEvent = $aggregateEvents->get($event['key']);
                if (! is_array($aggregateEvent)) {
                    throw new RuntimeException("{$event['key']}: aggregate event disappeared before repair.");
                }
                $participantResolution = $this->resolveParticipantLicenses($tournament, $event);
                if ($participantResolution['errors'] !== []) {
                    throw new RuntimeException(
                        $event['key'].': '.implode(' ', $participantResolution['errors']),
                    );
                }

                $this->synchronizeTournamentAggregationFlags($tournament, $aggregateEvent);
                $this->clearGeneratedScores($tournament);
                $stageSettings = $this->synchronizeStageSettings($tournament, $event);
                $resultOutputs = $this->synchronizeResultOutputs($tournament);
                $imports = [];

                foreach ($event['stages'] as $stage) {
                    $imports[] = $this->stageAndCommitScores(
                        $tournament,
                        $event,
                        $stage,
                        $admin,
                        $participantResolution['map'],
                    );
                }

                $snapshotSummary = $this->synchronizeSnapshots(
                    $tournament,
                    $event,
                    $participantResolution['map'],
                );
                $finalSnapshot = TournamentResultSnapshot::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('is_current', true)
                    ->where('is_final', true)
                    ->orderByDesc('id')
                    ->firstOrFail();
                $preview = $this->publicationService->preview($tournament->fresh(), $finalSnapshot);
                if (! $preview['can_publish']) {
                    throw new RuntimeException(
                        $event['key'].': '.implode(' ', $preview['errors']),
                    );
                }

                $publication = $this->publicationService->publish(
                    $tournament->fresh(),
                    $finalSnapshot->fresh(),
                    (int) $admin->id,
                    (string) $preview['result_checksum'],
                    self::IMPORT_MARKER.':'.$event['key'],
                );
                $audit = $this->completenessService->audit($tournament->fresh());
                if (! $audit['is_complete']) {
                    throw new RuntimeException(
                        $event['key'].': '.implode(' ', $audit['errors']),
                    );
                }

                $report['repaired'][] = [
                    'key' => $event['key'],
                    'tournament_id' => (int) $tournament->id,
                    'stage_settings' => $stageSettings,
                    'result_outputs' => $resultOutputs,
                    'imports' => $imports,
                    'snapshots' => $snapshotSummary,
                    'publication_id' => (int) $publication->id,
                    'publication_row_count' => (int) $publication->row_count,
                    'game_score_count' => DB::table('game_scores')
                        ->where('tournament_id', $tournament->id)
                        ->count(),
                    'completeness' => $audit,
                ];
            }

            return $report;
        });
    }

    /** @param array<string,mixed> $aggregateEvent */
    private function synchronizeTournamentAggregationFlags(
        Tournament $tournament,
        array $aggregateEvent,
    ): void {
        $metadata = $aggregateEvent['tournament'] ?? [];
        $tournament->forceFill([
            'counts_for_official_points' => (bool) ($metadata['counts_for_official_points'] ?? false),
            'counts_for_average' => (bool) ($metadata['counts_for_average'] ?? false),
            'counts_for_prize' => (bool) ($metadata['counts_for_prize'] ?? false),
        ])->save();
    }

    /** @return array<string,mixed> */
    public function dataset(): array
    {
        $path = database_path(self::DATASET_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Standard-tournament detail dataset was not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (count($payload['events'] ?? []) !== self::EXPECTED_EVENT_COUNT) {
            throw new RuntimeException('Standard-tournament detail dataset must contain 12 events.');
        }

        $scoreCount = array_sum(array_map(
            fn (array $event): int => (int) ($event['expected_score_count'] ?? 0),
            $payload['events'],
        ));
        if ($scoreCount !== self::EXPECTED_SCORE_COUNT) {
            throw new RuntimeException('Standard-tournament detail dataset must contain 14,595 scores.');
        }

        return $payload;
    }

    /** @return array{map:array<string,string>,errors:array<int,string>} */
    private function validateEvent(array $event, ?array $aggregateEvent, array $aggregate): array
    {
        $errors = [];
        if ($aggregateEvent === null) {
            return ['Aggregate event is missing.'];
        }
        if (($event['tournament']['name'] ?? null) !== ($aggregateEvent['tournament']['name'] ?? null)) {
            $errors[] = 'Tournament name differs from the aggregate dataset.';
        }

        foreach ($event['sources'] as $source) {
            $hash = strtolower(trim((string) ($source['sha256'] ?? '')));
            if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                $errors[] = "Source hash is invalid: {$source['alias']}";
                continue;
            }
            if ((bool) ($source['aggregate_hash_verified'] ?? false)) {
                $expected = strtolower((string) ($aggregate['source_sha256'][$source['alias']] ?? ''));
                if ($expected === '' || ! hash_equals($expected, $hash)) {
                    $errors[] = "Source hash differs from the aggregate dataset: {$source['alias']}";
                }
            }
        }

        $scoreCount = 0;
        $identities = [];
        foreach ($event['stages'] as $stage) {
            $stageName = trim((string) ($stage['stage'] ?? ''));
            if ($stageName === '' || ($stage['rows'] ?? []) === []) {
                $errors[] = 'A score stage is empty.';
                continue;
            }

            foreach ($stage['rows'] as $row) {
                $identity = (string) ($row['identity'] ?? '');
                $identities[$identity] = true;
                $rowTotal = 0;
                $seenGames = [];
                foreach ($row['games'] ?? [] as $game) {
                    $gameNumber = (int) ($game['game_number'] ?? 0);
                    $score = (int) ($game['score'] ?? -1);
                    if ($gameNumber < 1 || isset($seenGames[$gameNumber]) || $score < 0 || $score > 300) {
                        $errors[] = "{$stageName}: invalid game row for {$identity}.";
                        break;
                    }
                    $seenGames[$gameNumber] = true;
                    $rowTotal += $score;
                    $scoreCount++;
                }
                if ($rowTotal !== (int) ($row['stage_total_pin'] ?? -1)
                    || count($seenGames) !== (int) ($row['games_count'] ?? -1)) {
                    $errors[] = "{$stageName}: per-game total differs for {$identity}.";
                }
            }
        }

        if ($scoreCount !== (int) ($event['expected_score_count'] ?? -1)) {
            $errors[] = 'Expected score count differs from the reconstructed rows.';
        }
        if (count($identities) !== (int) ($event['expected_player_count'] ?? -1)) {
            $errors[] = 'Expected player count differs from the reconstructed rows.';
        }

        return array_values(array_unique($errors));
    }

    private function findTournament(string $eventKey, bool $lock = false): ?Tournament
    {
        $publication = TournamentResultPublication::query()
            ->where('status', TournamentResultPublication::STATUS_CURRENT)
            ->where('notes', self::AGGREGATE_MARKER.':'.$eventKey)
            ->orderByDesc('revision')
            ->first();

        if ($publication === null) {
            return null;
        }

        $query = Tournament::query()->whereKey($publication->tournament_id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @return array<int,string> */
    private function resolveParticipantLicenses(Tournament $tournament, array $event): array
    {
        $errors = [];
        $map = [];
        $participants = DB::table('tournament_participants')
            ->where('tournament_id', $tournament->id)
            ->select([
                'id',
                'participant_type',
                'pro_bowler_license_no',
                'display_name',
            ])
            ->get();
        $participantsByLicense = $participants->groupBy(
            fn (object $participant): string => $this->normalizeLicense(
                (string) $participant->pro_bowler_license_no,
            ),
        );
        $amateursByName = $participants
            ->filter(fn (object $participant): bool => (string) $participant->participant_type === 'amateur')
            ->groupBy(fn (object $participant): string => $this->normalizeName((string) $participant->display_name));

        $detailRows = [];
        foreach ($event['stages'] as $stage) {
            foreach ($stage['rows'] as $row) {
                $detailRows[$this->normalizeLicense((string) $row['license_no'])] ??= $row;
            }
        }
        $missing = [];
        $ambiguous = [];
        foreach ($detailRows as $sourceLicense => $row) {
            $candidates = (bool) $row['is_amateur']
                ? $amateursByName->get($this->normalizeName((string) $row['display_name']), collect())
                : $participantsByLicense->get($sourceLicense, collect());
            if ($candidates->count() === 1) {
                $map[$sourceLicense] = $this->normalizeLicense(
                    (string) $candidates->first()->pro_bowler_license_no,
                );
            } elseif ($candidates->isEmpty()) {
                $missing[] = $sourceLicense.' '.$row['display_name'];
            } else {
                $ambiguous[] = $sourceLicense.' '.$row['display_name'];
            }
        }
        if ($missing !== []) {
            $errors[] = 'Tournament participants are missing: '.implode(', ', array_slice($missing, 0, 10));
        }
        if ($ambiguous !== []) {
            $errors[] = 'Tournament participant names are ambiguous: '.implode(', ', array_slice($ambiguous, 0, 10));
        }

        $professionalLicenses = [];
        foreach ($event['stages'] as $stage) {
            foreach ($stage['rows'] as $row) {
                if (! (bool) $row['is_amateur']) {
                    $professionalLicenses[$this->normalizeLicense((string) $row['license_no'])] = true;
                }
            }
        }
        $bowlerLicenses = ProBowler::query()
            ->whereIn('license_no', array_keys($professionalLicenses))
            ->pluck('license_no')
            ->map(fn ($license): string => $this->normalizeLicense((string) $license))
            ->flip();
        $missingBowlers = array_values(array_filter(
            array_keys($professionalLicenses),
            fn (string $license): bool => ! $bowlerLicenses->has($license),
        ));
        if ($missingBowlers !== []) {
            $errors[] = 'Professional licenses are missing: '.implode(', ', array_slice($missingBowlers, 0, 10));
        }

        return ['map' => $map, 'errors' => $errors];
    }

    /** @return array<int,array<string,mixed>> */
    private function stagePlan(array $event): array
    {
        $plan = [];
        $carryGames = 0;
        foreach ($event['stages'] as $stage) {
            $games = $this->maximumStageGames($stage);
            $plan[] = [
                'stage' => $stage['stage'],
                'player_count' => count($stage['rows']),
                'stage_game_count' => $games,
                'carry_game_count' => $carryGames,
                'cumulative_game_count' => $carryGames + $games,
            ];
            $carryGames += $games;
        }

        return $plan;
    }

    /** @return array<string,mixed> */
    private function compareExistingScores(
        Tournament $tournament,
        array $event,
        array $participantLicenseMap,
    ): array
    {
        $expected = $this->expectedScoreMap($event, $participantLicenseMap);
        $actualRows = DB::table('game_scores')->where('tournament_id', $tournament->id)->get();
        $actual = [];
        foreach ($actualRows as $row) {
            $key = $this->scoreKey(
                (string) $row->stage,
                (string) $row->license_number,
                (int) $row->game_number,
            );
            $actual[$key][] = (int) $row->score;
        }

        $differences = [];
        foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $key) {
            $expectedValues = $expected[$key] ?? [];
            $actualValues = $actual[$key] ?? [];
            sort($expectedValues);
            sort($actualValues);
            if ($expectedValues !== $actualValues) {
                $differences[] = [
                    'key' => $key,
                    'expected' => $expectedValues,
                    'actual' => $actualValues,
                ];
            }
        }

        return [
            'expected_count' => (int) $event['expected_score_count'],
            'actual_count' => $actualRows->count(),
            'difference_count' => count($differences),
            'difference_samples' => array_slice($differences, 0, 10),
        ];
    }

    /** @return array<string,array<int,int>> */
    private function expectedScoreMap(array $event, array $participantLicenseMap): array
    {
        $scores = [];
        foreach ($event['stages'] as $stage) {
            foreach ($stage['rows'] as $row) {
                $sourceLicense = $this->normalizeLicense((string) $row['license_no']);
                $license = $participantLicenseMap[$sourceLicense] ?? $sourceLicense;
                foreach ($row['games'] as $game) {
                    $key = $this->scoreKey(
                        (string) $stage['stage'],
                        $license,
                        (int) $game['game_number'],
                    );
                    $scores[$key][] = (int) $game['score'];
                }
            }
        }

        return $scores;
    }

    private function scoreKey(string $stage, string $license, int $game): string
    {
        return implode('|', [
            trim($stage),
            $this->normalizeLicense($license),
            $game,
        ]);
    }

    private function clearGeneratedScores(Tournament $tournament): void
    {
        ScoreImportBatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('notes', 'like', '%'.self::IMPORT_MARKER.'%')
            ->delete();
        DB::table('game_scores')->where('tournament_id', $tournament->id)->delete();
    }

    /** @return array<int,array<string,mixed>> */
    private function synchronizeStageSettings(Tournament $tournament, array $event): array
    {
        $summary = [];
        foreach ($event['stages'] as $stage) {
            $games = $this->maximumStageGames($stage);
            $row = DB::table('stage_settings')
                ->where('tournament_id', $tournament->id)
                ->where('stage', $stage['stage'])
                ->first();
            $created = $row === null;

            if ($created) {
                DB::table('stage_settings')->insert([
                    'tournament_id' => $tournament->id,
                    'stage' => $stage['stage'],
                    'total_games' => $games,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('stage_settings')->where('id', $row->id)->update([
                    'total_games' => $games,
                    'enabled' => true,
                    'updated_at' => now(),
                ]);
            }

            $summary[] = [
                'stage' => $stage['stage'],
                'total_games' => $games,
                'created' => $created,
            ];
        }

        $tournament->forceFill(['setup_status' => 'completed'])->save();

        return $summary;
    }

    /** @return array<int,array<string,string>> */
    private function synchronizeResultOutputs(Tournament $tournament): array
    {
        $outputs = [
            ['output_type' => 'result', 'output_scope' => 'official'],
        ];
        if ((bool) $tournament->counts_for_official_points) {
            $outputs[] = ['output_type' => 'points', 'output_scope' => 'official'];
        }
        if ((bool) $tournament->counts_for_average) {
            $outputs[] = ['output_type' => 'average', 'output_scope' => 'official'];
        }
        if ((bool) $tournament->counts_for_prize) {
            $outputs[] = ['output_type' => 'prize', 'output_scope' => 'official'];
        }
        if (in_array((string) $tournament->title_scope, ['official', 'season_trial'], true)) {
            $outputs[] = [
                'output_type' => 'title',
                'output_scope' => (string) $tournament->title_scope,
            ];
        }

        foreach ($outputs as $output) {
            TournamentResultOutput::query()->updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'output_type' => $output['output_type'],
                    'output_scope' => $output['output_scope'],
                ],
                [
                    'distribution_pattern_id' => null,
                    'settings' => ['source' => self::IMPORT_MARKER],
                    'is_active' => true,
                ],
            );
        }

        return $outputs;
    }

    /** @return array<string,mixed> */
    private function stageAndCommitScores(
        Tournament $tournament,
        array $event,
        array $stage,
        User $admin,
        array $participantLicenseMap,
    ): array {
        $sourceAliases = collect($stage['rows'])
            ->flatMap(fn (array $row): array => $row['source_aliases'] ?? [])
            ->unique()
            ->values()
            ->all();
        $sourceFilename = implode(', ', $sourceAliases);
        $stageName = (string) $stage['stage'];
        $gender = (string) $event['tournament']['gender'];
        $batch = ScoreImportBatch::query()->create([
            'tournament_id' => $tournament->id,
            'import_type' => 'score_sheet_image',
            'source_filename' => $sourceFilename,
            'status' => 'draft',
            'imported_by' => $admin->id,
            'notes' => "default_stage={$stageName} / default_gender={$gender} / "
                .self::IMPORT_MARKER.':'.$event['key'],
        ]);

        $payloadRows = [];
        foreach ($stage['rows'] as $row) {
            $sourceLicense = $this->normalizeLicense((string) $row['license_no']);
            $license = $participantLicenseMap[$sourceLicense] ?? null;
            if ($license === null) {
                throw new RuntimeException(
                    "{$event['key']}: participant mapping is missing: {$row['display_name']}",
                );
            }
            $payloadRows[] = [
                'license_number' => $license,
                'name' => $row['display_name'],
                'stage' => $stageName,
                'gender' => $gender,
                'games' => array_map(
                    fn (array $game): array => [
                        'game_number' => (int) $game['game_number'],
                        'score' => (int) $game['score'],
                        'confidence' => 100,
                    ],
                    $row['games'],
                ),
                'confidence' => 100,
            ];
        }

        $engineText = json_encode(
            ['rows' => $payloadRows],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $staged = $this->ocrBoundary->stageTextResult($tournament, $batch, $engineText, $admin, [
            'default_stage' => $stageName,
            'default_gender' => $gender,
            'engine_name' => 'official_pdf_table_fixture',
            'replace_existing' => true,
            'source_filename' => $sourceFilename,
            'operation_action' => 'official_pdf_table_stage',
            'operation_message' => 'JPBA公式PDFの表をゲーム単位の確定取込データへ変換しました。',
        ]);
        $committed = $this->commitService->commit($tournament, $batch->fresh(), $admin);
        if (($staged['import_summary']['needs_review'] ?? 0) > 0 || ($committed['skipped'] ?? 0) > 0) {
            $issues = $batch->rows()
                ->where('parse_status', 'needs_review')
                ->selectRaw('error_message, COUNT(*) AS row_count')
                ->groupBy('error_message')
                ->orderByDesc('row_count')
                ->get()
                ->map(fn ($row): array => [
                    'error_message' => $row->error_message,
                    'row_count' => (int) $row->row_count,
                ])
                ->all();
            $samples = $batch->rows()
                ->where('parse_status', 'needs_review')
                ->orderBy('row_number')
                ->limit(5)
                ->get([
                    'row_number',
                    'license_number',
                    'name',
                    'stage',
                    'game_number',
                    'score',
                    'tournament_participant_id',
                    'pro_bowler_id',
                    'error_message',
                ])
                ->toArray();

            throw new RuntimeException(sprintf(
                '%s %s: score import contains unresolved rows. staged=%s committed=%s issues=%s samples=%s',
                $event['key'],
                $stageName,
                json_encode($staged['import_summary'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($committed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($samples, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ));
        }

        return [
            'batch_id' => (int) $batch->id,
            'stage' => $stageName,
            'payload_player_count' => count($payloadRows),
            'staged' => $staged['import_summary'],
            'committed' => $committed,
        ];
    }

    /** @return array<string,mixed> */
    private function synchronizeSnapshots(
        Tournament $tournament,
        array $event,
        array $participantLicenseMap,
    ): array
    {
        $snapshots = TournamentResultSnapshot::query()
            ->with('rows')
            ->where('tournament_id', $tournament->id)
            ->where('is_current', true)
            ->get();
        if ($snapshots->isEmpty()) {
            throw new RuntimeException("{$event['key']}: result snapshots are missing.");
        }

        $stages = collect($event['stages'])->values();
        $stageNames = $stages->pluck('stage')->all();
        $stageMaps = $stages->mapWithKeys(fn (array $stage): array => [
            $stage['stage'] => collect($stage['rows'])->keyBy(
                fn (array $row): string => $participantLicenseMap[
                    $this->normalizeLicense((string) $row['license_no'])
                ] ?? $this->normalizeLicense((string) $row['license_no']),
            ),
        ]);
        $nameMap = $this->uniqueNameLicenseMap($event, $participantLicenseMap);
        $summary = [];

        foreach ($snapshots as $snapshot) {
            $targetIndex = $this->snapshotTargetStageIndex($snapshot, $stageNames);
            $includedStageNames = array_slice($stageNames, 0, $targetIndex + 1);
            $carryStageNames = array_slice($includedStageNames, 0, -1);
            $gamesCount = array_sum(array_map(
                fn (string $stageName): int => $this->maximumStageGames(
                    $stages->firstWhere('stage', $stageName),
                ),
                $includedStageNames,
            ));
            $carryGameCount = array_sum(array_map(
                fn (string $stageName): int => $this->maximumStageGames(
                    $stages->firstWhere('stage', $stageName),
                ),
                $carryStageNames,
            ));
            $definition = is_array($snapshot->calculation_definition)
                ? $snapshot->calculation_definition
                : [];
            $definition['detail_source'] = self::IMPORT_MARKER;
            $definition['detail_event_key'] = $event['key'];
            $snapshot->forceFill([
                'stage_name' => $stageNames[$targetIndex],
                'games_count' => $gamesCount,
                'carry_game_count' => $carryGameCount,
                'carry_stage_names' => $carryStageNames,
                'calculation_definition' => $definition,
                'notes' => $this->appendMarker((string) $snapshot->notes),
            ])->save();

            foreach ($snapshot->rows as $row) {
                $license = $this->normalizeLicense((string) $row->pro_bowler_license_no);
                if ($license === '' || ! $this->licenseExistsInMaps($license, $stageMaps)) {
                    $license = $nameMap[$this->normalizeName((string) $row->display_name)] ?? '';
                }
                if ($license === '') {
                    throw new RuntimeException(
                        "{$event['key']}: snapshot row cannot be matched: {$row->display_name}",
                    );
                }

                $stageDetails = [];
                $scratchPin = 0;
                $carryPin = 0;
                $totalPin = 0;
                $games = 0;
                foreach ($includedStageNames as $index => $stageName) {
                    $source = $stageMaps->get($stageName)?->get($license);
                    if ($source === null) {
                        continue;
                    }
                    $pin = (int) $source['stage_total_pin'];
                    $stageGames = (int) $source['games_count'];
                    if ($index < count($includedStageNames) - 1) {
                        $carryPin += $pin;
                    } else {
                        $scratchPin = $pin;
                    }
                    $totalPin += $pin;
                    $games += $stageGames;
                    $stageDetails[] = [
                        'stage' => $stageName,
                        'games_count' => $stageGames,
                        'stage_total_pin' => $pin,
                        'games' => $source['games'],
                        'source_aliases' => $source['source_aliases'],
                    ];
                }
                if ($games === 0) {
                    throw new RuntimeException(
                        "{$event['key']}: snapshot row has no detail scores: {$row->display_name}",
                    );
                }

                $breakdown = is_array($row->breakdown) ? $row->breakdown : [];
                $breakdown['official_detail'] = [
                    'source' => self::IMPORT_MARKER,
                    'event_key' => $event['key'],
                    'stages' => $stageDetails,
                ];
                $row->forceFill([
                    'scratch_pin' => $scratchPin,
                    'carry_pin' => $carryPin,
                    'total_pin' => $totalPin,
                    'games' => $games,
                    'average' => round($totalPin / max(1, $games), 3),
                    'source_count' => count($stageDetails),
                    'is_complete' => true,
                    'breakdown' => $breakdown,
                ])->save();
            }

            $summary[] = [
                'snapshot_id' => (int) $snapshot->id,
                'result_code' => (string) $snapshot->result_code,
                'stage_name' => $stageNames[$targetIndex],
                'games_count' => $gamesCount,
                'carry_game_count' => $carryGameCount,
                'row_count' => $snapshot->rows->count(),
            ];
        }

        return ['snapshots' => $summary];
    }

    /** @param array<int,string> $stageNames */
    private function snapshotTargetStageIndex(TournamentResultSnapshot $snapshot, array $stageNames): int
    {
        $targetStage = match ((string) $snapshot->result_code) {
            'prelim_total' => '予選',
            'semifinal_total' => '準決勝',
            'quarterfinal_total' => '準々決勝',
            'round_robin_total' => 'ラウンドロビン',
            'final' => end($stageNames),
            default => null,
        };
        $index = $targetStage === null ? false : array_search($targetStage, $stageNames, true);
        if ($index === false) {
            throw new RuntimeException(
                "Unsupported snapshot stage: {$snapshot->result_code}",
            );
        }

        return (int) $index;
    }

    /** @return array<string,string> */
    private function uniqueNameLicenseMap(array $event, array $participantLicenseMap): array
    {
        $candidates = [];
        foreach ($event['stages'] as $stage) {
            foreach ($stage['rows'] as $row) {
                $name = $this->normalizeName((string) $row['display_name']);
                $sourceLicense = $this->normalizeLicense((string) $row['license_no']);
                $license = $participantLicenseMap[$sourceLicense] ?? $sourceLicense;
                $candidates[$name][$license] = true;
            }
        }

        $map = [];
        foreach ($candidates as $name => $licenses) {
            if (count($licenses) === 1) {
                $map[$name] = (string) array_key_first($licenses);
            }
        }

        return $map;
    }

    /** @param Collection<string,Collection<string,array<string,mixed>>> $stageMaps */
    private function licenseExistsInMaps(string $license, Collection $stageMaps): bool
    {
        return $stageMaps->contains(
            fn (Collection $rows): bool => $rows->has($license),
        );
    }

    private function maximumStageGames(array $stage): int
    {
        return (int) collect($stage['rows'])->max(
            fn (array $row): int => (int) $row['games_count'],
        );
    }

    private function appendMarker(string $notes): string
    {
        if (str_contains($notes, self::IMPORT_MARKER)) {
            return $notes;
        }

        return trim($notes.' / '.self::IMPORT_MARKER, ' /');
    }

    private function normalizeLicense(string $license): string
    {
        return strtoupper(trim($license));
    }

    private function normalizeName(string $name): string
    {
        $name = function_exists('mb_convert_kana')
            ? mb_convert_kana($name, 'asKV', 'UTF-8')
            : $name;

        return mb_strtolower((string) preg_replace('/[\s　]+/u', '', trim($name)), 'UTF-8');
    }
}

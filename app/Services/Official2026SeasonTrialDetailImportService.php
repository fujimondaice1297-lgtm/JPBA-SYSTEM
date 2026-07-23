<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ScoreImportBatch;
use App\Models\Tournament;
use App\Models\TournamentResultSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Official2026SeasonTrialDetailImportService
{
    private const DATASET_PATH = 'data/jpba_official_2026_season_trial_detail_scores.json';

    private const IMPORT_MARKER = 'jpba_official_2026_season_trial_detail';

    private const EXPECTED_EVENT_COUNT = 11;

    public function __construct(
        private readonly Official2026TournamentResultsImportService $officialResults,
        private readonly ScoreImportOcrEngineBoundaryService $ocrBoundary,
        private readonly ScoreImportCommitService $commitService,
        private readonly SeasonTrialTemplateSetupService $templateSetupService,
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
            $eventErrors = $this->validateEventAgainstAggregate($event, $aggregateEvent, $aggregate);
            $tournamentName = (string) ($aggregateEvent['existing_tournament_name'] ?? '');
            $tournament = $tournamentName === '' ? null : Tournament::query()
                ->where('year', 2026)
                ->where('name', $tournamentName)
                ->first();

            if ($tournament === null) {
                $eventErrors[] = 'Tournament shell was not found.';
            }

            $licenses = $this->eventLicenses($event);
            $bowlerCount = ProBowler::query()->whereIn('license_no', $licenses)->count();
            if ($bowlerCount !== count($licenses)) {
                $eventErrors[] = 'One or more professional licenses are missing from the master.';
            }

            $scoreComparison = $tournament === null
                ? null
                : $this->compareExistingScores($tournament, $event);
            if ($event['key'] === 'stsu_b'
                && ($scoreComparison['actual_count'] ?? 0) > 0
                && ($scoreComparison['difference_count'] ?? 0) > 0) {
                $eventErrors[] = 'The protected Summer B score details differ from the official PDF reconstruction.';
            }

            $events[] = [
                'key' => $event['key'],
                'tournament_id' => $tournament?->id,
                'tournament_name' => $tournamentName,
                'expected_score_count' => $this->expectedScoreCount($event),
                'expected_score_sheet_count' => 3,
                'expected_frame_count' => 100,
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
                $aggregateEvent = $aggregateEvents->get($event['key']);
                $tournament = Tournament::query()
                    ->where('year', 2026)
                    ->where('name', $aggregateEvent['existing_tournament_name'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->standardizeTournament($tournament, $event['key']);
                $templateSetup = $this->templateSetupService->setup(
                    (int) $tournament->id,
                    true,
                    ['season_key' => $this->seasonKey($event['key'])],
                );

                $this->clearDetailData($tournament);
                $prelimImport = $this->stageAndCommitScores(
                    $tournament,
                    $event,
                    '予選',
                    $event['prelim'],
                    8,
                );
                $semifinalImport = $this->stageAndCommitScores(
                    $tournament,
                    $event,
                    '準決勝',
                    $event['semifinal'],
                    4,
                );
                $shootoutScoreCount = $this->insertShootoutScores($tournament, $event);
                $scoreSheetSummary = $this->insertShootoutScoreSheets($tournament, $event);
                $snapshotSummary = $this->synchronizeSnapshots($tournament, $event);

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
                    'template_setup' => $templateSetup,
                    'prelim_import' => $prelimImport,
                    'semifinal_import' => $semifinalImport,
                    'shootout_score_count' => $shootoutScoreCount,
                    'score_sheets' => $scoreSheetSummary,
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

    /** @return array<string,mixed> */
    public function dataset(): array
    {
        $path = database_path(self::DATASET_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Season-trial detail dataset was not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (count($payload['events'] ?? []) !== self::EXPECTED_EVENT_COUNT) {
            throw new RuntimeException('Season-trial detail dataset must contain 11 venues.');
        }

        return $payload;
    }

    /** @return array<int,string> */
    private function validateEventAgainstAggregate(array $event, ?array $aggregateEvent, array $aggregate): array
    {
        $errors = [];
        if ($aggregateEvent === null) {
            return ['Aggregate event is missing.'];
        }

        $alias = (string) ($event['source']['alias'] ?? '');
        $expectedHash = (string) ($aggregate['source_sha256'][$alias] ?? '');
        if ($expectedHash === '' || ! hash_equals($expectedHash, (string) ($event['source']['sha256'] ?? ''))) {
            $errors[] = 'Official source PDF hash does not match the aggregate dataset.';
        }
        if (count($event['shootout']['rows'] ?? []) !== 10) {
            $errors[] = 'Shootout must contain exactly 10 player scores.';
        }

        foreach (['prelim' => 8, 'semifinal' => 4] as $stage => $slotCount) {
            foreach ($event[$stage] ?? [] as $row) {
                if (count($row['games'] ?? []) !== $slotCount) {
                    $errors[] = "{$stage} game slots are invalid.";
                    break;
                }
                $total = array_sum(array_values(array_filter(
                    $row['games'],
                    fn ($score): bool => $score !== null,
                )));
                $expectedTotal = $stage === 'prelim'
                    ? (int) $row['total_pin']
                    : (int) $row['stage_total_pin'];
                if ($total !== $expectedTotal) {
                    $errors[] = "{$stage} per-game total is invalid for {$row['license_no']}.";
                    break;
                }
            }
        }

        foreach ($event['shootout']['rows'] ?? [] as $row) {
            if (count($row['frames'] ?? []) !== 10
                || (int) ($row['frames'][9]['cumulative_score'] ?? -1) !== (int) $row['score']) {
                $errors[] = "Shootout frame totals are invalid for {$row['license_no']}.";
                break;
            }
        }

        return array_values(array_unique($errors));
    }

    /** @return array<int,string> */
    private function eventLicenses(array $event): array
    {
        $licenses = [];
        foreach (['prelim', 'semifinal'] as $stage) {
            foreach ($event[$stage] as $row) {
                $licenses[strtoupper((string) $row['license_no'])] = true;
            }
        }

        return array_keys($licenses);
    }

    private function expectedScoreCount(array $event): int
    {
        $count = 0;
        foreach (['prelim', 'semifinal'] as $stage) {
            foreach ($event[$stage] as $row) {
                $count += count(array_filter($row['games'], fn ($score): bool => $score !== null));
            }
        }

        return $count + count($event['shootout']['rows']);
    }

    /** @return array<string,mixed> */
    private function compareExistingScores(Tournament $tournament, array $event): array
    {
        $expected = $this->expectedScoreMap($event);
        $actualRows = DB::table('game_scores')->where('tournament_id', $tournament->id)->get();
        $actual = [];
        foreach ($actualRows as $row) {
            $key = $this->scoreKey(
                (string) $row->stage,
                (string) $row->license_number,
                (string) ($row->entry_number ?? ''),
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
            'expected_count' => $this->expectedScoreCount($event),
            'actual_count' => $actualRows->count(),
            'difference_count' => count($differences),
            'difference_samples' => array_slice($differences, 0, 10),
        ];
    }

    /** @return array<string,array<int,int>> */
    private function expectedScoreMap(array $event): array
    {
        $scores = [];
        foreach ([['prelim', '予選'], ['semifinal', '準決勝']] as [$sourceKey, $stage]) {
            foreach ($event[$sourceKey] as $row) {
                foreach ($row['games'] as $index => $score) {
                    if ($score === null) {
                        continue;
                    }
                    $key = $this->scoreKey($stage, $row['license_no'], '', $index + 1);
                    $scores[$key][] = (int) $score;
                }
            }
        }
        foreach ($event['shootout']['rows'] as $row) {
            $key = $this->scoreKey('シュートアウト', $row['license_no'], $row['entry_number'], 1);
            $scores[$key][] = (int) $row['score'];
        }

        return $scores;
    }

    private function scoreKey(string $stage, string $license, string $entryNumber, int $game): string
    {
        if ($stage === 'シュートアウト' && str_starts_with($entryNumber, 'SO:')) {
            // The match code already identifies SO1/SO2/SO3. Normalize here so
            // legacy rows that stored every match as game 1 remain comparable.
            $game = 1;
        }

        return implode('|', [trim($stage), strtoupper(trim($license)), trim($entryNumber), $game]);
    }

    private function standardizeTournament(Tournament $tournament, string $eventKey): void
    {
        $settings = is_array($tournament->shootout_settings) ? $tournament->shootout_settings : [];
        $settings['source'] = self::IMPORT_MARKER.':'.$eventKey;
        $settings['stage_progress'] = [
            'prelim_game_count' => 8,
            'semifinal_game_count' => 4,
            'semifinal_total_game_count' => 12,
            'semifinal_qualifier_count' => 8,
        ];

        $tournament->forceFill([
            'setup_status' => 'completed',
            'competition_type' => 'singles',
            'gender' => 'M',
            'official_type' => 'official',
            'title_scope' => 'season_trial',
            'title_category' => 'season_trial',
            'counts_for_official_points' => true,
            'counts_for_average' => true,
            'counts_for_prize' => true,
            'result_flow_type' => 'prelim_to_semifinal_to_shootout_to_final',
            'shootout_qualifier_count' => 8,
            'shootout_seed_source_result_code' => 'semifinal_total',
            'shootout_format' => 'standard_8',
            'shootout_settings' => $settings,
        ])->save();
    }

    private function seasonKey(string $eventKey): string
    {
        return str_starts_with($eventKey, 'stsu_')
            ? 'summer'
            : (str_starts_with($eventKey, 'stw_') ? 'winter' : 'spring');
    }

    private function clearDetailData(Tournament $tournament): void
    {
        ScoreImportBatch::query()->where('tournament_id', $tournament->id)->delete();
        DB::table('game_scores')->where('tournament_id', $tournament->id)->delete();
        DB::table('tournament_match_score_sheets')->where('tournament_id', $tournament->id)->delete();
    }

    /** @return array<string,mixed> */
    private function stageAndCommitScores(
        Tournament $tournament,
        array $event,
        string $stage,
        array $rows,
        int $gameSlotCount,
    ): array {
        $batch = ScoreImportBatch::query()->create([
            'tournament_id' => $tournament->id,
            'import_type' => 'score_sheet_image',
            'source_filename' => $event['source']['alias'],
            'status' => 'draft',
            'notes' => "default_stage={$stage} / default_gender=M / ".self::IMPORT_MARKER.':'.$event['key'],
        ]);

        $payloadRows = [];
        foreach ($rows as $row) {
            $games = [];
            for ($game = 1; $game <= $gameSlotCount; $game++) {
                $score = $row['games'][$game - 1] ?? null;
                if ($score === null) {
                    continue;
                }
                $games[] = [
                    'game_number' => $game,
                    'score' => (int) $score,
                    'confidence' => 100,
                ];
            }
            if ($games === []) {
                continue;
            }
            $payloadRows[] = [
                'license_number' => $row['license_no'],
                'name' => $row['display_name'],
                'stage' => $stage,
                'gender' => 'M',
                'games' => $games,
                'confidence' => 100,
            ];
        }

        $engineText = json_encode(['rows' => $payloadRows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $staged = $this->ocrBoundary->stageTextResult($tournament, $batch, $engineText, null, [
            'default_stage' => $stage,
            'default_gender' => 'M',
            'engine_name' => 'official_pdf_table_fixture',
            'replace_existing' => true,
            'source_filename' => $event['source']['alias'],
            'operation_action' => 'official_pdf_table_stage',
            'operation_message' => 'JPBA公式PDFの表を1ゲーム単位の確認ステージへ変換しました。',
        ]);
        $committed = $this->commitService->commit($tournament, $batch->fresh());
        if (($staged['import_summary']['needs_review'] ?? 0) > 0 || ($committed['skipped'] ?? 0) > 0) {
            $reviewIssueCounts = $batch->rows()
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
            $reviewSamples = $batch->rows()
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
                $stage,
                json_encode($staged['import_summary'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($committed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($reviewIssueCounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($reviewSamples, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ));
        }

        return [
            'batch_id' => (int) $batch->id,
            'payload_player_count' => count($payloadRows),
            'staged' => $staged['import_summary'],
            'committed' => $committed,
        ];
    }

    private function insertShootoutScores(Tournament $tournament, array $event): int
    {
        $now = now();
        $bowlerMap = ProBowler::query()
            ->whereIn('license_no', collect($event['shootout']['rows'])->pluck('license_no')->all())
            ->get()
            ->keyBy(fn (ProBowler $bowler): string => strtoupper((string) $bowler->license_no));
        $participantMap = DB::table('tournament_participants')
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('pro_bowler_license_no')
            ->pluck('id', 'pro_bowler_license_no')
            ->mapWithKeys(fn ($id, $license): array => [strtoupper((string) $license) => (int) $id]);

        $rows = [];
        foreach ($event['shootout']['rows'] as $row) {
            $license = strtoupper((string) $row['license_no']);
            $bowler = $bowlerMap->get($license);
            if ($bowler === null) {
                throw new RuntimeException("{$event['key']}: shootout bowler is missing: {$license}");
            }
            $rows[] = [
                'tournament_id' => $tournament->id,
                'stage' => 'シュートアウト',
                'license_number' => $bowler->license_no,
                'name' => $row['display_name'],
                'entry_number' => $row['entry_number'],
                'game_number' => $this->shootoutGameNumber((string) $row['entry_number']),
                'score' => (int) $row['score'],
                'shift' => null,
                'gender' => 'M',
                'pro_bowler_id' => $bowler->id,
                'tournament_participant_id' => $participantMap->get($license),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('game_scores')->insert($rows);

        return count($rows);
    }

    private function shootoutGameNumber(string $entryNumber): int
    {
        if (preg_match('/^SO:SO([123]):[ABCD]$/', $entryNumber, $matches) !== 1) {
            throw new RuntimeException("Invalid shootout entry number: {$entryNumber}");
        }

        return (int) $matches[1];
    }

    /** @return array<string,int> */
    private function insertShootoutScoreSheets(Tournament $tournament, array $event): array
    {
        $now = now();
        $rowsByMatch = collect($event['shootout']['rows'])
            ->groupBy(fn (array $row): string => explode(':', $row['entry_number'])[1]);
        $labels = ['SO1' => 'シュートアウト1stマッチ', 'SO2' => 'シュートアウト2ndマッチ', 'SO3' => '優勝決定戦'];
        $bowlerMap = ProBowler::query()
            ->whereIn('license_no', collect($event['shootout']['rows'])->pluck('license_no')->all())
            ->get()
            ->keyBy(fn (ProBowler $bowler): string => strtoupper((string) $bowler->license_no));
        $sheetCount = 0;
        $playerCount = 0;
        $frameCount = 0;

        foreach (['SO1', 'SO2', 'SO3'] as $matchIndex => $matchKey) {
            $players = $rowsByMatch->get($matchKey, collect())->sortBy('entry_number')->values();
            if ($players->count() < 2 || $players->where('is_winner', true)->count() !== 1) {
                throw new RuntimeException("{$event['key']}: {$matchKey} score-sheet winner is invalid.");
            }

            $sheetId = (int) DB::table('tournament_match_score_sheets')->insertGetId([
                'tournament_id' => $tournament->id,
                'sheet_type' => 'shootout',
                'stage_code' => 'シュートアウト',
                'match_code' => 'SO:'.$matchKey,
                'match_label' => $labels[$matchKey],
                'match_order' => $matchIndex + 1,
                'game_number' => 1,
                'lane_label' => null,
                'is_published' => true,
                'confirmed_at' => $now,
                'notes' => self::IMPORT_MARKER.':'.$event['key'].' / '.$event['source']['url'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sheetCount++;

            foreach ($players as $index => $row) {
                $license = strtoupper((string) $row['license_no']);
                $bowler = $bowlerMap->get($license);
                $slot = explode(':', $row['entry_number'])[2];
                $playerId = (int) DB::table('tournament_match_score_sheet_players')->insertGetId([
                    'score_sheet_id' => $sheetId,
                    'sort_order' => $index + 1,
                    'player_slot' => $slot,
                    'pro_bowler_id' => $bowler?->id,
                    'pro_bowler_license_no' => $bowler?->license_no ?? $row['license_no'],
                    'display_name' => $row['display_name'],
                    'name_kana' => $bowler?->name_kana,
                    'dominant_arm' => $bowler?->dominant_arm,
                    'lane_label' => null,
                    'final_score' => (int) $row['score'],
                    'is_winner' => (bool) $row['is_winner'],
                    'score_summary' => json_encode([
                        'source' => self::IMPORT_MARKER,
                        'source_pdf_sha256' => $event['source']['sha256'],
                        'frame_candidate_count' => $row['frame_candidate_count'],
                        'frame_cumulative_corrections' => $row['frame_cumulative_corrections'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $playerCount++;

                $frames = [];
                foreach ($row['frames'] as $frame) {
                    $frames[] = [
                        'score_sheet_player_id' => $playerId,
                        'frame_no' => (int) $frame['frame_no'],
                        'throw1' => $frame['throw1'] ?: null,
                        'throw2' => $frame['throw2'] ?: null,
                        'throw3' => $frame['throw3'] ?: null,
                        'frame_score' => (int) $frame['frame_score'],
                        'cumulative_score' => (int) $frame['cumulative_score'],
                        'display_marks' => json_encode($frame['display_marks'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'remaining_pins' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('tournament_match_score_frames')->insert($frames);
                $frameCount += count($frames);
            }
        }

        return ['sheets' => $sheetCount, 'players' => $playerCount, 'frames' => $frameCount];
    }

    /** @return array<string,mixed> */
    private function synchronizeSnapshots(Tournament $tournament, array $event): array
    {
        $snapshots = TournamentResultSnapshot::query()
            ->with('rows')
            ->where('tournament_id', $tournament->id)
            ->where('is_current', true)
            ->get();
        $prelimSnapshot = $snapshots->firstWhere('result_code', 'prelim_total');
        $semifinalSnapshot = $snapshots->firstWhere('result_code', 'semifinal_total');
        $finalSnapshot = $snapshots->first(fn (TournamentResultSnapshot $snapshot): bool => (bool) $snapshot->is_final);
        if ($prelimSnapshot === null || $semifinalSnapshot === null || $finalSnapshot === null) {
            throw new RuntimeException("{$event['key']}: required result snapshots are missing.");
        }

        $prelim = collect($event['prelim'])->keyBy(fn (array $row): string => strtoupper((string) $row['license_no']));
        $semifinal = collect($event['semifinal'])->keyBy(fn (array $row): string => strtoupper((string) $row['license_no']));
        $shootout = collect($event['shootout']['rows'])->groupBy(fn (array $row): string => strtoupper((string) $row['license_no']));

        $this->updateSnapshotMetadata($prelimSnapshot, '予選', 8, 0, []);
        $this->updateSnapshotMetadata($semifinalSnapshot, '準決勝', 12, 8, ['予選']);
        $this->updateSnapshotMetadata($finalSnapshot, 'シュートアウト', 12, 12, ['予選', '準決勝']);

        $this->updateSnapshotRows($prelimSnapshot, function (string $license, array $breakdown) use ($prelim): array {
            $source = $prelim->get($license);
            if ($source === null) {
                throw new RuntimeException("Preliminary snapshot row is absent from detail data: {$license}");
            }

            return [
                'scratch_pin' => (int) $source['total_pin'],
                'carry_pin' => 0,
                'total_pin' => (int) $source['total_pin'],
                'games' => (int) $source['games_count'],
                'average' => round($source['total_pin'] / max(1, $source['games_count']), 3),
                'breakdown' => $this->detailBreakdown($breakdown, 'prelim', $source['games']),
            ];
        });

        $this->updateSnapshotRows($semifinalSnapshot, function (string $license, array $breakdown) use ($prelim, $semifinal): array {
            $source = $semifinal->get($license);
            $carry = $prelim->get($license);
            if ($source === null || $carry === null) {
                throw new RuntimeException("Semifinal snapshot row is absent from detail data: {$license}");
            }
            $games = (int) $carry['games_count'] + (int) $source['games_count'];

            return [
                'scratch_pin' => (int) $source['stage_total_pin'],
                'carry_pin' => (int) $source['carry_total_pin'],
                'total_pin' => (int) $source['total_pin'],
                'games' => $games,
                'average' => round($source['total_pin'] / max(1, $games), 3),
                'breakdown' => $this->detailBreakdown($breakdown, 'semifinal', $source['games']),
            ];
        });

        $this->updateSnapshotRows($finalSnapshot, function (string $license, array $breakdown) use ($prelim, $semifinal, $shootout): array {
            $source = $semifinal->get($license);
            $carry = $prelim->get($license);
            if ($source === null || $carry === null) {
                throw new RuntimeException("Final snapshot row is absent from detail data: {$license}");
            }
            $games = (int) $carry['games_count'] + (int) $source['games_count'];
            $matchRows = $shootout->get($license, collect());

            return [
                'scratch_pin' => (int) $matchRows->sum('score'),
                'carry_pin' => (int) $source['total_pin'],
                'total_pin' => (int) $source['total_pin'],
                'games' => $games,
                'average' => round($source['total_pin'] / max(1, $games), 3),
                'breakdown' => $this->detailBreakdown($breakdown, 'shootout', $matchRows->values()->all()),
            ];
        });

        return [
            'prelim_snapshot_id' => (int) $prelimSnapshot->id,
            'semifinal_snapshot_id' => (int) $semifinalSnapshot->id,
            'final_snapshot_id' => (int) $finalSnapshot->id,
            'prelim_rows' => $prelimSnapshot->rows->count(),
            'semifinal_rows' => $semifinalSnapshot->rows->count(),
            'final_rows' => $finalSnapshot->rows->count(),
        ];
    }

    /** @param array<int,string> $carryStages */
    private function updateSnapshotMetadata(
        TournamentResultSnapshot $snapshot,
        string $stage,
        int $games,
        int $carryGames,
        array $carryStages,
    ): void {
        $definition = is_array($snapshot->calculation_definition) ? $snapshot->calculation_definition : [];
        $definition['detail_source'] = self::IMPORT_MARKER;
        $snapshot->forceFill([
            'stage_name' => $stage,
            'games_count' => $games,
            'carry_game_count' => $carryGames,
            'carry_stage_names' => $carryStages,
            'calculation_definition' => $definition,
            'notes' => trim((string) $snapshot->notes.' / '.self::IMPORT_MARKER),
        ])->save();
    }

    private function updateSnapshotRows(TournamentResultSnapshot $snapshot, callable $resolver): void
    {
        foreach ($snapshot->rows as $row) {
            $license = strtoupper(trim((string) $row->pro_bowler_license_no));
            $breakdown = is_array($row->breakdown) ? $row->breakdown : [];
            $attributes = $resolver($license, $breakdown);
            $row->forceFill($attributes + [
                'source_count' => 1,
                'is_complete' => true,
            ])->save();
        }
    }

    /** @return array<string,mixed> */
    private function detailBreakdown(array $existing, string $stage, array $source): array
    {
        $existing['official_detail'] = [
            'source' => self::IMPORT_MARKER,
            'stage' => $stage,
            'data' => $source,
        ];

        return $existing;
    }
}

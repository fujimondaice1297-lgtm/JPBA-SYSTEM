<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ScoreImportBatch;
use App\Models\Tournament;
use App\Models\TournamentResultOutput;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Official2026SeahorseSelectionImportService
{
    private const DATASET_PATH = 'data/jpba_official_2026_seahorse_selection_scores.json';

    private const IMPORT_MARKER = 'jpba_official_2026_seahorse_selection';

    private const EXPECTED_ATTEMPT_COUNT = 240;

    private const EXPECTED_PARTICIPANT_COUNT = 101;

    private const EXPECTED_SCORE_COUNT = 720;

    private const EXPECTED_TOTAL_PIN = 147459;

    public function __construct(
        private readonly ScoreImportOcrEngineBoundaryService $ocrBoundary,
        private readonly ScoreImportCommitService $commitService,
        private readonly TournamentResultPublicationService $publicationService,
        private readonly TournamentResultCompletenessService $completenessService,
    ) {}

    /** @return array<string,mixed> */
    public function import(bool $write = false, string $adminEmail = 'yamaguchi@jpba.or.jp'): array
    {
        $dataset = $this->dataset();
        $event = $dataset['events'][0];
        $admin = User::query()->where('email', $adminEmail)->first();
        $venue = Venue::query()
            ->where('name', $event['tournament']['venue_name'])
            ->where('is_active', true)
            ->first();
        $licenses = collect($event['stages'])
            ->flatMap(fn (array $stage): array => array_column($stage['rows'], 'license_no'))
            ->unique()
            ->values();
        $bowlers = ProBowler::query()
            ->whereIn('license_no', $licenses)
            ->get()
            ->keyBy(fn (ProBowler $bowler): string => strtoupper((string) $bowler->license_no));
        $missingLicenses = $licenses
            ->reject(fn (string $license): bool => $bowlers->has(strtoupper($license)))
            ->values()
            ->all();
        $tournament = $this->findTournament($event);
        $errors = [];

        if ($admin === null) {
            $errors[] = "Administrator was not found: {$adminEmail}";
        }
        if ($venue === null) {
            $errors[] = 'The active venue was not found: '.$event['tournament']['venue_name'];
        }
        if ($missingLicenses !== []) {
            $errors[] = 'Professional licenses are missing: '.implode(', ', $missingLicenses);
        }
        if ($tournament !== null && ! $this->isOwnedTournament($tournament)) {
            $errors[] = 'An existing tournament with the same name is not owned by this importer.';
        }

        $report = [
            'mode' => $write ? 'write' : 'dry-run',
            'dataset' => $dataset['dataset'],
            'dataset_sha256' => hash_file('sha256', database_path(self::DATASET_PATH)),
            'source_checked_at' => $dataset['source_checked_at'],
            'source_sha256' => $event['source']['sha256'],
            'attempt_count' => (int) $event['attempt_count'],
            'participant_count' => (int) $event['participant_count'],
            'expected_score_count' => (int) $event['expected_score_count'],
            'expected_total_pin' => (int) $event['expected_total_pin'],
            'existing_tournament_id' => $tournament?->id,
            'admin_id' => $admin?->id,
            'venue_id' => $venue?->id,
            'missing_licenses' => $missingLicenses,
            'errors' => $errors,
            'repaired' => null,
        ];

        if (! $write || $errors !== []) {
            return $report;
        }

        return DB::transaction(function () use ($event, $admin, $venue, $bowlers, $report): array {
            $tournament = $this->persistTournament($event, $venue);
            $this->clearImportedData($tournament);
            $this->insertParticipantsAndEntries($tournament, $event, $bowlers);
            $imports = $this->importScores($tournament, $event, $admin);
            $snapshot = $this->createFinalSnapshot($tournament, $event, $bowlers, (int) $admin->id);
            $this->synchronizeOutputs($tournament);

            $preview = $this->publicationService->preview($tournament->fresh(), $snapshot->fresh());
            if (! $preview['can_publish']) {
                throw new RuntimeException(implode(' ', $preview['errors']));
            }
            $publication = $this->publicationService->publish(
                $tournament->fresh(),
                $snapshot->fresh(),
                (int) $admin->id,
                (string) $preview['result_checksum'],
                self::IMPORT_MARKER,
            );
            $audit = $this->completenessService->audit($tournament->fresh());
            if (! $audit['is_complete']) {
                throw new RuntimeException(implode(' ', $audit['errors']));
            }

            $report['repaired'] = [
                'tournament_id' => (int) $tournament->id,
                'publication_id' => (int) $publication->id,
                'publication_row_count' => (int) $publication->row_count,
                'game_score_count' => DB::table('game_scores')
                    ->where('tournament_id', $tournament->id)
                    ->count(),
                'game_score_total_pin' => (int) DB::table('game_scores')
                    ->where('tournament_id', $tournament->id)
                    ->sum('score'),
                'imports' => $imports,
                'completeness' => $audit,
            ];

            return $report;
        });
    }

    /** @return array<string,mixed> */
    public function dataset(): array
    {
        $path = database_path(self::DATASET_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Selection dataset was not found: {$path}");
        }
        $dataset = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $event = $dataset['events'][0] ?? null;
        if (! is_array($event)
            || (int) ($event['attempt_count'] ?? 0) !== self::EXPECTED_ATTEMPT_COUNT
            || (int) ($event['participant_count'] ?? 0) !== self::EXPECTED_PARTICIPANT_COUNT
            || (int) ($event['expected_score_count'] ?? 0) !== self::EXPECTED_SCORE_COUNT
            || (int) ($event['expected_total_pin'] ?? 0) !== self::EXPECTED_TOTAL_PIN) {
            throw new RuntimeException('Selection dataset control totals are invalid.');
        }

        return $dataset;
    }

    private function findTournament(array $event): ?Tournament
    {
        return Tournament::query()
            ->where('year', 2026)
            ->where('name', $event['tournament']['name'])
            ->first();
    }

    private function isOwnedTournament(Tournament $tournament): bool
    {
        return ($tournament->template_snapshot['_official_import'] ?? null) === self::IMPORT_MARKER;
    }

    private function persistTournament(array $event, Venue $venue): Tournament
    {
        $source = $event['tournament'];
        $tournament = $this->findTournament($event) ?? new Tournament;
        $tournament->fill([
            'name' => $source['name'],
            'setup_status' => 'completed',
            'competition_type' => $source['competition_type'],
            'start_date' => $source['start_date'],
            'end_date' => $source['end_date'],
            'year' => (int) $source['year'],
            'gender' => $source['gender'],
            'official_type' => $source['official_type'],
            'title_category' => $source['title_category'],
            'title_scope' => $source['title_scope'],
            'counts_for_official_points' => false,
            'counts_for_average' => true,
            'counts_for_prize' => false,
            'venue_id' => $venue->id,
            'venue_name' => $venue->name,
            'venue_address' => $venue->address,
            'venue_tel' => $venue->tel,
            'venue_fax' => $venue->fax,
            'host' => 'JPBA男子プロボウラーズ選手会',
            'authorized_by' => 'JPBA',
            'materials' => $source['source_url'],
            'result_flow_type' => 'legacy_standard',
            'template_snapshot' => [
                '_official_import' => self::IMPORT_MARKER,
                'event_key' => $event['key'],
                'source_url' => $source['source_url'],
                'source_checked_at' => '2026-07-23',
                'parent_event_key' => 'seahorse_2026',
            ],
        ])->save();

        return $tournament->fresh();
    }

    private function clearImportedData(Tournament $tournament): void
    {
        TournamentResultPublication::query()->where('tournament_id', $tournament->id)->delete();
        TournamentResultSnapshot::query()->where('tournament_id', $tournament->id)->delete();
        DB::table('tournament_results')->where('tournament_id', $tournament->id)->delete();
        ScoreImportBatch::query()->where('tournament_id', $tournament->id)->delete();
        DB::table('game_scores')->where('tournament_id', $tournament->id)->delete();
        DB::table('stage_settings')->where('tournament_id', $tournament->id)->delete();
        TournamentResultOutput::query()->where('tournament_id', $tournament->id)->delete();
        DB::table('tournament_entries')->where('tournament_id', $tournament->id)->delete();
        DB::table('tournament_participants')->where('tournament_id', $tournament->id)->delete();
    }

    /** @param \Illuminate\Support\Collection<string,ProBowler> $bowlers */
    private function insertParticipantsAndEntries(
        Tournament $tournament,
        array $event,
        $bowlers,
    ): void {
        $licenses = collect($event['stages'])
            ->flatMap(fn (array $stage): array => array_column($stage['rows'], 'license_no'))
            ->unique()
            ->values();
        $now = now();
        foreach ($licenses as $index => $license) {
            $bowler = $bowlers->get(strtoupper((string) $license));
            if ($bowler === null) {
                throw new RuntimeException("Professional license disappeared: {$license}");
            }
            DB::table('tournament_participants')->insert([
                'tournament_id' => $tournament->id,
                'pro_bowler_license_no' => $bowler->license_no,
                'pro_bowler_id' => $bowler->id,
                'participant_type' => 'pro',
                'display_name' => $bowler->name_kanji,
                'display_license_no' => $bowler->license_no,
                'gender' => 'M',
                'sort_order' => $index + 1,
                'source_note' => self::IMPORT_MARKER,
                'is_temporary' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('tournament_entries')->insert([
                'pro_bowler_id' => $bowler->id,
                'tournament_id' => $tournament->id,
                'status' => 'entry',
                'is_paid' => false,
                'shift_drawn' => false,
                'lane_drawn' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function importScores(Tournament $tournament, array $event, User $admin): array
    {
        $imports = [];
        foreach ($event['stages'] as $stage) {
            $stageName = (string) $stage['stage'];
            DB::table('stage_settings')->insert([
                'tournament_id' => $tournament->id,
                'stage' => $stageName,
                'total_games' => 3,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $batch = ScoreImportBatch::query()->create([
                'tournament_id' => $tournament->id,
                'import_type' => 'score_sheet_image',
                'source_filename' => $event['source']['filename'],
                'status' => 'draft',
                'imported_by' => $admin->id,
                'notes' => "default_stage={$stageName} / default_gender=M / ".self::IMPORT_MARKER,
            ]);
            $payloadRows = array_map(fn (array $row): array => [
                'license_number' => $row['license_no'],
                'name' => $row['display_name'],
                'stage' => $stageName,
                'gender' => 'M',
                'games' => array_map(fn (array $game): array => [
                    'game_number' => (int) $game['game_number'],
                    'score' => (int) $game['score'],
                    'confidence' => 100,
                ], $row['games']),
                'confidence' => 100,
            ], $stage['rows']);
            $engineText = json_encode(
                ['rows' => $payloadRows],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            $staged = $this->ocrBoundary->stageTextResult($tournament, $batch, $engineText, $admin, [
                'default_stage' => $stageName,
                'default_gender' => 'M',
                'engine_name' => 'official_pdf_table_fixture',
                'replace_existing' => true,
                'source_filename' => $event['source']['filename'],
                'operation_action' => 'official_pdf_table_stage',
                'operation_message' => 'JPBA official selection scores were staged.',
            ]);
            $committed = $this->commitService->commit($tournament, $batch->fresh(), $admin);
            if (($staged['import_summary']['needs_review'] ?? 0) > 0 || ($committed['skipped'] ?? 0) > 0) {
                throw new RuntimeException("{$stageName}: score import contains unresolved rows.");
            }
            $imports[] = [
                'stage' => $stageName,
                'batch_id' => (int) $batch->id,
                'player_count' => count($payloadRows),
                'staged' => $staged['import_summary'],
                'committed' => $committed,
            ];
        }

        return $imports;
    }

    /** @param \Illuminate\Support\Collection<string,ProBowler> $bowlers */
    private function createFinalSnapshot(
        Tournament $tournament,
        array $event,
        $bowlers,
        int $adminId,
    ): TournamentResultSnapshot {
        $aggregates = [];
        foreach ($event['stages'] as $stage) {
            foreach ($stage['rows'] as $row) {
                $license = strtoupper((string) $row['license_no']);
                $aggregates[$license] ??= [
                    'license_no' => $license,
                    'display_name' => $row['display_name'],
                    'games' => 0,
                    'total_pin' => 0,
                    'attempt_pins' => [],
                    'breakdown' => [],
                ];
                $pin = (int) $row['stage_total_pin'];
                $aggregates[$license]['games'] += 3;
                $aggregates[$license]['total_pin'] += $pin;
                $aggregates[$license]['attempt_pins'][] = $pin;
                $aggregates[$license]['breakdown'][] = [
                    'stage' => $stage['stage'],
                    'games' => 3,
                    'total_pin' => $pin,
                ];
            }
        }
        usort($aggregates, function (array $left, array $right): int {
            rsort($left['attempt_pins']);
            rsort($right['attempt_pins']);

            return $right['attempt_pins'] <=> $left['attempt_pins']
                ?: $right['total_pin'] <=> $left['total_pin']
                ?: strcmp($left['display_name'], $right['display_name']);
        });
        $sourceSets = array_map(fn (array $stage): array => [
            'stage' => $stage['stage'],
            'game_from' => 1,
            'game_to' => 3,
            'bucket' => 'scratch',
        ], $event['stages']);
        $snapshot = TournamentResultSnapshot::query()->create([
            'tournament_id' => $tournament->id,
            'result_code' => 'selection_final',
            'result_name' => '選抜大会 A・B・C・Dシフト 3G 最終成績',
            'result_type' => 'total_pin',
            'stage_name' => '選抜大会',
            'gender' => 'M',
            'shift' => null,
            'games_count' => max(array_column($aggregates, 'games')),
            'carry_game_count' => 0,
            'carry_stage_names' => [],
            'calculation_definition' => [
                'source_sets' => $sourceSets,
                'ranking_basis' => 'best_single_shift_3g',
                'annual_average_basis' => 'all_selection_attempts',
                'detail_source' => self::IMPORT_MARKER,
            ],
            'reflected_at' => now(),
            'reflected_by' => $adminId,
            'is_final' => true,
            'is_published' => true,
            'is_current' => true,
            'notes' => self::IMPORT_MARKER,
        ]);
        foreach ($aggregates as $index => $row) {
            $bowler = $bowlers->get($row['license_no']);
            TournamentResultSnapshotRow::query()->create([
                'snapshot_id' => $snapshot->id,
                'ranking' => $index + 1,
                'subject_type' => 'individual',
                'pro_bowler_id' => $bowler->id,
                'pro_bowler_license_no' => $bowler->license_no,
                'display_name' => $bowler->name_kanji,
                'gender' => 'M',
                'entry_number' => $bowler->license_no,
                'identity_key' => 'pro:'.$bowler->id,
                'scratch_pin' => $row['total_pin'],
                'carry_pin' => 0,
                'total_pin' => $row['total_pin'],
                'games' => $row['games'],
                'source_count' => count($row['breakdown']),
                'is_complete' => true,
                'breakdown' => [
                    'attempts' => $row['breakdown'],
                    'best_shift_pin' => max($row['attempt_pins']),
                ],
                'average' => round($row['total_pin'] / $row['games'], 3),
                'tie_break_value' => max($row['attempt_pins']),
                'points' => 0,
                'prize_money' => 0,
            ]);
        }

        return $snapshot->fresh('rows');
    }

    private function synchronizeOutputs(Tournament $tournament): void
    {
        foreach ([
            ['result', 'official'],
            ['average', 'official'],
        ] as [$type, $scope]) {
            TournamentResultOutput::query()->updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'output_type' => $type,
                    'output_scope' => $scope,
                ],
                [
                    'distribution_pattern_id' => null,
                    'settings' => ['source' => self::IMPORT_MARKER],
                    'is_active' => true,
                ],
            );
        }
    }
}

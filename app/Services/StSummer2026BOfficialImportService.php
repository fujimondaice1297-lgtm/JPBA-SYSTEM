<?php

namespace App\Services;

use App\Models\ScoreImportBatch;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class StSummer2026BOfficialImportService
{
    private const STAGE_PRELIM = '予選';
    private const STAGE_SEMIFINAL = '準決勝';
    private const STAGE_SHOOTOUT = 'シュートアウト';

    public function __construct(
        private readonly ScoreImportOcrEngineBoundaryService $ocrBoundary,
        private readonly ScoreImportCommitService $commitService,
        private readonly ShootoutService $shootoutService,
        private readonly TournamentTitleSyncService $titleSyncService,
    ) {
    }

    public function preview(string $sourceDir): array
    {
        $data = $this->loadData($sourceDir);

        return [
            'entry_rows' => count($data['entries']),
            'active_entry_rows' => count($this->activeEntries($data['entries'])),
            'prelim_4g_rows' => count($data['prelim4']),
            'prelim_8g_rows' => count($data['prelim8']),
            'prelim_scored_rows' => count(array_filter($data['prelim8'], fn (array $row): bool => !$row['is_blind'])),
            'prelim_blind_rows' => count(array_filter($data['prelim8'], fn (array $row): bool => $row['is_blind'])),
            'semifinal_rows' => count($data['semifinal']),
            'shootout_qualifier_rows' => count(array_slice($data['semifinal'], 0, 8)),
            'final_award_rows' => count($data['final_awards']),
            'expected_score_import_rows' => [
                'prelim' => count(array_filter($data['prelim8'], fn (array $row): bool => !$row['is_blind'])) * 8,
                'semifinal' => count($data['semifinal']) * 4,
            ],
            'top_prelim' => array_slice($data['prelim8'], 0, 3),
            'top_semifinal' => array_slice($data['semifinal'], 0, 8),
            'final_awards' => $data['final_awards'],
        ];
    }

    public function import(string $sourceDir): array
    {
        $data = $this->loadData($sourceDir);
        $this->validateData($data);

        return DB::transaction(fn (): array => $this->persistImport($data));
    }

    private function persistImport(array $data): array
    {
        $now = now();
        $tournament = $this->upsertTournament();

        $this->clearTournamentData((int) $tournament->id);
        $participantMap = $this->insertParticipantsAndEntries($tournament, $data['entries'], $now);
        $laneCount = $this->insertLaneAssignments($tournament, $data['entries'], $participantMap, $now);

        $prelimImport = $this->stageAndCommitScores(
            tournament: $tournament,
            sourceFilename: 'B_Eliminatins_8G.pdf.ocr.json',
            stage: self::STAGE_PRELIM,
            rows: $this->scorePayloadRows($data['prelim8'], self::STAGE_PRELIM, 8),
        );

        $semifinalImport = $this->stageAndCommitScores(
            tournament: $tournament,
            sourceFilename: 'B_Semifinal_4G.pdf.ocr.json',
            stage: self::STAGE_SEMIFINAL,
            rows: $this->scorePayloadRows($data['semifinal'], self::STAGE_SEMIFINAL, 4),
        );

        $snapshots = $this->insertSnapshots($tournament, $data, $now);
        $shootoutScoreCount = $this->insertShootoutGameScores($tournament, $data['semifinal'], $now);
        $shootoutSummary = $this->validateShootout($data);
        $resultCount = $this->insertTournamentResults($tournament, $data, $now);
        $titleSync = $this->titleSyncService->sync($tournament);

        return [
            'tournament_id' => (int) $tournament->id,
            'tournament_name' => $tournament->name,
            'participant_count' => count($participantMap),
            'entry_count' => DB::table('tournament_entries')->where('tournament_id', $tournament->id)->count(),
            'lane_assignment_count' => $laneCount,
            'score_imports' => [
                'prelim' => $prelimImport,
                'semifinal' => $semifinalImport,
            ],
            'game_score_count' => DB::table('game_scores')->where('tournament_id', $tournament->id)->count(),
            'shootout_game_score_count' => $shootoutScoreCount,
            'snapshots' => $snapshots,
            'shootout' => $shootoutSummary,
            'tournament_result_count' => $resultCount,
            'title_sync' => $titleSync,
        ];
    }

    private function loadData(string $sourceDir): array
    {
        $dir = rtrim($sourceDir, "\\/");
        if ($dir === '' || !is_dir($dir)) {
            throw new InvalidArgumentException("source-dir not found: {$sourceDir}");
        }

        return [
            'entries' => $this->parseEntryRows($this->readText($dir, 'B_EliminationsLane_0629.txt')),
            'prelim4' => $this->parsePrelim4Rows($this->readText($dir, 'B_Eliminatins_4G.txt')),
            'prelim8' => $this->parsePrelim8Rows($this->readText($dir, 'B_Eliminatins_8G.txt')),
            'semifinal' => $this->parseSemifinalRows($this->readText($dir, 'B_Semifinal_4G.txt')),
            'final_awards' => $this->parseFinalAwardRows($this->readText($dir, 'B_FinalResult.txt')),
        ];
    }

    private function readText(string $dir, string $file): string
    {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            throw new InvalidArgumentException("required OCR text file not found: {$path}");
        }

        $text = file_get_contents($path);
        if ($text === false || trim($text) === '') {
            throw new InvalidArgumentException("OCR text file is empty: {$path}");
        }

        return $text;
    }

    private function validateData(array $data): void
    {
        $checks = [
            'active entries' => count($this->activeEntries($data['entries'])) >= 50,
            'prelim 4G rows' => count($data['prelim4']) >= 48,
            'prelim 8G rows' => count($data['prelim8']) >= 50,
            'prelim scored rows' => count(array_filter($data['prelim8'], fn (array $row): bool => !$row['is_blind'])) === 48,
            'semifinal rows' => count($data['semifinal']) === 24,
            'final awards' => count($data['final_awards']) === 8,
        ];

        foreach ($checks as $label => $ok) {
            if (!$ok) {
                throw new RuntimeException("official PDF parse check failed: {$label}");
            }
        }

        foreach ($data['prelim8'] as $row) {
            if ($row['is_blind']) {
                continue;
            }
            $total = array_sum($row['scores']);
            if ($total !== $row['total']) {
                throw new RuntimeException("prelim total mismatch: {$row['license']} {$row['name']}");
            }
        }

        foreach ($data['semifinal'] as $row) {
            if (array_sum($row['scores']) !== $row['semi_total']) {
                throw new RuntimeException("semifinal total mismatch: {$row['license']} {$row['name']}");
            }
            if ($row['prelim_total'] + $row['semi_total'] !== $row['total_12g']) {
                throw new RuntimeException("semifinal carry total mismatch: {$row['license']} {$row['name']}");
            }
        }
    }

    private function upsertTournament(): Tournament
    {
        $payload = [
            'name' => 'メリーランドカップ JPBAシーズントライアル2026 サマーシリーズ B会場',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
            'venue_name' => 'サンスクエアボウル',
            'venue_address' => '〒114-0002 東京都北区王子1-4-1',
            'venue_tel' => '03(3927)0200',
            'venue_fax' => '03(3914)4516',
            'host' => '(公社)日本プロボウリング協会',
            'authorized_by' => '(公社)日本プロボウリング協会',
            'supervisor' => '会場該当地区及び事務局',
            'year' => 2026,
            'gender' => 'M',
            'official_type' => 'official',
            'title_category' => 'season_trial',
            'result_flow_type' => 'prelim_to_semifinal_to_shootout_to_final',
            'shootout_qualifier_count' => 8,
            'shootout_seed_source_result_code' => 'semifinal_total',
            'shootout_format' => 'standard_8',
            'shootout_settings' => [
                'source' => 'JPBA official PDF ST Summer 2026 B venue',
                'stage_progress' => [
                    'prelim_player_count' => 48,
                    'prelim_game_count' => 8,
                    'prelim_qualifier_count' => 24,
                    'semifinal_game_count' => 4,
                    'semifinal_total_game_count' => 12,
                    'semifinal_qualifier_count' => 8,
                ],
            ],
            'lane_from' => 3,
            'lane_to' => 36,
            'lane_assignment_mode' => 'lane_movement',
            'box_player_count' => 2,
            'use_lane_draw' => false,
            'use_shift_draw' => false,
        ];

        $existing = Tournament::query()
            ->where('name', $payload['name'])
            ->where('year', 2026)
            ->where('venue_name', 'サンスクエアボウル')
            ->first();

        if ($existing) {
            $existing->fill($payload)->save();

            return $existing->fresh();
        }

        return Tournament::query()->create($payload);
    }

    private function clearTournamentData(int $tournamentId): void
    {
        $snapshotIds = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournamentId)
            ->pluck('id');

        if ($snapshotIds->isNotEmpty()) {
            DB::table('tournament_result_snapshot_rows')->whereIn('snapshot_id', $snapshotIds->all())->delete();
            DB::table('tournament_result_snapshots')->whereIn('id', $snapshotIds->all())->delete();
        }

        if (Schema::hasTable('tournament_match_score_sheets')) {
            $sheetIds = DB::table('tournament_match_score_sheets')
                ->where('tournament_id', $tournamentId)
                ->pluck('id');

            if ($sheetIds->isNotEmpty() && Schema::hasTable('tournament_match_score_sheet_players')) {
                $playerIds = DB::table('tournament_match_score_sheet_players')
                    ->whereIn('score_sheet_id', $sheetIds->all())
                    ->pluck('id');

                if ($playerIds->isNotEmpty() && Schema::hasTable('tournament_match_score_frames')) {
                    DB::table('tournament_match_score_frames')->whereIn('score_sheet_player_id', $playerIds->all())->delete();
                }

                DB::table('tournament_match_score_sheet_players')->whereIn('score_sheet_id', $sheetIds->all())->delete();
            }

            if ($sheetIds->isNotEmpty()) {
                DB::table('tournament_match_score_sheets')->whereIn('id', $sheetIds->all())->delete();
            }
        }

        ScoreImportBatch::query()->where('tournament_id', $tournamentId)->delete();
        DB::table('tournament_results')->where('tournament_id', $tournamentId)->delete();
        DB::table('game_scores')->where('tournament_id', $tournamentId)->delete();
        DB::table('tournament_round_lane_assignments')->where('tournament_id', $tournamentId)->delete();
        DB::table('tournament_entries')->where('tournament_id', $tournamentId)->delete();
        DB::table('tournament_participants')->where('tournament_id', $tournamentId)->delete();
    }

    private function insertParticipantsAndEntries(Tournament $tournament, array $entryRows, mixed $now): array
    {
        $participants = [];

        foreach ($this->activeEntries($entryRows) as $entry) {
            $licenseNo = $this->normalizeMaleLicense($entry['license']);
            $proBowlerId = $this->findProBowlerId($licenseNo);
            $lane = $this->laneNumber($entry['start_lane_label']);
            $laneSlot = $this->laneSlot($entry['start_lane_label']);

            $participantId = (int) DB::table('tournament_participants')->insertGetId([
                'tournament_id' => $tournament->id,
                'pro_bowler_license_no' => $licenseNo,
                'pro_bowler_id' => $proBowlerId,
                'participant_type' => 'pro',
                'display_name' => $entry['name'],
                'display_license_no' => $entry['license'],
                'gender' => 'M',
                'lane' => $lane,
                'lane_slot' => $laneSlot,
                'lane_label' => $entry['start_lane_label'],
                'box_no' => $this->boxNo($lane),
                'sort_order' => $entry['entry_no'],
                'source_note' => trim($entry['note'] . ($entry['seed_mark'] ? ' / seed' : '')),
                'display_dominant_arm' => null,
                'display_affiliation_name' => null,
                'display_equipment_contract' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $participants[$licenseNo] = [
                'participant_id' => $participantId,
                'pro_bowler_id' => $proBowlerId,
                'entry' => $entry,
            ];

            if ($proBowlerId) {
                DB::table('tournament_entries')->insert([
                    'pro_bowler_id' => $proBowlerId,
                    'tournament_id' => $tournament->id,
                    'status' => str_contains($entry['note'], 'BL') ? 'no_entry' : 'entry',
                    'is_paid' => false,
                    'shift_drawn' => true,
                    'lane_drawn' => true,
                    'shift' => null,
                    'lane' => $lane,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return $participants;
    }

    private function insertLaneAssignments(Tournament $tournament, array $entryRows, array $participantMap, mixed $now): int
    {
        $rows = [];

        foreach ($this->activeEntries($entryRows) as $entry) {
            $licenseNo = $this->normalizeMaleLicense($entry['license']);
            $participant = $participantMap[$licenseNo] ?? null;
            $lane = $this->laneNumber($entry['start_lane_label']);
            $laneSlot = $this->laneSlot($entry['start_lane_label']);

            $rows[] = [
                'tournament_id' => $tournament->id,
                'source_result_snapshot_id' => null,
                'tournament_participant_id' => $participant['participant_id'] ?? null,
                'pro_bowler_id' => $participant['pro_bowler_id'] ?? null,
                'stage' => self::STAGE_PRELIM,
                'round_label' => '予選1-8G',
                'game_from' => 1,
                'game_to' => 8,
                'seed_rank' => null,
                'pro_bowler_license_no' => $licenseNo,
                'display_license_no' => $entry['license'],
                'display_name' => $entry['name'],
                'period_label' => (string) $entry['period'],
                'dominant_arm' => null,
                'affiliation_display' => null,
                'source_total_pin' => null,
                'source_games' => null,
                'source_average' => null,
                'start_lane' => $lane,
                'lane_slot' => $laneSlot,
                'start_lane_label' => $entry['start_lane_label'],
                'box_no' => $this->boxNo($lane),
                'movement_direction' => 'right',
                'movement_box_step' => 2,
                'movement_boxes' => json_encode($this->movementBoxes($lane), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sort_order' => $entry['entry_no'],
                'note' => $entry['note'] !== '' ? $entry['note'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('tournament_round_lane_assignments')->insert($chunk);
        }

        return count($rows);
    }

    private function stageAndCommitScores(Tournament $tournament, string $sourceFilename, string $stage, array $rows): array
    {
        $batch = ScoreImportBatch::query()->create([
            'tournament_id' => $tournament->id,
            'import_type' => 'score_sheet_image',
            'source_filename' => $sourceFilename,
            'stored_path' => null,
            'status' => 'draft',
            'notes' => "default_stage={$stage} / default_gender=M",
        ]);

        $engineText = json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($engineText)) {
            throw new RuntimeException('failed to encode OCR payload rows');
        }

        $stageResult = $this->ocrBoundary->stageTextResult($tournament, $batch, $engineText, null, [
            'default_stage' => $stage,
            'default_gender' => 'M',
            'engine_name' => 'official_pdf_text_fixture',
            'replace_existing' => true,
            'source_filename' => $sourceFilename,
            'operation_action' => 'official_pdf_text_stage',
            'operation_message' => 'JPBA公式PDF抽出テキストを確認用ステージングへ変換しました。',
        ]);

        $commitResult = $this->commitService->commit($tournament, $batch->fresh());
        if (($stageResult['import_summary']['needs_review'] ?? 0) > 0 || ($commitResult['skipped'] ?? 0) > 0) {
            throw new RuntimeException("score import has review rows: {$sourceFilename}");
        }

        return [
            'batch_id' => (int) $batch->id,
            'payload_rows' => count($rows),
            'staged' => $stageResult['import_summary'],
            'adapter' => $stageResult['adapter_summary'],
            'committed' => $commitResult,
        ];
    }

    private function scorePayloadRows(array $resultRows, string $stage, int $gameCount): array
    {
        $payload = [];

        foreach ($resultRows as $row) {
            if (($row['is_blind'] ?? false) === true) {
                continue;
            }

            $games = [];
            for ($game = 1; $game <= $gameCount; $game++) {
                $score = $row['scores'][$game - 1] ?? null;
                if ($score === null) {
                    continue;
                }

                $games[] = [
                    'game_number' => $game,
                    'score' => $score,
                    'confidence' => 100,
                ];
            }

            $payload[] = [
                'license_number' => $this->normalizeMaleLicense($row['license']),
                'name' => $row['name'],
                'stage' => $stage,
                'gender' => 'M',
                'games' => $games,
                'confidence' => 100,
            ];
        }

        return $payload;
    }

    private function insertSnapshots(Tournament $tournament, array $data, mixed $now): array
    {
        $prelim4Rows = $data['prelim4'] !== [] ? $data['prelim4'] : $this->prelim4RowsFromPrelim8($data['prelim8']);

        return [
            'prelim_4g' => $this->insertSnapshot($tournament, 'prelim_4g', '予選前半4G成績', self::STAGE_PRELIM, 4, 0, $this->snapshotRowsFromPrelim4($prelim4Rows), false, $now),
            'prelim_total' => $this->insertSnapshot($tournament, 'prelim_total', '予選8Gトータルピン成績', self::STAGE_PRELIM, 8, 0, $this->snapshotRowsFromPrelim8($data['prelim8']), false, $now),
            'semifinal_total' => $this->insertSnapshot($tournament, 'semifinal_total', '準決勝4G・通算12Gトータルピン成績', self::STAGE_SEMIFINAL, 12, 8, $this->snapshotRowsFromSemifinal($data['semifinal']), false, $now),
            'shootout_final' => $this->insertSnapshot($tournament, 'shootout_final', '決勝シュートアウト最終成績', self::STAGE_SHOOTOUT, 12, 12, $this->snapshotRowsFromFinalAwards($data), true, $now),
        ];
    }

    private function insertSnapshot(Tournament $tournament, string $code, string $name, string $stage, int $games, int $carryGames, array $rows, bool $isFinal, mixed $now): array
    {
        DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournament->id)
            ->where('result_code', $code)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $snapshotId = (int) DB::table('tournament_result_snapshots')->insertGetId([
            'tournament_id' => $tournament->id,
            'result_code' => $code,
            'result_name' => $name,
            'result_type' => 'total_pin',
            'stage_name' => $stage,
            'gender' => 'M',
            'shift' => null,
            'games_count' => $games,
            'carry_game_count' => $carryGames,
            'carry_stage_names' => $carryGames > 0 ? json_encode([self::STAGE_PRELIM], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'calculation_definition' => json_encode([
                'source' => 'JPBA official PDF text import',
                'result_code' => $code,
                'stage' => $stage,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'reflected_at' => $now,
            'reflected_by' => null,
            'is_final' => $isFinal,
            'is_published' => true,
            'is_current' => true,
            'notes' => 'JPBA公式PDF抽出テキストから復元',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $insertRows = [];
        foreach ($rows as $row) {
            $licenseNo = $this->normalizeMaleLicense($row['license']);
            $proBowlerId = $this->findProBowlerId($licenseNo);

            $insertRows[] = [
                'snapshot_id' => $snapshotId,
                'ranking' => $row['ranking'],
                'pro_bowler_id' => $proBowlerId,
                'pro_bowler_license_no' => $licenseNo,
                'amateur_name' => $proBowlerId ? null : $row['name'],
                'display_name' => $row['name'],
                'gender' => 'M',
                'shift' => null,
                'entry_number' => null,
                'scratch_pin' => $row['scratch_pin'],
                'carry_pin' => $row['carry_pin'],
                'total_pin' => $row['total_pin'],
                'games' => $row['games'],
                'average' => $row['average'],
                'tie_break_value' => $row['tie_break_value'] ?? null,
                'points' => $row['points'] ?? null,
                'prize_money' => $row['prize_money'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insertRows, 500) as $chunk) {
            DB::table('tournament_result_snapshot_rows')->insert($chunk);
        }

        return [
            'snapshot_id' => $snapshotId,
            'row_count' => count($insertRows),
        ];
    }

    private function insertShootoutGameScores(Tournament $tournament, array $semifinalRows, mixed $now): int
    {
        $byRank = [];
        foreach ($semifinalRows as $row) {
            if ((int) $row['rank'] <= 8) {
                $byRank[(int) $row['rank']] = $row;
            }
        }

        $rows = [];
        foreach ($this->officialShootoutScores() as $scoreRow) {
            $source = $byRank[$scoreRow['seed']] ?? null;
            if (!$source) {
                continue;
            }

            $licenseNo = $this->normalizeMaleLicense($source['license']);
            $rows[] = [
                'tournament_id' => $tournament->id,
                'stage' => self::STAGE_SHOOTOUT,
                'license_number' => $licenseNo,
                'name' => $source['name'],
                'entry_number' => $scoreRow['entry_number'],
                'game_number' => 1,
                'score' => $scoreRow['score'],
                'shift' => null,
                'gender' => 'M',
                'pro_bowler_id' => $this->findProBowlerId($licenseNo),
                'tournament_participant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('game_scores')->insert($rows);
        }

        return count($rows);
    }

    private function validateShootout(array $data): array
    {
        $seedEntries = [];
        foreach (array_slice($data['semifinal'], 0, 8) as $row) {
            $licenseNo = $this->normalizeMaleLicense($row['license']);
            $seedEntries[] = [
                'seed' => (int) $row['rank'],
                'display_name' => $row['name'],
                'pro_bowler_id' => $this->findProBowlerId($licenseNo),
                'pro_bowler_license_no' => $licenseNo,
                'participant_key' => 'license:' . $licenseNo,
                'source_ranking' => (int) $row['rank'],
                'total_pin' => $row['total_12g'],
                'games' => 12,
                'average' => $row['average_12g'],
            ];
        }

        $shootout = $this->shootoutService->buildStandard8($seedEntries, $this->shootoutMatchScores());
        $standings = $this->shootoutService->buildFinalStandings($shootout);
        $standingLicenses = array_map(
            fn (array $row): string => $this->licenseTail((string) ($row['node']['pro_bowler_license_no'] ?? '')),
            $standings
        );
        $officialLicenses = array_map(fn (array $row): string => $row['license'], $data['final_awards']);

        if ($standingLicenses !== $officialLicenses) {
            throw new RuntimeException('shootout standings do not match official final awards');
        }

        return [
            'winner_name' => $shootout['summary']['winner_name'] ?? null,
            'completed_match_count' => $shootout['summary']['completed_match_count'] ?? null,
            'standings' => array_map(fn (array $row): array => [
                'ranking' => $row['ranking'],
                'name' => $row['node']['display_name'] ?? null,
                'shootout_pin' => $row['shootout_pin'],
                'shootout_games' => $row['shootout_games'],
            ], $standings),
        ];
    }

    private function insertTournamentResults(Tournament $tournament, array $data, mixed $now): int
    {
        $semiByLicense = [];
        foreach ($data['semifinal'] as $row) {
            $semiByLicense[$row['license']] = $row;
        }

        $rows = [];
        foreach ($data['final_awards'] as $award) {
            $semi = $semiByLicense[$award['license']] ?? null;
            if (!$semi) {
                throw new RuntimeException("missing semifinal row for final award: {$award['license']}");
            }

            $licenseNo = $this->normalizeMaleLicense($award['license']);
            $proBowlerId = $this->findProBowlerId($licenseNo);

            $rows[] = [
                'pro_bowler_license_no' => $licenseNo,
                'tournament_id' => $tournament->id,
                'ranking' => $award['ranking'],
                'points' => $award['points'],
                'total_pin' => $semi['total_12g'],
                'games' => 12,
                'average' => $semi['average_12g'],
                'prize_money' => $award['prize_money'],
                'ranking_year' => 2026,
                'amateur_name' => $proBowlerId ? null : $award['name'],
                'pro_bowler_id' => $proBowlerId,
                'affiliation_display' => $award['affiliation'],
                'award_points' => $award['award_points'],
                'step_points' => $award['step_points'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('tournament_results')->insert($rows);

        return count($rows);
    }

    private function snapshotRowsFromPrelim4(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [
            'ranking' => $row['front_rank'],
            'license' => $row['license'],
            'name' => $row['name'],
            'scratch_pin' => $row['front_total'],
            'carry_pin' => 0,
            'total_pin' => $row['front_total'],
            'games' => 4,
            'average' => $row['front_average'],
        ], array_filter($rows, fn (array $row): bool => !$row['is_blind'])));
    }

    private function snapshotRowsFromPrelim8(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [
            'ranking' => $row['rank'],
            'license' => $row['license'],
            'name' => $row['name'],
            'scratch_pin' => $row['total'],
            'carry_pin' => 0,
            'total_pin' => $row['total'],
            'games' => 8,
            'average' => $row['average'],
        ], array_filter($rows, fn (array $row): bool => !$row['is_blind'])));
    }

    private function snapshotRowsFromSemifinal(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [
            'ranking' => $row['rank'],
            'license' => $row['license'],
            'name' => $row['name'],
            'scratch_pin' => $row['semi_total'],
            'carry_pin' => $row['prelim_total'],
            'total_pin' => $row['total_12g'],
            'games' => 12,
            'average' => $row['average_12g'],
            'points' => 25 - (int) $row['rank'],
        ], $rows));
    }

    private function snapshotRowsFromFinalAwards(array $data): array
    {
        $semiByLicense = [];
        foreach ($data['semifinal'] as $row) {
            $semiByLicense[$row['license']] = $row;
        }

        $shootoutPins = $this->shootoutPinsByLicense($data['semifinal']);
        $rows = [];
        foreach ($data['final_awards'] as $award) {
            $semi = $semiByLicense[$award['license']] ?? null;
            if (!$semi) {
                continue;
            }

            $rows[] = [
                'ranking' => $award['ranking'],
                'license' => $award['license'],
                'name' => $award['name'],
                'scratch_pin' => $shootoutPins[$award['license']]['pin'] ?? 0,
                'carry_pin' => $semi['total_12g'],
                'total_pin' => $semi['total_12g'],
                'games' => 12,
                'average' => $semi['average_12g'],
                'points' => $award['points'],
                'prize_money' => $award['prize_money'],
            ];
        }

        return $rows;
    }

    private function prelim4RowsFromPrelim8(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => array_merge($row, [
            'front_average' => round($row['front_total'] / 4, 2),
        ]), $rows));
    }

    private function activeEntries(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row): bool {
            return !str_contains($row['note'], 'アソビックス');
        }));
    }

    private function parseEntryRows(string $text): array
    {
        $rows = [];
        foreach ($this->normalizedLines($text) as $line) {
            if (preg_match('/^(?<lane>\d+L-\d)\s+(?<entry_no>\d+)\s+(?<between>.*?)(?<license>\d{3,4})\s+(?<name>.+?)\s+(?<period>\d{1,2})(?:\s+(?<note>.*))?$/u', $line, $m) !== 1) {
                continue;
            }

            $rows[] = [
                'entry_no' => (int) $m['entry_no'],
                'start_lane_label' => $m['lane'],
                'license' => $m['license'],
                'seed_mark' => str_contains(' ' . trim($m['between']) . ' ', ' S '),
                'name' => $this->normalizeName($m['name']),
                'period' => (int) $m['period'],
                'note' => trim($m['note'] ?? ''),
            ];
        }

        return $rows;
    }

    private function parsePrelim4Rows(string $text): array
    {
        $rows = [];
        $tailPattern = '/\s+(?<g1>\d{1,3}|BL)\s+(?<g2>\d{1,3}|BL)\s+(?<g3>\d{1,3}|BL)\s+(?<g4>\d{1,3}|BL)\s+(?<front>\d+)\s+(?<front_rank>\d+|BL)\s+(?:0\s+)?(?<total>[\d,]+)\s+(?<avg>\d+\.\d+)$/u';

        foreach ($this->normalizedLines($text) as $line) {
            if (preg_match($tailPattern, $line, $tail, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $prefix = substr($line, 0, $tail[0][1]);
            $base = $this->parseResultPrefix($prefix);
            if ($base === null) {
                continue;
            }

            $scores = $this->scoreValues($tail, 4);
            $base['scores'] = $scores;
            $base['front_total'] = (int) $tail['front'][0];
            $base['front_rank'] = $tail['front_rank'][0] === 'BL' ? null : (int) $tail['front_rank'][0];
            $base['front_average'] = (float) $tail['avg'][0];
            $rows[] = $base;
        }

        return $rows;
    }

    private function parsePrelim8Rows(string $text): array
    {
        $rows = [];
        $tailPattern = '/\s+(?<g1>\d{1,3}|BL)\s+(?<g2>\d{1,3}|BL)\s+(?<g3>\d{1,3}|BL)\s+(?<g4>\d{1,3}|BL)\s+(?<front>\d+)\s+(?<front_rank>\d+|BL)\s+(?<g5>\d{1,3}|BL)\s+(?<g6>\d{1,3}|BL)\s+(?<g7>\d{1,3}|BL)\s+(?<g8>\d{1,3}|BL)\s+(?<back>\d+)\s+(?<total>[\d,]+)\s+(?<avg>\d+\.\d+)$/u';

        foreach ($this->normalizedLines($text) as $line) {
            if (preg_match($tailPattern, $line, $tail, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $prefix = substr($line, 0, $tail[0][1]);
            $base = $this->parseResultPrefix($prefix);
            if ($base === null) {
                continue;
            }

            $base['scores'] = $this->scoreValues($tail, 8);
            $base['front_total'] = (int) $tail['front'][0];
            $base['front_rank'] = $tail['front_rank'][0] === 'BL' ? null : (int) $tail['front_rank'][0];
            $base['back_total'] = (int) $tail['back'][0];
            $base['total'] = $this->intValue($tail['total'][0]);
            $base['average'] = (float) $tail['avg'][0];
            $rows[] = $base;
        }

        return $rows;
    }

    private function parseSemifinalRows(string $text): array
    {
        $rows = [];
        $tailPattern = '/\s+(?<prelim_total>[\d,]+)\s+(?<prelim_avg>\d+\.\d+)\s+(?<prelim_rank>\d+)\s+(?<g1>\d{1,3})\s+(?<g2>\d{1,3})\s+(?<g3>\d{1,3})\s+(?<g4>\d{1,3})\s+(?<semi_total>\d+)\s+(?<semi_avg>\d+\.\d+)\s+(?<total>[\d,]+)\s+(?<avg>\d+\.\d+)$/u';

        foreach ($this->normalizedLines($text) as $line) {
            if (preg_match($tailPattern, $line, $tail, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $prefix = substr($line, 0, $tail[0][1]);
            $base = $this->parseResultPrefix($prefix);
            if ($base === null) {
                continue;
            }

            $base['prelim_total'] = $this->intValue($tail['prelim_total'][0]);
            $base['prelim_average'] = (float) $tail['prelim_avg'][0];
            $base['prelim_rank'] = (int) $tail['prelim_rank'][0];
            $base['scores'] = $this->scoreValues($tail, 4);
            $base['semi_total'] = (int) $tail['semi_total'][0];
            $base['semi_average'] = (float) $tail['semi_avg'][0];
            $base['total_12g'] = $this->intValue($tail['total'][0]);
            $base['average_12g'] = (float) $tail['avg'][0];
            $rows[] = $base;
        }

        return $rows;
    }

    private function parseFinalAwardRows(string $text): array
    {
        $rows = [];
        $pattern = '/^(?<rank_label>優\s*勝|第\s*(?<rank>\d+)\s*位)\s+(?<seed>S\s+)?(?<license>\d{3,4})\s+(?<name>.+?)\s+(?<period>\d{1,2})\s+(?<affiliation>.+?)\s+(?<points>\d+)\s+(?<award_points>\d+)\s+(?<step_points>\d+)\s+(?<prize>[\d,]+)$/u';

        foreach ($this->normalizedLines($text) as $line) {
            if (preg_match($pattern, $line, $m) !== 1) {
                continue;
            }

            $rows[] = [
                'ranking' => ($m['rank'] ?? '') !== '' ? (int) $m['rank'] : 1,
                'license' => $m['license'],
                'seed_mark' => trim($m['seed'] ?? '') === 'S',
                'name' => $this->normalizeName($m['name']),
                'period' => (int) $m['period'],
                'affiliation' => trim($m['affiliation']),
                'points' => (int) $m['points'],
                'award_points' => (int) $m['award_points'],
                'step_points' => (int) $m['step_points'],
                'prize_money' => $this->intValue($m['prize']),
            ];
        }

        return $rows;
    }

    private function parseResultPrefix(string $prefix): ?array
    {
        $prefix = trim($prefix);
        if (preg_match('/^(?<rank>\d+|BL)\s+(?<between>.*?)(?<license>\d{3,4})\s+(?<meta>.+)$/u', $prefix, $m) !== 1) {
            return null;
        }

        if (preg_match('/^(?<name>.+?)\s+(?<period>\d{1,2})\s+(?<arm>右両手|左両手|右|左)\s+(?<affiliation>.*)$/u', trim($m['meta']), $meta) !== 1) {
            return null;
        }

        $rank = $m['rank'];
        $between = trim($m['between']);
        $stepPoint = null;
        foreach (preg_split('/\s+/u', $between) ?: [] as $token) {
            if (preg_match('/^(\d+)P$/u', $token, $point) === 1) {
                $stepPoint = (int) $point[1];
            }
        }

        return [
            'rank' => $rank === 'BL' ? null : (int) $rank,
            'is_blind' => $rank === 'BL',
            'seed_mark' => str_contains(' ' . $between . ' ', ' S '),
            'step_point' => $stepPoint,
            'license' => $m['license'],
            'name' => $this->normalizeName($meta['name']),
            'period' => (int) $meta['period'],
            'arm' => $meta['arm'],
            'affiliation' => trim($meta['affiliation']),
        ];
    }

    private function normalizedLines(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $normalized = [];

        foreach ($lines as $line) {
            $line = preg_replace('/^\xEF\xBB\xBF/u', '', $line) ?? $line;
            $line = mb_convert_kana($line, 'asKV', 'UTF-8');
            $line = str_replace("\u{3000}", ' ', $line);
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);

            if ($line !== '') {
                $normalized[] = $line;
            }
        }

        return $normalized;
    }

    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function scoreValues(array $matches, int $gameCount): array
    {
        $scores = [];
        for ($game = 1; $game <= $gameCount; $game++) {
            $value = $matches['g' . $game][0] ?? null;
            $scores[] = $value === 'BL' ? null : (int) $value;
        }

        return $scores;
    }

    private function intValue(string $value): int
    {
        return (int) str_replace(',', '', $value);
    }

    private function normalizeMaleLicense(string $license): string
    {
        $digits = preg_replace('/\D+/', '', $license) ?: $license;

        return 'M' . str_pad($digits, 8, '0', STR_PAD_LEFT);
    }

    private function licenseTail(string $license): string
    {
        $digits = preg_replace('/\D+/', '', $license) ?: '';

        return ltrim(substr($digits, -4), '0') ?: '0';
    }

    private function findProBowlerId(string $licenseNo): ?int
    {
        $id = DB::table('pro_bowlers')
            ->whereRaw('upper(license_no) = ?', [strtoupper($licenseNo)])
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        $digits = preg_replace('/\D+/', '', $licenseNo) ?: '';
        if ($digits === '') {
            return null;
        }

        $last4 = str_pad(substr($digits, -4), 4, '0', STR_PAD_LEFT);
        $rows = DB::table('pro_bowlers')
            ->whereRaw("right(regexp_replace(upper(license_no), '[^0-9]', '', 'g'), 4) = ?", [$last4])
            ->whereRaw("upper(left(license_no, 1)) = 'M'")
            ->pluck('id');

        return $rows->count() === 1 ? (int) $rows->first() : null;
    }

    private function laneNumber(string $laneLabel): ?int
    {
        return preg_match('/^(\d+)L-/u', $laneLabel, $m) === 1 ? (int) $m[1] : null;
    }

    private function laneSlot(string $laneLabel): ?int
    {
        return preg_match('/L-(\d)$/u', $laneLabel, $m) === 1 ? (int) $m[1] : null;
    }

    private function boxNo(?int $lane): ?int
    {
        if ($lane === null) {
            return null;
        }

        $oddLane = $lane % 2 === 0 ? $lane - 1 : $lane;

        return (int) floor(($oddLane - 1) / 2) + 1;
    }

    private function movementBoxes(?int $startLane): array
    {
        if ($startLane === null) {
            return [];
        }

        $oddLanes = range(3, 35, 2);
        $oddLane = $startLane % 2 === 0 ? $startLane - 1 : $startLane;
        $index = array_search($oddLane, $oddLanes, true);
        if ($index === false) {
            return [];
        }

        $boxes = [];
        $count = count($oddLanes);
        for ($game = 1; $game <= 8; $game++) {
            $lane = $oddLanes[($index + (($game - 1) * 2)) % $count];
            $boxes[] = [
                'game' => $game,
                'lane_pair' => $lane . 'L-' . ($lane + 1) . 'L',
            ];
        }

        return $boxes;
    }

    private function shootoutMatchScores(): array
    {
        $scores = [];
        foreach ($this->officialShootoutScores() as $row) {
            if (preg_match('/^SO:(SO[123]):([ABCD])$/', $row['entry_number'], $m) !== 1) {
                continue;
            }

            $scores[$m[1]][$m[2]] = ['score' => $row['score']];
        }

        return $scores;
    }

    private function shootoutPinsByLicense(array $semifinalRows): array
    {
        $bySeed = [];
        foreach ($semifinalRows as $row) {
            $bySeed[(int) $row['rank']] = $row;
        }

        $pins = [];
        foreach ($this->officialShootoutScores() as $score) {
            $seed = $score['seed'];
            if (!isset($bySeed[$seed])) {
                continue;
            }

            $license = $bySeed[$seed]['license'];
            if (!isset($pins[$license])) {
                $pins[$license] = ['pin' => 0, 'games' => 0];
            }

            $pins[$license]['pin'] += $score['score'];
            $pins[$license]['games']++;
        }

        return $pins;
    }

    private function officialShootoutScores(): array
    {
        return [
            ['entry_number' => 'SO:SO1:A', 'seed' => 5, 'score' => 179],
            ['entry_number' => 'SO:SO1:B', 'seed' => 6, 'score' => 257],
            ['entry_number' => 'SO:SO1:C', 'seed' => 7, 'score' => 193],
            ['entry_number' => 'SO:SO1:D', 'seed' => 8, 'score' => 224],
            ['entry_number' => 'SO:SO2:A', 'seed' => 2, 'score' => 269],
            ['entry_number' => 'SO:SO2:B', 'seed' => 3, 'score' => 201],
            ['entry_number' => 'SO:SO2:C', 'seed' => 4, 'score' => 233],
            ['entry_number' => 'SO:SO2:D', 'seed' => 6, 'score' => 188],
            ['entry_number' => 'SO:SO3:A', 'seed' => 1, 'score' => 197],
            ['entry_number' => 'SO:SO3:B', 'seed' => 2, 'score' => 263],
        ];
    }
}

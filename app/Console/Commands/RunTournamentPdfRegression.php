<?php

namespace App\Console\Commands;

use App\Http\Controllers\TournamentResultController;
use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentResult;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunTournamentPdfRegression extends Command
{
    protected $signature = 'tournament:pdf-regression {--json : Output the regression result as JSON}';

    protected $description = 'Run tournament result PDF regression checks for each PDF mode.';

    public function handle(): int
    {
        $results = [];

        $existingCases = [
            [
                'case' => 'season_trial_existing',
                'mode' => 'season_trial',
                'tournament' => Tournament::query()
                    ->where('title_category', 'season_trial')
                    ->where('result_flow_type', 'like', '%shootout%')
                    ->orderBy('id')
                    ->first(),
            ],
            [
                'case' => 'season_trial_gender_snapshot_existing',
                'mode' => 'season_trial_gender_snapshot',
                'tournament' => Tournament::query()
                    ->where('title_category', 'season_trial')
                    ->where('result_flow_type', 'like', '%shootout%')
                    ->whereExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('tournament_result_snapshots')
                            ->whereColumn('tournament_result_snapshots.tournament_id', 'tournaments.id')
                            ->where('tournament_result_snapshots.is_current', true)
                            ->whereNotNull('tournament_result_snapshots.gender');
                    })
                    ->orderByDesc('id')
                    ->first(),
            ],
            [
                'case' => 'round_robin_step_ladder_existing',
                'mode' => 'standard_with_step_ladder',
                'tournament' => Tournament::query()
                    ->where('result_flow_type', 'prelim_to_rr_to_final')
                    ->orderBy('id')
                    ->first(),
            ],
        ];

        foreach ($existingCases as $case) {
            if (!$case['tournament']) {
                $results[] = $this->missingResult($case['case'], $case['mode'], '対象大会がDBにありません。');
                continue;
            }

            $results[] = $this->checkPdf(
                caseName: $case['case'],
                expectedMode: $case['mode'],
                tournament: $case['tournament'],
                isFixture: false
            );
        }

        $singleEliminationExisting = Tournament::query()
            ->where('result_flow_type', 'like', '%single_elimination%')
            ->orderBy('id')
            ->first();

        if ($singleEliminationExisting) {
            $results[] = $this->checkPdf(
                caseName: 'single_elimination_existing',
                expectedMode: 'single_elimination',
                tournament: $singleEliminationExisting,
                isFixture: false
            );
        }

        DB::beginTransaction();

        try {
            $standardTournament = $this->createStandardFixture();
            $results[] = $this->checkPdf('standard_fixture', 'standard', $standardTournament, true);

            $shootoutTournament = $this->createShootoutFixture();
            $results[] = $this->checkPdf('shootout_fixture', 'shootout', $shootoutTournament, true);

            $singleEliminationTournament = $this->createSingleEliminationFixture();
            $results[] = $this->checkPdf('single_elimination_fixture', 'single_elimination', $singleEliminationTournament, true);
        } catch (Throwable $e) {
            $results[] = [
                'case' => 'fixture_setup',
                'mode' => 'fixture',
                'status' => 'FAIL',
                'message' => $e->getMessage(),
            ];
        } finally {
            DB::rollBack();
        }

        $failed = collect($results)->contains(fn (array $result) => ($result['status'] ?? '') !== 'OK');

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['case', 'mode', 'tournament_id', 'fixture', 'status', 'bytes', 'message'],
                array_map(fn (array $result) => [
                    $result['case'] ?? '',
                    $result['mode'] ?? '',
                    $result['tournament_id'] ?? '-',
                    !empty($result['fixture']) ? 'yes' : 'no',
                    $result['status'] ?? '',
                    $result['bytes'] ?? '-',
                    $result['message'] ?? '',
                ], $results)
            );
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function checkPdf(string $caseName, string $expectedMode, Tournament $tournament, bool $isFixture): array
    {
        $warnings = [];
        set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$warnings): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            $warnings[] = $message . ' (' . $file . ':' . $line . ')';

            return true;
        });

        try {
            $controller = app(TournamentResultController::class);
            $response = $controller->exportTournamentPdf($tournament);
            $content = (string) $response->getContent();
        } catch (Throwable $e) {
            return [
                'case' => $caseName,
                'mode' => $expectedMode,
                'tournament_id' => $tournament->id,
                'fixture' => $isFixture,
                'status' => 'FAIL',
                'bytes' => 0,
                'message' => $e->getMessage(),
            ];
        } finally {
            restore_error_handler();
        }

        $bytes = strlen($content);
        if (!str_starts_with($content, '%PDF')) {
            return [
                'case' => $caseName,
                'mode' => $expectedMode,
                'tournament_id' => $tournament->id,
                'fixture' => $isFixture,
                'status' => 'FAIL',
                'bytes' => $bytes,
                'message' => 'PDF header is missing.',
            ];
        }

        if (!empty($warnings)) {
            return [
                'case' => $caseName,
                'mode' => $expectedMode,
                'tournament_id' => $tournament->id,
                'fixture' => $isFixture,
                'status' => 'FAIL',
                'bytes' => $bytes,
                'message' => implode(' / ', array_slice($warnings, 0, 3)),
            ];
        }

        $shootoutPayloadMessage = $this->validateShootoutPdfPayload($controller, $tournament);
        if ($shootoutPayloadMessage !== null) {
            return [
                'case' => $caseName,
                'mode' => $expectedMode,
                'tournament_id' => $tournament->id,
                'fixture' => $isFixture,
                'status' => 'FAIL',
                'bytes' => $bytes,
                'message' => $shootoutPayloadMessage,
            ];
        }

        return [
            'case' => $caseName,
            'mode' => $expectedMode,
            'tournament_id' => $tournament->id,
            'fixture' => $isFixture,
            'status' => 'OK',
            'bytes' => $bytes,
            'message' => 'PDF generated.',
        ];
    }

    private function validateShootoutPdfPayload(TournamentResultController $controller, Tournament $tournament): ?string
    {
        $flowType = trim((string) ($tournament->result_flow_type ?? ''));
        if (!str_contains($flowType, 'shootout')) {
            return null;
        }

        try {
            $method = new \ReflectionMethod($controller, 'buildShootoutPdfData');
            $method->setAccessible(true);
            $payload = $method->invoke($controller, $tournament);
        } catch (Throwable $e) {
            return 'Shootout PDF payload failed: ' . $e->getMessage();
        }

        if (!is_array($payload)) {
            return 'Shootout PDF payload is missing.';
        }

        $seedCount = count((array) ($payload['seed_rows'] ?? []));
        $matchCount = count((array) ($payload['matches'] ?? []));
        if ($seedCount < 8 || $matchCount < 3) {
            return "Shootout PDF payload is incomplete. seeds={$seedCount}, matches={$matchCount}";
        }

        return null;
    }

    private function missingResult(string $caseName, string $mode, string $message): array
    {
        return [
            'case' => $caseName,
            'mode' => $mode,
            'tournament_id' => null,
            'fixture' => false,
            'status' => 'FAIL',
            'bytes' => 0,
            'message' => $message,
        ];
    }

    private function createStandardFixture(): Tournament
    {
        $tournament = $this->createTournament([
            'name' => 'PDF回帰 標準成績 fixture',
            'title_category' => 'normal',
            'result_flow_type' => 'legacy_standard',
        ]);

        $participants = $this->participants(4);
        $this->createTournamentResults($tournament, $participants);
        $this->createSnapshot($tournament, 'final_total', '最終成績', $participants, true);

        return $tournament->refresh();
    }

    private function createShootoutFixture(): Tournament
    {
        $tournament = $this->createTournament([
            'name' => 'PDF回帰 シュートアウト fixture',
            'title_category' => 'normal',
            'result_flow_type' => 'prelim_to_shootout_to_final',
            'shootout_qualifier_count' => 8,
            'shootout_seed_source_result_code' => 'prelim_total',
            'shootout_settings' => [],
        ]);

        $participants = $this->participants(8);
        $this->createSnapshot($tournament, 'prelim_total', '予選通算成績', $participants, false);
        $this->insertShootoutScores($tournament, $participants);

        $finalOrder = [2, 1, 3, 4, 8, 5, 6, 7];
        $this->createTournamentResults($tournament, $this->participantsBySeed($participants, $finalOrder));
        $this->createSnapshot($tournament, 'shootout_final', 'シュートアウト最終成績', $this->participantsBySeed($participants, $finalOrder), true);

        return $tournament->refresh();
    }

    private function createSingleEliminationFixture(): Tournament
    {
        $tournament = $this->createTournament([
            'name' => 'PDF回帰 シングルエリミネーション fixture',
            'title_category' => 'normal',
            'result_flow_type' => 'prelim_to_single_elimination_to_final',
            'single_elimination_qualifier_count' => 4,
            'single_elimination_seed_source_result_code' => 'prelim_total',
            'single_elimination_seed_policy' => 'standard',
            'single_elimination_seed_settings' => [
                'lane_settings' => [
                    'rounds' => [
                        1 => ['start_lane' => 21, 'step' => 2, 'width' => 2],
                        2 => ['start_lane' => 25, 'step' => 2, 'width' => 2],
                    ],
                ],
            ],
        ]);

        $participants = $this->participants(4);
        $this->createSnapshot($tournament, 'prelim_total', '予選通算成績', $participants, false);
        $this->insertSingleEliminationScores($tournament, $participants);

        $finalOrder = [1, 3, 2, 4];
        $rankings = [1, 2, 3, 3];
        $this->createTournamentResults($tournament, $this->participantsBySeed($participants, $finalOrder), $rankings);
        $this->createSnapshot($tournament, 'single_elimination_final', 'トーナメント最終成績', $this->participantsBySeed($participants, $finalOrder), true, $rankings);

        return $tournament->refresh();
    }

    private function createTournament(array $attributes): Tournament
    {
        return Tournament::query()->create(array_merge([
            'name' => 'PDF回帰 fixture',
            'year' => 2026,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
            'venue_name' => 'PDF回帰テスト会場',
            'venue_address' => '東京都テスト区1-1-1',
            'gender' => 'X',
            'official_type' => 'official',
            'entry_start' => '2026-06-01 00:00:00',
            'entry_end' => '2026-06-30 23:59:59',
            'lane_from' => 1,
            'lane_to' => 32,
            'use_shift_draw' => false,
            'use_lane_draw' => false,
            'lane_assignment_mode' => 'single_lane',
            'box_player_count' => 4,
            'accept_shift_preference' => false,
            'result_carry_preset' => 'default',
            'result_carry_settings' => [],
        ], $attributes));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function participants(int $count): array
    {
        $bowlers = ProBowler::query()
            ->whereNotNull('license_no')
            ->whereNotNull('name_kanji')
            ->orderBy('id')
            ->limit($count)
            ->get(['id', 'license_no', 'name_kanji', 'sex']);

        $participants = [];
        for ($i = 1; $i <= $count; $i++) {
            $bowler = $bowlers->get($i - 1);

            $participants[] = [
                'seed' => $i,
                'pro_bowler_id' => $bowler?->id,
                'license_no' => $bowler?->license_no ?: sprintf('PDFREG%04d', $i),
                'name' => $bowler?->name_kanji ?: ('PDF回帰 選手' . $i),
                'gender' => ((int) ($bowler?->sex ?? 1)) === 2 ? 'F' : 'M',
                'total_pin' => 2200 - ($i * 10),
                'games' => 8,
            ];
        }

        return $participants;
    }

    /**
     * @param array<int,array<string,mixed>> $participants
     * @param array<int,int> $seedOrder
     * @return array<int,array<string,mixed>>
     */
    private function participantsBySeed(array $participants, array $seedOrder): array
    {
        $bySeed = collect($participants)->keyBy('seed');

        return collect($seedOrder)
            ->map(fn (int $seed) => $bySeed->get($seed))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int,array<string,mixed>> $participants
     * @param array<int,int>|null $rankings
     */
    private function createTournamentResults(Tournament $tournament, array $participants, ?array $rankings = null): void
    {
        foreach (array_values($participants) as $index => $participant) {
            $ranking = $rankings[$index] ?? ($index + 1);
            $games = max(1, (int) ($participant['games'] ?? 8));
            $totalPin = (int) ($participant['total_pin'] ?? (2200 - ($index * 10)));

            TournamentResult::query()->create([
                'tournament_id' => $tournament->id,
                'pro_bowler_id' => $participant['pro_bowler_id'] ?? null,
                'pro_bowler_license_no' => $participant['license_no'] ?? null,
                'amateur_name' => empty($participant['pro_bowler_id']) ? ($participant['name'] ?? null) : null,
                'ranking' => $ranking,
                'points' => max(0, 20 - ($ranking * 2)),
                'award_points' => max(0, 10 - $ranking),
                'step_points' => 0,
                'total_pin' => $totalPin,
                'games' => $games,
                'average' => round($totalPin / $games, 2),
                'prize_money' => max(0, 50000 - ($ranking * 5000)),
                'ranking_year' => 2026,
                'affiliation_display' => 'PDF回帰テスト',
            ]);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $participants
     * @param array<int,int>|null $rankings
     */
    private function createSnapshot(Tournament $tournament, string $code, string $name, array $participants, bool $isFinal, ?array $rankings = null): TournamentResultSnapshot
    {
        $snapshot = TournamentResultSnapshot::query()->create([
            'tournament_id' => $tournament->id,
            'result_code' => $code,
            'result_name' => $name,
            'result_type' => 'total_pin',
            'stage_name' => $isFinal ? '最終成績' : '予選',
            'gender' => null,
            'shift' => null,
            'games_count' => 8,
            'carry_game_count' => 0,
            'carry_stage_names' => [],
            'calculation_definition' => ['source' => 'pdf_regression_fixture'],
            'reflected_at' => now(),
            'reflected_by' => null,
            'is_final' => $isFinal,
            'is_published' => true,
            'is_current' => true,
            'notes' => 'PDF回帰fixture',
        ]);

        foreach (array_values($participants) as $index => $participant) {
            $ranking = $rankings[$index] ?? ($index + 1);
            $games = max(1, (int) ($participant['games'] ?? 8));
            $totalPin = (int) ($participant['total_pin'] ?? (2200 - ($index * 10)));

            TournamentResultSnapshotRow::query()->create([
                'snapshot_id' => $snapshot->id,
                'ranking' => $ranking,
                'pro_bowler_id' => $participant['pro_bowler_id'] ?? null,
                'pro_bowler_license_no' => $participant['license_no'] ?? null,
                'amateur_name' => empty($participant['pro_bowler_id']) ? ($participant['name'] ?? null) : null,
                'display_name' => $participant['name'] ?? ('PDF回帰 選手' . ($index + 1)),
                'gender' => $participant['gender'] ?? null,
                'shift' => null,
                'entry_number' => 'PDF-' . ($index + 1),
                'scratch_pin' => $totalPin,
                'carry_pin' => 0,
                'total_pin' => $totalPin,
                'games' => $games,
                'average' => round($totalPin / $games, 3),
                'tie_break_value' => (float) (1000 - $ranking),
                'points' => null,
                'prize_money' => null,
            ]);
        }

        return $snapshot;
    }

    /**
     * @param array<int,array<string,mixed>> $participants
     */
    private function insertShootoutScores(Tournament $tournament, array $participants): void
    {
        $matches = [
            ['SO1', 1, ['A' => [5, 210], 'B' => [6, 220], 'C' => [7, 230], 'D' => [8, 240]]],
            ['SO2', 2, ['A' => [2, 250], 'B' => [3, 235], 'C' => [4, 225], 'D' => [8, 245]]],
            ['SO3', 3, ['A' => [1, 245], 'B' => [2, 255]]],
        ];

        $this->insertMatchScores($tournament, $participants, 'シュートアウト', 'SO', $matches);
    }

    /**
     * @param array<int,array<string,mixed>> $participants
     */
    private function insertSingleEliminationScores(Tournament $tournament, array $participants): void
    {
        $matches = [
            ['R1-M1', 1, ['A' => [1, 250], 'B' => [4, 220]]],
            ['R1-M2', 1, ['A' => [2, 230], 'B' => [3, 240]]],
            ['R2-M1', 2, ['A' => [1, 245], 'B' => [3, 238]]],
        ];

        $this->insertMatchScores($tournament, $participants, 'トーナメント', 'SE', $matches);
    }

    /**
     * @param array<int,array<string,mixed>> $participants
     * @param array<int,array{0:string,1:int,2:array<string,array{0:int,1:int}>}> $matches
     */
    private function insertMatchScores(Tournament $tournament, array $participants, string $stage, string $prefix, array $matches): void
    {
        $bySeed = collect($participants)->keyBy('seed');
        $now = now();
        $rows = [];

        foreach ($matches as [$matchKey, $gameNumber, $slots]) {
            foreach ($slots as $slot => [$seed, $score]) {
                $participant = $bySeed->get($seed, []);
                $rows[] = [
                    'tournament_id' => $tournament->id,
                    'stage' => $stage,
                    'license_number' => $participant['license_no'] ?? null,
                    'name' => $participant['name'] ?? null,
                    'entry_number' => $prefix . ':' . $matchKey . ':' . $slot,
                    'game_number' => $gameNumber,
                    'score' => $score,
                    'shift' => $prefix === 'SE' ? 'single_elimination' : 'shootout',
                    'gender' => $participant['gender'] ?? null,
                    'pro_bowler_id' => $participant['pro_bowler_id'] ?? null,
                    'tournament_participant_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('game_scores')->insert($rows);
    }
}

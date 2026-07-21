<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentResult;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use App\Services\RoundRobinService;
use App\Services\ShootoutService;
use App\Services\SingleEliminationService;
use App\Services\StepLadderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunTournamentResultFlowRegression extends Command
{
    protected $signature = 'tournament:result-flow-regression {--json : Output the regression result as JSON}';

    protected $description = 'Run tournament result-flow regression checks for round robin, step ladder, shootout, and single elimination.';

    public function handle(
        RoundRobinService $roundRobinService,
        StepLadderService $stepLadderService,
        ShootoutService $shootoutService,
        SingleEliminationService $singleEliminationService
    ): int {
        $results = [];

        $results[] = $this->checkRoundRobin($roundRobinService);
        $results[] = $this->checkStepLadder($stepLadderService);
        $results[] = $this->checkShootout($shootoutService);

        $singleEliminationTournament = Tournament::query()
            ->where('result_flow_type', 'like', '%single_elimination%')
            ->orderBy('id')
            ->first();

        if ($singleEliminationTournament) {
            $results[] = $this->checkSingleElimination(
                service: $singleEliminationService,
                tournament: $singleEliminationTournament,
                caseName: 'single_elimination_existing',
                isFixture: false
            );
        } else {
            DB::beginTransaction();
            try {
                $tournament = $this->createSingleEliminationFixture();
                $results[] = $this->checkSingleElimination(
                    service: $singleEliminationService,
                    tournament: $tournament,
                    caseName: 'single_elimination_fixture',
                    isFixture: true
                );
            } catch (Throwable $e) {
                $results[] = $this->result(
                    caseName: 'single_elimination_fixture',
                    mode: 'single_elimination',
                    tournamentId: null,
                    isFixture: true,
                    checks: [
                        ['ok' => false, 'message' => $e->getMessage()],
                    ],
                    details: []
                );
            } finally {
                DB::rollBack();
            }
        }

        $failed = collect($results)->contains(fn (array $result) => ($result['status'] ?? '') === 'FAIL');

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['case', 'mode', 'tournament_id', 'fixture', 'status', 'message'],
                array_map(fn (array $result) => [
                    $result['case'] ?? '',
                    $result['mode'] ?? '',
                    $result['tournament_id'] ?? '-',
                    !empty($result['fixture']) ? 'yes' : 'no',
                    $result['status'] ?? '',
                    $result['message'] ?? '',
                ], $results)
            );
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function checkRoundRobin(RoundRobinService $service): array
    {
        $tournament = Tournament::query()
            ->where('result_flow_type', 'prelim_to_rr_to_final')
            ->orderBy('id')
            ->first();

        if (!$tournament) {
            return $this->missingResult('round_robin_existing', 'round_robin', '対象大会がDBにありません。');
        }

        try {
            $rr = $service->build([
                'tournament_id' => (int) $tournament->id,
                'upto_game' => 8,
                'gender' => 'F',
            ]);
        } catch (Throwable $e) {
            return $this->exceptionResult('round_robin_existing', 'round_robin', (int) $tournament->id, false, $e);
        }

        $players = array_values((array) ($rr['players'] ?? []));
        $leader = (array) ($players[0] ?? []);
        $leaderName = (string) ($leader['display_name'] ?? '');
        $roundRobinGames = (int) ($rr['meta']['round_robin_games'] ?? 0);
        $leaderWins = (int) ($leader['wins'] ?? 0);
        $leaderLosses = (int) ($leader['losses'] ?? 0);
        $leaderTies = (int) ($leader['ties'] ?? 0);
        $expectedBonus = ($leaderWins * (int) ($rr['meta']['win_bonus'] ?? 30))
            + ($leaderTies * (int) ($rr['meta']['tie_bonus'] ?? 15));

        return $this->result(
            caseName: 'round_robin_existing',
            mode: 'round_robin',
            tournamentId: (int) $tournament->id,
            isFixture: false,
            checks: [
                ['ok' => empty($rr['missing_carry_snapshot']), 'message' => 'carry snapshot missing'],
                ['ok' => count($players) === 8, 'message' => 'expected 8 round-robin players'],
                ['ok' => $roundRobinGames >= 1, 'message' => 'round-robin game count is missing'],
                ['ok' => $leaderName !== '', 'message' => 'leader name is missing'],
                ['ok' => $leaderWins + $leaderLosses + $leaderTies === $roundRobinGames, 'message' => 'leader record game count mismatch'],
                ['ok' => (int) ($leader['bonus_points'] ?? -1) === $expectedBonus, 'message' => 'leader bonus mismatch'],
            ],
            details: [
                'players' => count($players),
                'games' => $roundRobinGames,
                'leader' => $leaderName,
                'record' => (string) ($leader['record'] ?? ''),
                'bonus' => (int) ($leader['bonus_points'] ?? 0),
            ]
        );
    }

    private function checkStepLadder(StepLadderService $service): array
    {
        $tournament = Tournament::query()
            ->where('result_flow_type', 'prelim_to_rr_to_final')
            ->orderBy('id')
            ->first();

        if (!$tournament) {
            return $this->missingResult('step_ladder_existing', 'step_ladder', '対象大会がDBにありません。');
        }

        try {
            $stepLadder = $service->build([
                'tournament_id' => (int) $tournament->id,
                'upto_game' => 2,
                'gender' => 'F',
            ]);
        } catch (Throwable $e) {
            return $this->exceptionResult('step_ladder_existing', 'step_ladder', (int) $tournament->id, false, $e);
        }

        $standings = array_values((array) ($stepLadder['standings'] ?? []));
        $winnerName = (string) ($standings[0]['player']['display_name'] ?? '');
        $winnerBowlerId = (int) ($standings[0]['player']['pro_bowler_id'] ?? 0);
        $expectedWinnerBowlerId = $this->expectedWinnerBowlerId($tournament);

        return $this->result(
            caseName: 'step_ladder_existing',
            mode: 'step_ladder',
            tournamentId: (int) $tournament->id,
            isFixture: false,
            checks: [
                ['ok' => empty($stepLadder['missing_seed_snapshot']), 'message' => 'seed snapshot missing'],
                ['ok' => count((array) ($stepLadder['seeds'] ?? [])) === 3, 'message' => 'expected 3 step-ladder seeds'],
                ['ok' => ($stepLadder['semifinal']['status'] ?? '') === 'done', 'message' => 'semifinal is not done'],
                ['ok' => ($stepLadder['final']['status'] ?? '') === 'done', 'message' => 'final is not done'],
                ['ok' => count($standings) === 3, 'message' => 'expected 3 final standings'],
                ['ok' => $winnerName !== '', 'message' => 'winner name is missing'],
                ['ok' => $expectedWinnerBowlerId === null || $winnerBowlerId === $expectedWinnerBowlerId, 'message' => 'winner does not match tournament_results'],
            ],
            details: [
                'seeds' => count((array) ($stepLadder['seeds'] ?? [])),
                'semifinal_status' => (string) ($stepLadder['semifinal']['status'] ?? ''),
                'final_status' => (string) ($stepLadder['final']['status'] ?? ''),
                'winner' => $winnerName,
                'expected_winner_bowler_id' => $expectedWinnerBowlerId,
            ]
        );
    }

    private function checkShootout(ShootoutService $service): array
    {
        $tournament = Tournament::query()
            ->where('result_flow_type', 'like', '%shootout%')
            ->orderBy('id')
            ->first();

        if (!$tournament) {
            return $this->missingResult('shootout_existing', 'shootout', '対象大会がDBにありません。');
        }

        try {
            $flowType = trim((string) ($tournament->result_flow_type ?? 'legacy_standard')) ?: 'legacy_standard';
            $seedSourceResultCode = trim((string) ($tournament->shootout_seed_source_result_code ?? ''))
                ?: $this->defaultShootoutSeedSourceResultCode($flowType);
            $seedSnapshot = $this->findCurrentSnapshot(
                tournamentId: (int) $tournament->id,
                resultCode: $seedSourceResultCode,
                gender: $this->normalizeTournamentGender($tournament),
                shift: ''
            );
            $seedRows = $seedSnapshot
                ? $this->buildSeedEntriesFromSnapshot((int) $seedSnapshot->id, 8)
                : [];
            $shootout = $service->buildStandard8(
                seedEntries: $seedRows,
                matchScores: $this->loadShootoutMatchScores((int) $tournament->id)
            );
            $standings = $service->buildFinalStandings($shootout);
        } catch (Throwable $e) {
            return $this->exceptionResult('shootout_existing', 'shootout', (int) $tournament->id, false, $e);
        }

        $winnerName = (string) ($shootout['summary']['winner_name'] ?? '');
        $standingWinnerName = (string) ($standings[0]['node']['display_name'] ?? '');
        $winnerBowlerId = (int) ($standings[0]['node']['pro_bowler_id'] ?? 0);
        $expectedWinnerBowlerId = $this->expectedWinnerBowlerId($tournament);

        return $this->result(
            caseName: 'shootout_existing',
            mode: 'shootout',
            tournamentId: (int) $tournament->id,
            isFixture: false,
            checks: [
                ['ok' => isset($seedSnapshot), 'message' => 'seed snapshot missing'],
                ['ok' => count($seedRows) === 8, 'message' => 'expected 8 shootout seeds'],
                ['ok' => (int) ($shootout['summary']['completed_match_count'] ?? 0) === 3, 'message' => 'expected 3 completed shootout matches'],
                ['ok' => count($standings) === 8, 'message' => 'expected 8 shootout standings'],
                ['ok' => $winnerName !== '', 'message' => 'winner name is missing'],
                ['ok' => $expectedWinnerBowlerId === null || $winnerBowlerId === $expectedWinnerBowlerId, 'message' => 'winner does not match tournament_results'],
                ['ok' => $standingWinnerName === $winnerName, 'message' => 'standings winner mismatch'],
            ],
            details: [
                'seed_source' => $seedSourceResultCode,
                'seeds' => count($seedRows),
                'completed_matches' => (int) ($shootout['summary']['completed_match_count'] ?? 0),
                'winner' => $winnerName,
                'expected_winner_bowler_id' => $expectedWinnerBowlerId,
            ]
        );
    }

    private function checkSingleElimination(SingleEliminationService $service, Tournament $tournament, string $caseName, bool $isFixture): array
    {
        $flowType = trim((string) ($tournament->result_flow_type ?? ''));
        $qualifierCount = (int) ($tournament->single_elimination_qualifier_count ?? 0);
        if ($qualifierCount < 2) {
            $qualifierCount = 4;
        }

        $seedSourceResultCode = trim((string) ($tournament->single_elimination_seed_source_result_code ?? ''))
            ?: $this->defaultSingleEliminationSeedSourceResultCode($flowType);

        $seedSnapshot = $this->findCurrentSnapshot(
            tournamentId: (int) $tournament->id,
            resultCode: $seedSourceResultCode,
            gender: $this->normalizeTournamentGender($tournament),
            shift: ''
        );

        $seedRows = $seedSnapshot
            ? $this->buildSeedEntriesFromSnapshot((int) $seedSnapshot->id, $qualifierCount)
            : [];

        $seedSettings = $tournament->single_elimination_seed_settings;
        if (!is_array($seedSettings)) {
            $seedSettings = [];
        }

        $bracket = $service->buildBracket(
            qualifierCount: $qualifierCount,
            seedPolicy: trim((string) ($tournament->single_elimination_seed_policy ?? '')) ?: 'standard',
            seedSettings: $seedSettings,
            seedEntries: $seedRows
        );

        $bracket = $service->applyMatchScores(
            bracket: $bracket,
            matchScores: $this->loadSingleEliminationMatchScores((int) $tournament->id)
        );

        $standings = $service->buildFinalStandingRows($bracket);
        $completedMatches = $this->countCompletedMatches($bracket);
        $rankings = array_map(fn (array $row) => (int) ($row['ranking'] ?? 0), $standings);
        $winnerName = (string) ($standings[0]['display_name'] ?? '');
        $actualMatchCount = (int) ($bracket['summary']['actual_match_count'] ?? 0);
        $expectedRankings = $qualifierCount === 4 ? [1, 2, 3, 3] : null;

        return $this->result(
            caseName: $caseName,
            mode: 'single_elimination',
            tournamentId: (int) $tournament->id,
            isFixture: $isFixture,
            checks: [
                ['ok' => isset($seedSnapshot), 'message' => 'seed snapshot missing'],
                ['ok' => count($seedRows) === $qualifierCount, 'message' => 'expected ' . $qualifierCount . ' single-elimination seeds'],
                ['ok' => $actualMatchCount === max(1, $qualifierCount - 1), 'message' => 'expected ' . max(1, $qualifierCount - 1) . ' bracket matches'],
                ['ok' => $completedMatches === $actualMatchCount, 'message' => 'expected all bracket matches completed'],
                ['ok' => count($standings) === $qualifierCount, 'message' => 'expected ' . $qualifierCount . ' final standings'],
                ['ok' => $expectedRankings === null || $rankings === $expectedRankings, 'message' => 'unexpected rankings: ' . implode(',', $rankings)],
                ['ok' => $winnerName !== '', 'message' => 'winner name missing'],
            ],
            details: [
                'seed_source' => $seedSourceResultCode,
                'seeds' => count($seedRows),
                'completed_matches' => $completedMatches,
                'rankings' => implode(',', $rankings),
                'winner' => $winnerName,
            ]
        );
    }

    private function result(string $caseName, string $mode, ?int $tournamentId, bool $isFixture, array $checks, array $details): array
    {
        $failed = array_values(array_filter($checks, fn (array $check) => empty($check['ok'])));
        $status = empty($failed) ? 'OK' : 'FAIL';
        $message = $status === 'OK'
            ? $this->summaryMessage($details)
            : implode(' / ', array_map(fn (array $check) => (string) ($check['message'] ?? 'failed'), $failed));

        return [
            'case' => $caseName,
            'mode' => $mode,
            'tournament_id' => $tournamentId,
            'fixture' => $isFixture,
            'status' => $status,
            'message' => $message,
            'details' => $details,
        ];
    }

    private function missingResult(string $caseName, string $mode, string $message): array
    {
        return [
            'case' => $caseName,
            'mode' => $mode,
            'tournament_id' => null,
            'fixture' => false,
            'status' => 'SKIP',
            'message' => $message,
            'details' => [],
        ];
    }

    private function exceptionResult(string $caseName, string $mode, int $tournamentId, bool $isFixture, Throwable $e): array
    {
        return [
            'case' => $caseName,
            'mode' => $mode,
            'tournament_id' => $tournamentId,
            'fixture' => $isFixture,
            'status' => 'FAIL',
            'message' => $e->getMessage(),
            'details' => [],
        ];
    }

    private function summaryMessage(array $details): string
    {
        $parts = [];
        foreach ($details as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '=' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return implode(', ', $parts) ?: 'OK';
    }

    private function normalizeTournamentGender(Tournament $tournament): ?string
    {
        $gender = strtoupper(trim((string) ($tournament->gender ?? '')));

        return in_array($gender, ['M', 'F'], true) ? $gender : null;
    }

    private function expectedWinnerBowlerId(Tournament $tournament): ?int
    {
        $bowlerId = TournamentResult::query()
            ->where('tournament_id', $tournament->id)
            ->where('ranking', 1)
            ->value('pro_bowler_id');

        return $bowlerId !== null && (int) $bowlerId > 0 ? (int) $bowlerId : null;
    }

    private function findCurrentSnapshot(int $tournamentId, string $resultCode, ?string $gender, string $shift): ?object
    {
        $gender = $gender !== null ? trim($gender) : null;
        if ($gender === '') {
            $gender = null;
        }

        $shift = trim($shift);
        $candidates = [
            ['gender' => $gender, 'shift' => $shift !== '' ? $shift : null],
            ['gender' => $gender, 'shift' => null],
            ['gender' => null, 'shift' => null],
        ];

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

            $snapshot = $query
                ->orderByDesc('reflected_at')
                ->orderByDesc('id')
                ->first();

            if ($snapshot) {
                return $snapshot;
            }
        }

        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildSeedEntriesFromSnapshot(int $snapshotId, int $qualifierCount): array
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
                'participant_key' => $this->participantKeyFromSnapshotRow($row, $seed),
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

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function loadSingleEliminationMatchScores(int $tournamentId): array
    {
        $rows = DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'トーナメント')
            ->where('entry_number', 'like', 'SE:%')
            ->orderBy('game_number')
            ->orderBy('entry_number')
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $entryNumber = trim((string) ($row->entry_number ?? ''));
            if (!preg_match('/^SE:(R\d+-M\d+):([AB])$/', $entryNumber, $m)) {
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

    private function defaultShootoutSeedSourceResultCode(string $flowType): string
    {
        return match ($flowType) {
            'prelim_to_quarterfinal_to_shootout_to_final' => 'quarterfinal_total',
            'prelim_to_semifinal_to_shootout_to_final' => 'semifinal_total',
            default => 'prelim_total',
        };
    }

    private function defaultSingleEliminationSeedSourceResultCode(string $flowType): string
    {
        return match ($flowType) {
            'prelim_to_quarterfinal_to_single_elimination_to_final' => 'quarterfinal_total',
            'prelim_to_semifinal_to_single_elimination_to_final' => 'semifinal_total',
            default => 'prelim_total',
        };
    }

    private function participantKeyFromSnapshotRow(object $row, int $seed): string
    {
        $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            return 'pro_bowler:' . $proBowlerId;
        }

        $license = trim((string) ($row->pro_bowler_license_no ?? ''));
        if ($license !== '') {
            return 'license:' . $this->normalizeDigits($license);
        }

        $amateurName = trim((string) ($row->amateur_name ?? ''));
        if ($amateurName !== '') {
            return 'amateur:' . md5($amateurName);
        }

        $displayName = trim((string) ($row->display_name ?? ''));
        if ($displayName !== '') {
            return 'name:' . md5($displayName);
        }

        return 'seed:' . $seed;
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function countCompletedMatches(array $bracket): int
    {
        $count = 0;
        foreach ((array) ($bracket['rounds'] ?? []) as $round) {
            foreach ((array) ($round['matches'] ?? []) as $match) {
                if (!empty($match['is_complete']) && empty($match['is_bye'])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function createSingleEliminationFixture(): Tournament
    {
        $tournament = $this->createTournament([
            'name' => '結果フロー回帰 シングルエリミネーション fixture',
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
            'name' => '結果フロー回帰 fixture',
            'year' => 2026,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
            'venue_name' => '結果フロー回帰テスト会場',
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
                'license_no' => $bowler?->license_no ?: sprintf('RFLREG%04d', $i),
                'name' => $bowler?->name_kanji ?: ('結果フロー回帰 選手' . $i),
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
                'affiliation_display' => '結果フロー回帰テスト',
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
            'calculation_definition' => ['source' => 'result_flow_regression_fixture'],
            'reflected_at' => now(),
            'reflected_by' => null,
            'is_final' => $isFinal,
            'is_published' => true,
            'is_current' => true,
            'notes' => '結果フロー回帰fixture',
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
                'display_name' => $participant['name'] ?? ('結果フロー回帰 選手' . ($index + 1)),
                'gender' => $participant['gender'] ?? null,
                'shift' => null,
                'entry_number' => 'RFL-' . ($index + 1),
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

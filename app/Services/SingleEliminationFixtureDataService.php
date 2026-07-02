<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentResult;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use Illuminate\Support\Facades\DB;

class SingleEliminationFixtureDataService
{
    public const FIXTURE_NAME = 'シングルエリミネーション通し確認 fixture';
    private const FIXTURE_NOTE = 'single_elimination_actual_flow_fixture';

    public function __construct(
        private readonly SingleEliminationService $singleEliminationService,
        private readonly TournamentResultSnapshotService $snapshotService
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function restore(bool $force = false): array
    {
        return DB::transaction(function () use ($force): array {
            $existing = $this->findExistingFixture();

            if ($existing && $force) {
                $this->deleteFixture($existing);
                $existing = null;
            }

            if ($existing) {
                return $this->buildSummary($existing->refresh(), false);
            }

            $tournament = $this->createTournament();
            $participants = $this->participants(4);

            $this->insertPrelimScores($tournament, $participants);
            $prelimSnapshot = $this->snapshotService->createTotalPinSnapshot([
                'tournament_id' => (int) $tournament->id,
                'result_code' => 'prelim_total',
                'result_name' => '予選通算成績',
                'result_type' => 'total_pin',
                'stage_name' => '予選',
                'gender' => null,
                'shift' => null,
                'is_final' => false,
                'is_published' => true,
                'reflected_by' => null,
                'notes' => self::FIXTURE_NOTE,
                'calculation_definition' => [
                    'source' => self::FIXTURE_NOTE,
                    'source_sets' => [
                        ['stage' => '予選', 'game_from' => 1, 'game_to' => 8, 'bucket' => 'scratch'],
                    ],
                ],
            ]);

            $this->insertSingleEliminationScores($tournament, $participants);
            $bracket = $this->buildBracket($tournament);
            $standings = $this->singleEliminationService->buildFinalStandingRows($bracket);
            $finalSnapshot = $this->createFinalSnapshot($tournament, $prelimSnapshot, $standings);
            $this->syncTournamentResults($finalSnapshot);

            return $this->buildSummary($tournament->refresh(), true);
        });
    }

    public function findExistingFixture(): ?Tournament
    {
        return Tournament::query()
            ->where('name', self::FIXTURE_NAME)
            ->where('result_flow_type', 'like', '%single_elimination%')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<string,mixed>
     */
    public function buildSummary(Tournament $tournament, bool $created = false): array
    {
        $bracket = $this->buildBracket($tournament);
        $standings = [];

        try {
            $standings = $this->singleEliminationService->buildFinalStandingRows($bracket);
        } catch (\Throwable) {
            $standings = [];
        }

        $winner = trim((string) ($standings[0]['display_name'] ?? ''));
        $rankings = array_map(fn (array $row): int => (int) ($row['ranking'] ?? 0), $standings);
        $prelimSnapshot = $this->currentSnapshot($tournament, 'prelim_total');
        $finalSnapshot = $this->currentSnapshot($tournament, 'single_elimination_final');

        return [
            'created' => $created,
            'tournament_id' => (int) $tournament->id,
            'name' => (string) $tournament->name,
            'prelim_snapshot_id' => $prelimSnapshot?->id,
            'final_snapshot_id' => $finalSnapshot?->id,
            'score_count' => DB::table('game_scores')->where('tournament_id', $tournament->id)->count(),
            'single_elimination_score_count' => DB::table('game_scores')
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'トーナメント')
                ->where('entry_number', 'like', 'SE:%')
                ->count(),
            'result_count' => TournamentResult::query()->where('tournament_id', $tournament->id)->count(),
            'seed_count' => (int) ($bracket['summary']['qualifier_count'] ?? 0),
            'actual_match_count' => (int) ($bracket['summary']['actual_match_count'] ?? 0),
            'completed_match_count' => $this->countCompletedMatches($bracket),
            'standings_count' => count($standings),
            'winner' => $winner,
            'rankings' => implode(',', $rankings),
        ];
    }

    private function createTournament(): Tournament
    {
        return Tournament::query()->create([
            'name' => self::FIXTURE_NAME,
            'year' => 2026,
            'start_date' => '2026-07-02',
            'end_date' => '2026-07-02',
            'venue_name' => 'SE通し確認テスト会場',
            'venue_address' => '東京都テスト区1-1-1',
            'gender' => 'X',
            'official_type' => 'official',
            'entry_start' => '2026-06-01 00:00:00',
            'entry_end' => '2026-06-30 23:59:59',
            'title_category' => 'normal',
            'lane_from' => 21,
            'lane_to' => 30,
            'use_shift_draw' => false,
            'use_lane_draw' => false,
            'lane_assignment_mode' => 'single_lane',
            'box_player_count' => 4,
            'accept_shift_preference' => false,
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
            'result_carry_preset' => 'default',
            'result_carry_settings' => [],
        ]);
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
                'license_no' => $bowler?->license_no ?: sprintf('SEFIX%04d', $i),
                'name' => $bowler?->name_kanji ?: ('SE通し確認 選手' . $i),
                'gender' => ((int) ($bowler?->sex ?? 1)) === 2 ? 'F' : 'M',
            ];
        }

        return $participants;
    }

    /**
     * @param array<int,array<string,mixed>> $participants
     */
    private function insertPrelimScores(Tournament $tournament, array $participants): void
    {
        $scoresBySeed = [
            1 => [220, 221, 222, 223, 224, 225, 226, 227],
            2 => [218, 219, 220, 221, 222, 223, 224, 225],
            3 => [216, 217, 218, 219, 220, 221, 222, 223],
            4 => [214, 215, 216, 217, 218, 219, 220, 221],
        ];

        $now = now();
        $rows = [];

        foreach ($participants as $participant) {
            $seed = (int) ($participant['seed'] ?? 0);
            foreach (($scoresBySeed[$seed] ?? []) as $index => $score) {
                $rows[] = [
                    'tournament_id' => $tournament->id,
                    'stage' => '予選',
                    'license_number' => $participant['license_no'] ?? null,
                    'name' => $participant['name'] ?? null,
                    'entry_number' => 'SEFIX-' . $seed,
                    'game_number' => $index + 1,
                    'score' => $score,
                    'shift' => null,
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

        $bySeed = collect($participants)->keyBy('seed');
        $now = now();
        $rows = [];

        foreach ($matches as [$matchKey, $gameNumber, $slots]) {
            foreach ($slots as $slot => [$seed, $score]) {
                $participant = $bySeed->get($seed, []);
                $rows[] = [
                    'tournament_id' => $tournament->id,
                    'stage' => 'トーナメント',
                    'license_number' => $participant['license_no'] ?? null,
                    'name' => $participant['name'] ?? null,
                    'entry_number' => 'SE:' . $matchKey . ':' . $slot,
                    'game_number' => $gameNumber,
                    'score' => $score,
                    'shift' => 'single_elimination',
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

    private function buildBracket(Tournament $tournament): array
    {
        $qualifierCount = (int) ($tournament->single_elimination_qualifier_count ?? 4);
        $seedSnapshot = $this->currentSnapshot($tournament, 'prelim_total');
        $seedEntries = $seedSnapshot ? $this->seedEntriesFromSnapshot((int) $seedSnapshot->id, $qualifierCount) : [];
        $seedSettings = $tournament->single_elimination_seed_settings;

        if (!is_array($seedSettings)) {
            $seedSettings = [];
        }

        $bracket = $this->singleEliminationService->buildBracket(
            qualifierCount: $qualifierCount,
            seedPolicy: (string) ($tournament->single_elimination_seed_policy ?: 'standard'),
            seedSettings: $seedSettings,
            seedEntries: $seedEntries
        );

        return $this->singleEliminationService->applyMatchScores(
            bracket: $bracket,
            matchScores: $this->loadSingleEliminationMatchScores((int) $tournament->id)
        );
    }

    private function currentSnapshot(Tournament $tournament, string $resultCode): ?TournamentResultSnapshot
    {
        return TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->where('result_code', $resultCode)
            ->where('is_current', true)
            ->whereNull('gender')
            ->whereNull('shift')
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function seedEntriesFromSnapshot(int $snapshotId, int $qualifierCount): array
    {
        return DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $snapshotId)
            ->orderBy('ranking')
            ->orderByDesc('total_pin')
            ->orderBy('id')
            ->limit($qualifierCount)
            ->get()
            ->values()
            ->map(function (object $row, int $index): array {
                $seed = $index + 1;
                $displayName = trim((string) ($row->display_name ?? $row->amateur_name ?? $row->pro_bowler_license_no ?? ('seed' . $seed)));

                return [
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
            })
            ->all();
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
            if (!preg_match('/^SE:(R\d+-M\d+):([AB])$/i', $entryNumber, $m)) {
                continue;
            }

            $scores[strtoupper($m[1])][$m[2]] = [
                'score' => $row->score !== null ? (int) $row->score : null,
                'row_id' => (int) ($row->id ?? 0),
                'license_number' => $row->license_number ?? null,
                'name' => $row->name ?? null,
                'pro_bowler_id' => $row->pro_bowler_id ?? null,
            ];
        }

        return $scores;
    }

    /**
     * @param array<int,array<string,mixed>> $standings
     */
    private function createFinalSnapshot(Tournament $tournament, TournamentResultSnapshot $prelimSnapshot, array $standings): TournamentResultSnapshot
    {
        TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->where('result_code', 'single_elimination_final')
            ->whereNull('gender')
            ->whereNull('shift')
            ->update(['is_current' => false]);

        $maxGames = empty($standings)
            ? 0
            : max(array_map(fn (array $row): int => (int) ($row['games'] ?? 0), $standings));

        $snapshot = TournamentResultSnapshot::query()->create([
            'tournament_id' => $tournament->id,
            'result_code' => 'single_elimination_final',
            'result_name' => 'トーナメント最終成績',
            'result_type' => 'single_elimination',
            'stage_name' => 'トーナメント',
            'gender' => null,
            'shift' => null,
            'games_count' => $maxGames,
            'carry_game_count' => 8,
            'carry_stage_names' => ['予選'],
            'calculation_definition' => [
                'source' => self::FIXTURE_NOTE,
                'seed_snapshot_id' => (int) $prelimSnapshot->id,
                'seed_snapshot_code' => 'prelim_total',
                'match_score_stage' => 'トーナメント',
                'ranking_policy' => SingleEliminationService::RANKING_POLICY_SAME_LOST_ROUND,
            ],
            'reflected_at' => now(),
            'reflected_by' => null,
            'is_final' => true,
            'is_published' => true,
            'is_current' => true,
            'notes' => self::FIXTURE_NOTE,
        ]);

        foreach ($standings as $row) {
            TournamentResultSnapshotRow::query()->create([
                'snapshot_id' => $snapshot->id,
                'ranking' => (int) ($row['ranking'] ?? 0),
                'pro_bowler_id' => $row['pro_bowler_id'] ?? null,
                'pro_bowler_license_no' => $row['pro_bowler_license_no'] ?? null,
                'amateur_name' => $row['amateur_name'] ?? null,
                'display_name' => $row['display_name'] ?? null,
                'gender' => $row['gender'] ?? null,
                'shift' => $row['shift'] ?? null,
                'entry_number' => $row['entry_number'] ?? null,
                'scratch_pin' => (int) ($row['scratch_pin'] ?? 0),
                'carry_pin' => (int) ($row['carry_pin'] ?? 0),
                'total_pin' => (int) ($row['total_pin'] ?? 0),
                'games' => (int) ($row['games'] ?? 0),
                'average' => $row['average'] ?? null,
                'tie_break_value' => $row['tie_break_value'] ?? null,
                'points' => null,
                'prize_money' => null,
            ]);
        }

        return $snapshot->load('rows');
    }

    private function syncTournamentResults(TournamentResultSnapshot $snapshot): void
    {
        TournamentResult::query()
            ->where('tournament_id', $snapshot->tournament_id)
            ->delete();

        foreach ($snapshot->rows()->orderBy('ranking')->orderBy('id')->get() as $row) {
            TournamentResult::query()->create([
                'tournament_id' => (int) $snapshot->tournament_id,
                'pro_bowler_id' => $row->pro_bowler_id !== null ? (int) $row->pro_bowler_id : null,
                'pro_bowler_license_no' => $row->pro_bowler_license_no,
                'amateur_name' => $row->pro_bowler_id === null ? ($row->amateur_name ?: $row->display_name) : null,
                'ranking' => (int) $row->ranking,
                'points' => 0,
                'award_points' => 0,
                'step_points' => 0,
                'total_pin' => (int) $row->total_pin,
                'games' => (int) $row->games,
                'average' => $row->average !== null ? (float) $row->average : null,
                'prize_money' => 0,
                'ranking_year' => 2026,
                'affiliation_display' => 'SE通し確認',
            ]);
        }
    }

    private function deleteFixture(Tournament $tournament): void
    {
        $snapshotIds = TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->pluck('id')
            ->all();

        TournamentResultSnapshotRow::query()->whereIn('snapshot_id', $snapshotIds)->delete();
        TournamentResultSnapshot::query()->where('tournament_id', $tournament->id)->delete();
        TournamentResult::query()->where('tournament_id', $tournament->id)->delete();
        DB::table('game_scores')->where('tournament_id', $tournament->id)->delete();
        $tournament->delete();
    }

    private function participantKeyFromSnapshotRow(object $row, int $seed): string
    {
        $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            return 'pro_bowler:' . $proBowlerId;
        }

        $license = trim((string) ($row->pro_bowler_license_no ?? ''));
        if ($license !== '') {
            return 'license:' . (preg_replace('/\D+/', '', $license) ?? $license);
        }

        $displayName = trim((string) ($row->display_name ?? $row->amateur_name ?? ''));
        if ($displayName !== '') {
            return 'name:' . md5($displayName);
        }

        return 'seed:' . $seed;
    }

    private function countCompletedMatches(array $bracket): int
    {
        $count = 0;
        foreach ((array) ($bracket['rounds'] ?? []) as $round) {
            foreach ((array) ($round['matches'] ?? []) as $match) {
                $match = (array) $match;
                if (!empty($match['is_complete']) && empty($match['is_bye'])) {
                    $count++;
                }
            }
        }

        return $count;
    }
}

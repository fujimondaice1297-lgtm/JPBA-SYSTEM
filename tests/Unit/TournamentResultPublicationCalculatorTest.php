<?php

namespace Tests\Unit;

use App\Services\TournamentResultPublicationCalculator;
use PHPUnit\Framework\TestCase;

class TournamentResultPublicationCalculatorTest extends TestCase
{
    public function test_it_merges_final_semifinal_and_preliminary_rows_without_duplicates(): void
    {
        $calculator = new TournamentResultPublicationCalculator;

        $rows = $calculator->build([
            $this->group(30, 'shootout_final', [
                $this->row(1, 101, 'Winner', 2200, 12),
                $this->row(2, 102, 'Second', 2150, 12),
            ]),
            $this->group(20, 'semifinal_total', [
                $this->row(1, 102, 'Second', 2100, 12),
                $this->row(2, 101, 'Winner', 2080, 12),
                $this->row(3, 103, 'Third', 2050, 12),
            ]),
            $this->group(10, 'prelim_total', [
                $this->row(1, 103, 'Third', 1600, 8),
                $this->row(2, 104, 'Fourth', 1580, 8),
            ]),
        ], [], [], [
            'counts_for_points' => false,
            'counts_for_prize' => false,
        ]);

        self::assertSame([101, 102, 103, 104], array_column($rows, 'pro_bowler_id'));
        self::assertSame([1, 2, 3, 4], array_column($rows, 'ranking'));
        self::assertSame(['shootout_final', 'shootout_final', 'semifinal_total', 'prelim_total'], array_column($rows, 'source_result_code'));
    }

    public function test_season_trial_points_are_derived_from_award_and_semifinal_rank(): void
    {
        $calculator = new TournamentResultPublicationCalculator;
        $rows = [];
        for ($rank = 1; $rank <= 24; $rank++) {
            $rows[] = $this->row($rank, 100 + $rank, 'Player '.$rank, 2000 - $rank, 12);
        }

        $result = $calculator->build([
            $this->group(20, 'semifinal_total', $rows),
        ], [], [], [
            'is_season_trial' => true,
            'counts_for_points' => true,
            'counts_for_prize' => false,
            'semifinal_qualifier_count' => 24,
        ]);

        self::assertSame(50, $result[0]['award_points']);
        self::assertSame(24, $result[0]['step_points']);
        self::assertSame(74, $result[0]['points']);
        self::assertSame(18, $result[7]['award_points']);
        self::assertSame(17, $result[7]['step_points']);
        self::assertSame(35, $result[7]['points']);
        self::assertSame(0, $result[23]['award_points']);
        self::assertSame(1, $result[23]['step_points']);
        self::assertSame(1, $result[23]['points']);
    }

    public function test_distributions_apply_only_to_professional_rows(): void
    {
        $calculator = new TournamentResultPublicationCalculator;

        $result = $calculator->build([
            $this->group(10, 'final_total', [
                $this->row(1, 101, 'Pro', 2000, 10),
                [
                    'id' => 2,
                    'ranking' => 2,
                    'pro_bowler_id' => null,
                    'amateur_bowler_id' => 55,
                    'display_name' => 'Amateur',
                    'total_pin' => 1900,
                    'games' => 10,
                ],
            ]),
        ], [1 => 100, 2 => 80], [1 => 500000, 2 => 300000], [
            'counts_for_points' => true,
            'counts_for_prize' => true,
        ]);

        self::assertSame(100, $result[0]['points']);
        self::assertSame(500000, $result[0]['prize_money']);
        self::assertSame(0, $result[1]['points']);
        self::assertSame(0, $result[1]['prize_money']);
    }

    public function test_equal_source_ranks_remain_tied(): void
    {
        $calculator = new TournamentResultPublicationCalculator;

        $result = $calculator->build([
            $this->group(30, 'single_elimination_final', [
                $this->row(1, 101, 'Winner', 2000, 10),
                $this->row(2, 102, 'Second', 1950, 10),
                $this->row(3, 103, 'Third A', 1900, 10),
                $this->row(3, 104, 'Third B', 1880, 10),
            ]),
        ], [], []);

        self::assertSame([1, 2, 3, 3], array_column($result, 'ranking'));
    }

    /** @return array<string,mixed> */
    private function row(int $rank, int $bowlerId, string $name, int $pin, int $games): array
    {
        return [
            'id' => $bowlerId,
            'ranking' => $rank,
            'pro_bowler_id' => $bowlerId,
            'display_name' => $name,
            'total_pin' => $pin,
            'games' => $games,
            'average' => $pin / $games,
            'points' => null,
            'prize_money' => null,
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function group(int $snapshotId, string $resultCode, array $rows): array
    {
        return [
            'snapshot_id' => $snapshotId,
            'result_code' => $resultCode,
            'rows' => $rows,
        ];
    }
}

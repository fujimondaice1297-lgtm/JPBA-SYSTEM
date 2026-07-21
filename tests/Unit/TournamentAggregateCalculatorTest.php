<?php

namespace Tests\Unit;

use App\Services\TournamentAggregateCalculator;
use PHPUnit\Framework\TestCase;

class TournamentAggregateCalculatorTest extends TestCase
{
    public function test_it_ranks_complete_individual_all_events_before_incomplete_rows(): void
    {
        $calculator = new TournamentAggregateCalculator;
        $sources = [
            ['id' => 1, 'label' => 'シングルス', 'is_required' => true, 'expected_games_per_member' => 2],
            ['id' => 2, 'label' => 'ダブルス', 'is_required' => true, 'expected_games_per_member' => 2],
        ];
        $subjects = [
            $this->individual('pro:1', '選手A', 840, [
                1 => $this->source(1, 'シングルス', 420, 2, ['pro:1' => 2]),
                2 => $this->source(2, 'ダブルス', 420, 2, ['pro:1' => 2]),
            ], [210, 210, 200, 220]),
            $this->individual('pro:2', '選手B', 450, [
                1 => $this->source(1, 'シングルス', 450, 2, ['pro:2' => 2]),
            ], [220, 230]),
        ];

        $rows = $calculator->finalize($subjects, $sources, 'individual', true);

        $this->assertSame('選手A', $rows[0]['display_name']);
        $this->assertTrue($rows[0]['is_complete']);
        $this->assertSame(1, $rows[0]['ranking']);
        $this->assertSame(840, $rows[0]['total_pin']);
        $this->assertSame(210.0, $rows[0]['average']);

        $this->assertSame('選手B', $rows[1]['display_name']);
        $this->assertFalse($rows[1]['is_complete']);
        $this->assertContains('ダブルスのスコアなし', $rows[1]['incomplete_reasons']);
    }

    public function test_group_is_incomplete_when_a_member_has_missing_games(): void
    {
        $calculator = new TournamentAggregateCalculator;
        $sources = [
            ['id' => 10, 'label' => 'チーム戦', 'is_required' => true, 'expected_games_per_member' => 3],
        ];
        $subjects = [
            [
                'identity_key' => 'group:1',
                'display_name' => 'Aチーム',
                'expected_member_count' => 2,
                'member_keys' => ['participant:1', 'participant:2'],
                'total_pin' => 1250,
                'games' => 6,
                'score_values' => [200, 210, 220, 195, 205, 220],
                'source_breakdown' => [
                    10 => $this->source(10, 'チーム戦', 1250, 6, [
                        'participant:1' => 3,
                        'participant:2' => 3,
                    ]),
                ],
            ],
            [
                'identity_key' => 'group:2',
                'display_name' => 'Bチーム',
                'expected_member_count' => 2,
                'member_keys' => ['participant:3', 'participant:4'],
                'total_pin' => 1100,
                'games' => 5,
                'score_values' => [220, 220, 220, 220, 220],
                'source_breakdown' => [
                    10 => $this->source(10, 'チーム戦', 1100, 5, [
                        'participant:3' => 3,
                        'participant:4' => 2,
                    ]),
                ],
            ],
        ];

        $rows = $calculator->finalize($subjects, $sources, 'group', true);

        $this->assertTrue($rows[0]['is_complete']);
        $this->assertSame('Aチーム', $rows[0]['display_name']);
        $this->assertFalse($rows[1]['is_complete']);
        $this->assertContains('チーム戦に未入力メンバーあり', $rows[1]['incomplete_reasons']);
    }

    public function test_shared_rank_is_default_and_low_high_can_break_a_tie(): void
    {
        $calculator = new TournamentAggregateCalculator;
        $sources = [
            ['id' => 1, 'label' => '競技', 'is_required' => true, 'expected_games_per_member' => 2],
        ];
        $subjects = [
            $this->individual('pro:1', '選手A', 400, [
                1 => $this->source(1, '競技', 400, 2, ['pro:1' => 2]),
            ], [180, 220]),
            $this->individual('pro:2', '選手B', 400, [
                1 => $this->source(1, '競技', 400, 2, ['pro:2' => 2]),
            ], [195, 205]),
        ];

        $shared = $calculator->finalize($subjects, $sources, 'individual', true, 'shared_rank');
        $lowHigh = $calculator->finalize($subjects, $sources, 'individual', true, 'low_high');

        $this->assertSame([1, 1], array_column($shared, 'ranking'));
        $this->assertSame('選手B', $lowHigh[0]['display_name']);
        $this->assertSame([1, 2], array_column($lowHigh, 'ranking'));
    }

    private function individual(
        string $key,
        string $name,
        int $totalPin,
        array $sourceBreakdown,
        array $scores,
    ): array {
        return [
            'identity_key' => $key,
            'display_name' => $name,
            'total_pin' => $totalPin,
            'games' => count($scores),
            'score_values' => $scores,
            'source_breakdown' => $sourceBreakdown,
        ];
    }

    private function source(
        int $id,
        string $label,
        int $totalPin,
        int $games,
        array $memberGames,
    ): array {
        return [
            'source_id' => $id,
            'label' => $label,
            'total_pin' => $totalPin,
            'games' => $games,
            'member_games' => $memberGames,
        ];
    }
}

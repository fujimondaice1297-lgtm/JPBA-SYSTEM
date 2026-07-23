<?php

namespace Tests\Unit;

use App\Services\ShootoutService;
use Tests\TestCase;

final class ShootoutServiceTest extends TestCase
{
    public function test_explicit_top_score_winner_resolves_a_tied_match(): void
    {
        $shootout = (new ShootoutService)->buildStandard8(
            $this->seeds(),
            [
                'SO1' => [
                    'A' => ['score' => 240],
                    'B' => ['score' => 210],
                    'C' => ['score' => 200],
                    'D' => ['score' => 190],
                ],
                'SO2' => [
                    'A' => ['score' => 220],
                    'B' => ['score' => 240],
                    'C' => ['score' => 210],
                    'D' => ['score' => 240, 'is_winner' => true],
                ],
                'SO3' => [
                    'A' => ['score' => 200],
                    'B' => ['score' => 230],
                ],
            ],
        );

        $second = $shootout['matches'][1];
        $this->assertTrue($second['is_complete']);
        $this->assertFalse($second['is_tied']);
        $this->assertTrue($second['decided_by_tiebreak']);
        $this->assertSame('Player 5', $second['winner_node']['display_name']);
        $this->assertSame(3, $shootout['summary']['completed_match_count']);
        $this->assertSame('Player 5', $shootout['summary']['winner_name']);
    }

    public function test_tied_match_without_explicit_winner_remains_incomplete(): void
    {
        $shootout = (new ShootoutService)->buildStandard8(
            $this->seeds(),
            [
                'SO1' => [
                    'A' => ['score' => 240],
                    'B' => ['score' => 210],
                    'C' => ['score' => 200],
                    'D' => ['score' => 190],
                ],
                'SO2' => [
                    'A' => ['score' => 220],
                    'B' => ['score' => 240],
                    'C' => ['score' => 210],
                    'D' => ['score' => 240],
                ],
            ],
        );

        $this->assertFalse($shootout['matches'][1]['is_complete']);
        $this->assertTrue($shootout['matches'][1]['is_tied']);
        $this->assertSame(1, $shootout['summary']['completed_match_count']);
    }

    /** @return array<int,array<string,mixed>> */
    private function seeds(): array
    {
        return array_map(fn (int $seed): array => [
            'seed' => $seed,
            'display_name' => 'Player '.$seed,
            'pro_bowler_id' => $seed,
            'pro_bowler_license_no' => sprintf('M%08d', $seed),
            'participant_key' => 'seed-'.$seed,
            'source_ranking' => $seed,
            'total_pin' => 2400 - $seed,
            'games' => 12,
            'average' => 200,
        ], range(1, 8));
    }
}

<?php

namespace Tests\Unit;

use App\Models\Tournament;
use App\Services\RoundRobinService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RoundRobinConfiguredRoundsTest extends TestCase
{
    public function test_it_maps_official_license_pairings_to_current_seeds(): void
    {
        $tournament = new Tournament;
        $tournament->template_snapshot = [
            'round_robin_pairings' => [
                [
                    'game_number' => 1,
                    'pairs' => [
                        ['left_license' => 'F00000003', 'right_license' => 'F00000001'],
                        ['left_license' => 'F00000004', 'right_license' => 'F00000002'],
                    ],
                ],
            ],
        ];
        $players = [
            1 => ['seed' => 1, 'license_no' => 'F00000001'],
            2 => ['seed' => 2, 'license_no' => 'F00000002'],
            3 => ['seed' => 3, 'license_no' => 'F00000003'],
            4 => ['seed' => 4, 'license_no' => 'F00000004'],
        ];
        $method = new ReflectionMethod(RoundRobinService::class, 'configuredRounds');

        $rounds = $method->invoke(new RoundRobinService, $tournament, $players);

        self::assertSame(3, $rounds[0][0]['left_seed']);
        self::assertSame(1, $rounds[0][0]['right_seed']);
        self::assertSame(4, $rounds[0][1]['left_seed']);
        self::assertSame(2, $rounds[0][1]['right_seed']);
    }
}

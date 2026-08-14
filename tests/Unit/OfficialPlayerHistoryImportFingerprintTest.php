<?php

namespace Tests\Unit;

use App\Models\ProBowler;
use App\Services\JpbaOfficialPlayerProfileService;
use App\Services\OfficialPlayerHistoryImportService;
use ReflectionMethod;
use Tests\TestCase;

class OfficialPlayerHistoryImportFingerprintTest extends TestCase
{
    public function test_it_removes_only_exact_tournament_history_duplicates(): void
    {
        $bowler = new ProBowler();
        $bowler->id = 123;
        $base = [
            'season_year' => 2007,
            'held_on' => '2007-07-19',
            'tournament_name' => 'シーズントライアル',
            'ranking_rank' => 54,
            'average' => '187.88',
            'prize_money' => 0,
        ];
        $records = [
            $base,
            $base,
            [...$base, 'average' => '187.87'],
        ];

        $service = new OfficialPlayerHistoryImportService(
            new JpbaOfficialPlayerProfileService()
        );
        $method = new ReflectionMethod($service, 'uniqueTournamentRecords');
        $method->setAccessible(true);

        $unique = $method->invoke($service, $bowler, $records);

        $this->assertCount(2, $unique);
        $this->assertSame('187.88', $unique[0]['average']);
        $this->assertSame('187.87', $unique[1]['average']);
    }
}

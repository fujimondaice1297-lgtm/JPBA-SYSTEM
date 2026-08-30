<?php

namespace Tests\Unit;

use App\Services\SeasonTrial2026CatalogService;
use Tests\TestCase;

class SeasonTrial2026CatalogServiceTest extends TestCase
{
    public function test_catalog_contains_all_official_2026_season_trial_venue_events(): void
    {
        $catalog = app(SeasonTrial2026CatalogService::class)->catalog();
        $editions = collect($catalog['editions'])->keyBy('season_key');
        $events = $editions->flatMap(fn (array $edition) => $edition['events']);

        $this->assertSame(2026, $catalog['year']);
        $this->assertSame('jpba-season-trial', $catalog['series_code']);
        $this->assertSame('season-trial-standard', $catalog['template_code']);
        $this->assertSame(['winter', 'spring', 'summer', 'autumn'], $editions->keys()->all());
        $this->assertCount(16, $events);
        $this->assertCount(12, $events->whereNotNull('final_result_url'));
        $this->assertCount(4, $events->whereNull('final_result_url'));

        foreach ($editions as $seasonKey => $edition) {
            $this->assertCount(4, $edition['events']);
            $this->assertSame(['A', 'B', 'C', 'D'], collect($edition['events'])->pluck('venue_code')->all());
            $this->assertStringStartsWith('https://www.jpba.or.jp/', $edition['source_url']);
            foreach ($edition['events'] as $event) {
                $this->assertArrayNotHasKey('participants', $event);
                $this->assertArrayNotHasKey('scores', $event);
                $this->assertArrayNotHasKey('results', $event);
                $this->assertGreaterThanOrEqual($edition['start_date'], $event['date']);
                $this->assertLessThanOrEqual($edition['end_date'], $event['date']);
            }
        }

        $summer = $editions['summer'];
        $summerB = collect($summer['events'])->firstWhere('venue_code', 'B');
        $summerD = collect($summer['events'])->firstWhere('venue_code', 'D');

        $this->assertSame('2026-07-01', $summerB['date']);
        $this->assertSame('サンスクエアボウル', $summerB['venue_name']);
        $this->assertNotNull($summerB['final_result_url']);
        $this->assertSame('2026-07-28', $summerD['date']);
        $this->assertSame('賀茂ボール', $summerD['venue_name']);
        $this->assertSame('completed', $summer['status']);
        $this->assertSame(
            'https://www.jpba.or.jp/information/tournament/tournament2026/ST_Summer/Result/D_FinalResult.pdf',
            $summerD['final_result_url'],
        );

        $autumn = $editions['autumn'];
        $this->assertSame('scheduled', $autumn['status']);
        $this->assertSame('2026-09-29', $autumn['start_date']);
        $this->assertSame('2026-10-21', $autumn['end_date']);
        $this->assertSame(
            [
                ['A', '2026-09-29', '宇都宮第二トーヨーボウル'],
                ['B', '2026-10-21', 'ハマボール'],
                ['C', '2026-09-29', '稲沢グランドボウル'],
                ['D', '2026-10-16', 'メリーランドタケオボウル'],
            ],
            collect($autumn['events'])
                ->map(fn (array $event): array => [$event['venue_code'], $event['date'], $event['venue_name']])
                ->all(),
        );
        $this->assertCount(4, collect($autumn['events'])->whereNull('final_result_url'));
    }
}

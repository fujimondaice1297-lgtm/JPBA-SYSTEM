<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Official2026LateJulyDatasetTest extends TestCase
{
    public function test_current_aggregate_and_rankings_match_the_audited_sources(): void
    {
        $dataset = $this->json('jpba_official_2026_results.json');

        self::assertCount(27, $dataset['events']);
        self::assertSame(79, array_sum(array_map(
            static fn (array $event): int => count($event['snapshots']),
            $dataset['events'],
        )));

        $rankings = collect($dataset['official_rankings'])->keyBy('gender');
        self::assertSame('2026-09-08', $rankings['M']['as_of_date']);
        self::assertCount(328, $rankings['M']['rows']);
        self::assertSame('2026-07-27', $rankings['F']['as_of_date']);
        self::assertCount(212, $rankings['F']['rows']);

        $rookies = collect($dataset['events'])->firstWhere('key', 'rookies_m_2026');
        self::assertNotNull($rookies);
        self::assertFalse($rookies['tournament']['counts_for_official_points']);
        self::assertTrue($rookies['use_snapshot_prize']);
        $rookiesFinal = collect($rookies['snapshots'])->firstWhere('result_code', 'final');
        self::assertCount(61, $rookiesFinal['rows']);
        self::assertSame('M00001446', $rookiesFinal['rows'][0]['license_no']);
        self::assertSame(5344, $rookiesFinal['rows'][0]['total_pin']);
        self::assertSame(676, array_sum(array_column($rookiesFinal['rows'], 'games')));
        self::assertSame(142227, array_sum(array_column($rookiesFinal['rows'], 'total_pin')));
        self::assertSame(0, array_sum(array_column($rookiesFinal['rows'], 'points')));
        self::assertSame(2000000, array_sum(array_column($rookiesFinal['rows'], 'prize_money')));

        $koreanCup = collect($dataset['events'])->firstWhere('key', 'korean_cup_2026');
        self::assertNotNull($koreanCup);
        self::assertTrue($koreanCup['use_snapshot_points']);
        self::assertTrue($koreanCup['use_snapshot_prize']);
        $koreanCupFinal = collect($koreanCup['snapshots'])->firstWhere('result_code', 'final');
        self::assertCount(27, $koreanCupFinal['rows']);
        self::assertSame('M00001287', $koreanCupFinal['rows'][0]['license_no']);
        self::assertSame(9, $koreanCupFinal['rows'][0]['ranking']);
        self::assertSame(303, array_sum(array_column($koreanCupFinal['rows'], 'games')));
        self::assertSame(67276, array_sum(array_column($koreanCupFinal['rows'], 'total_pin')));
        self::assertSame(915, array_sum(array_column($koreanCupFinal['rows'], 'points')));
        self::assertSame(2407000, array_sum(array_column($koreanCupFinal['rows'], 'prize_money')));

        $summerD = collect($dataset['events'])->firstWhere('key', 'stsu_d');
        self::assertNotNull($summerD);
        self::assertSame(314800, array_sum(array_column($summerD['prize_distributions'], 'amount')));
        $summerDFinal = collect($summerD['snapshots'])->firstWhere('result_code', 'final');
        self::assertSame('M00000905', $summerDFinal['rows'][0]['license_no']);
        self::assertSame(70, $summerDFinal['rows'][0]['points']);

        $ooka = collect($dataset['events'])->firstWhere('key', 'ooka_2026');
        self::assertNotNull($ooka);
        self::assertCount(7, $ooka['tournament']['round_robin_pairings']);
        foreach ($ooka['tournament']['round_robin_pairings'] as $round) {
            self::assertCount(4, $round['pairs']);
            self::assertCount(8, array_unique(array_merge(
                array_column($round['pairs'], 'left_license'),
                array_column($round['pairs'], 'right_license'),
            )));
        }
        $ookaRoundRobin = collect($ooka['snapshots'])->firstWhere('result_code', 'round_robin_total');
        self::assertSame(
            ['F00000478', 'F00000599', 'F00000582'],
            array_column(array_slice($ookaRoundRobin['rows'], 0, 3), 'license_no'),
        );
        $ookaFinal = collect($ooka['snapshots'])->firstWhere('result_code', 'final');
        self::assertSame('F00000478', $ookaFinal['rows'][0]['license_no']);
    }

    public function test_late_july_detail_and_final_counts_are_complete(): void
    {
        $season = $this->json('jpba_official_2026_season_trial_detail_scores.json');
        $summerD = collect($season['events'])->firstWhere('key', 'stsu_d');
        self::assertSame(290, array_sum(array_column($summerD['prelim'], 'games_count')));
        self::assertSame(80, array_sum(array_column($summerD['semifinal'], 'games_count')));
        self::assertCount(10, $summerD['shootout']['rows']);

        $standard = $this->json('jpba_official_2026_standard_detail_scores.json');
        $ooka = collect($standard['events'])->firstWhere('key', 'ooka_2026');
        self::assertSame(1383, $ooka['expected_score_count']);
        self::assertSame(100, $ooka['expected_player_count']);

        $finals = $this->json('jpba_official_2026_standard_final_scores.json');
        $ookaFinal = collect($finals['events'])->firstWhere('key', 'ooka_2026');
        self::assertCount(2, $ookaFinal['score_sheets']);
        self::assertSame(40, array_sum(array_map(
            static fn (array $sheet): int => array_sum(array_map(
                static fn (array $player): int => count($player['frames']),
                $sheet['players'],
            )),
            $ookaFinal['score_sheets'],
        )));
    }

    /** @return array<string,mixed> */
    private function json(string $filename): array
    {
        return json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/database/data/'.$filename),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Official2026SeptemberDetailDatasetTest extends TestCase
{
    public function test_every_imported_game_matches_the_official_aggregate_and_source_hashes(): void
    {
        $aggregate = $this->dataset('jpba_official_2026_results.json');
        $details = $this->dataset('jpba_official_2026_standard_detail_scores.json');

        foreach (['rookies_m_2026' => 676, 'korean_cup_2026' => 303] as $key => $expectedGames) {
            $event = collect($aggregate['events'])->firstWhere('key', $key);
            $detail = collect($details['events'])->firstWhere('key', $key);
            self::assertArrayNotHasKey('verified_aggregate_only', $event);
            self::assertSame($expectedGames, $detail['expected_score_count']);
            $sourceAliases = [];
            foreach ($detail['sources'] as $source) {
                self::assertSame($aggregate['source_sha256'][$source['alias']], $source['sha256']);
                self::assertStringStartsWith('https://www.jpba.or.jp/', $source['url']);
                $sourceAliases[] = $source['alias'];
            }

            $actual = [];
            foreach ($detail['stages'] as $stage) {
                foreach ($stage['rows'] as $row) {
                    foreach ($row['source_aliases'] as $alias) {
                        self::assertContains($alias, $sourceAliases);
                    }
                    $actual[$row['license_no']] ??= ['games' => 0, 'pins' => 0];
                    $actual[$row['license_no']]['games'] += count($row['games']);
                    $actual[$row['license_no']]['pins'] += array_sum(array_column($row['games'], 'score'));
                }
            }

            self::assertCount(count($event['snapshots'][0]['rows']), $actual);
            foreach ($event['snapshots'][0]['rows'] as $row) {
                self::assertSame($row['games'], $actual[$row['license_no']]['games'], $key.' '.$row['license_no']);
                self::assertSame($row['total_pin'], $actual[$row['license_no']]['pins'], $key.' '.$row['license_no']);
            }
        }
    }

    public function test_right_side_bracket_game_order_agrees_with_the_certified_perfect(): void
    {
        $details = $this->dataset('jpba_official_2026_standard_detail_scores.json');
        $korean = collect($details['events'])->firstWhere('key', 'korean_cup_2026');
        $round = collect($korean['stages'])->firstWhere('stage', '準決勝トーナメント2回戦');
        $asato = collect($round['rows'])->firstWhere('license_no', 'M00001423');
        self::assertSame(['game_number' => 1, 'score' => 300], $asato['games'][0]);
        self::assertSame(['game_number' => 2, 'score' => 255], $asato['games'][1]);
    }

    private function dataset(string $filename): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/database/data/'.$filename), true, flags: JSON_THROW_ON_ERROR);
    }
}

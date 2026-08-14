<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPlayerOfficialHistoryVisibilityTest extends TestCase
{
    public function test_only_latest_ten_annual_rows_are_initially_visible_and_tournaments_are_grouped_by_year(): void
    {
        $annualRecords = collect(range(2026, 2016))->map(fn (int $year): array => [
            'season_key' => (string) $year,
            'season_start_year' => $year,
            'season_end_year' => $year,
            'ranking_rank' => 1,
            'games' => 100,
            'total_pin' => 22000,
            'points' => '1000.00',
            'average' => '220.00',
            'prize_money' => 500000,
            'is_live_ranking' => $year === 2026,
            'ranking_as_of_date' => $year === 2026 ? '2026-07-21' : null,
        ]);

        $view = $this->baseView();
        $view['annual_records'] = $annualRecords;
        $view['tournament_history_by_year'] = collect(range(2026, 2021))
            ->mapWithKeys(fn (int $year): array => [
                $year => collect([[
                    'season_year' => $year,
                    'held_on' => $year . '-04-01',
                    'tournament_name' => $year . '年テスト公式大会',
                    'ranking_rank' => 3,
                    'average' => '221.25',
                    'prize_money' => 300000,
                ]]),
            ]);

        $html = view('public.players.show', compact('view'))->render();
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);

        $annualRows = $xpath->query('//tr[@data-annual-record]');
        $this->assertSame(11, $annualRows->length);
        $this->assertFalse($annualRows->item(9)->hasAttribute('hidden'));
        $this->assertTrue($annualRows->item(10)->hasAttribute('hidden'));

        $this->assertStringContainsString('data-annual-record-toggle', $html);
        $this->assertStringContainsString('もっと見る', $html);
        $this->assertStringContainsString('（7/21現在）', $html);
        $this->assertStringContainsString('data-tournament-history-year="2026"', $html);
        $this->assertStringContainsString('2026年度（1大会）', $html);
        $this->assertStringContainsString('2026年テスト公式大会', $html);

        $tournamentYears = $xpath->query('//details[@data-tournament-history-year]');
        $this->assertSame(6, $tournamentYears->length);
        $this->assertFalse($tournamentYears->item(4)->hasAttribute('hidden'));
        $this->assertTrue($tournamentYears->item(5)->hasAttribute('hidden'));
        $this->assertStringContainsString('data-tournament-history-toggle', $html);
        $this->assertFalse($tournamentYears->item(0)->hasAttribute('open'));
    }

    private function baseView(): array
    {
        return [
            'name' => 'テスト選手',
            'license_no' => '1',
            'sex' => '男性',
            'is_female' => false,
            'organization' => ['name' => null, 'url' => null],
            'official_titles_count' => 0,
            'titles' => collect(),
            'season_trial_titles_count' => 0,
            'season_trial_titles' => collect(),
            'official_stats' => [],
            'award_counts' => [],
            'achievement_sections' => collect(),
            'achievement_summary' => ['other' => ['total' => 0]],
            'sns' => [],
        ];
    }
}

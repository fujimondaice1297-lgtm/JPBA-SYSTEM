<?php

namespace Tests\Unit;

use App\Models\ProBowler;
use App\Services\JpbaOfficialPlayerTitleHistoryService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class JpbaOfficialPlayerTitleHistoryServiceTest extends TestCase
{
    public function test_it_extracts_profile_year_urls_for_the_requested_license(): void
    {
        $html = <<<'HTML'
        <html><body class="player-detail">
          <a href="/player1/detail.html?id=M00001219&amp;year=2010#entry">2010年度</a>
          <a href="/player1/detail.html?id=M00001219&amp;year=2009#entry">2009年度</a>
          <a href="/player1/detail.html?id=M00000018&amp;year=2010#entry">別選手</a>
        </body></html>
        HTML;

        $urls = (new JpbaOfficialPlayerTitleHistoryService)->parseYearUrls($html, 'M1219');

        $this->assertSame([2010, 2009], array_keys($urls));
        $this->assertSame(
            'https://www.jpba1.jp/player1/detail.html?id=M00001219&year=2010#entry',
            $urls[2010]
        );
    }

    public function test_it_extracts_only_first_place_rows_and_separates_season_trials(): void
    {
        $html = <<<'HTML'
        <html><body class="player-detail"><table>
          <tr><th>開催年</th><th>開催日</th><th>大会名</th><th>順位</th><th>獲得賞金</th><th>アベレージ</th></tr>
          <tr><td>2010</td><td>11/4</td><td>第34回ABSジャパンＯＰ</td><td>1位</td><td>\15,100,000</td><td>235.68</td></tr>
          <tr><td>2010</td><td>10/15</td><td>シーズンT2010オータムS</td><td>1位</td><td>\159,800</td><td>226.07</td></tr>
          <tr><td>2010</td><td>9/1</td><td>第5回MKチャリティカップ</td><td>2位</td><td>\750,000</td><td>230.34</td></tr>
        </table></body></html>
        HTML;
        $bowler = new ProBowler([
            'license_no' => 'M00001219',
            'license_no_num' => 1219,
            'name_kanji' => '川添奨太',
        ]);
        $bowler->id = 13575;
        $service = new JpbaOfficialPlayerTitleHistoryService;

        $wins = $service->parseWinsForYear(
            $html,
            $bowler,
            2010,
            'https://www.jpba1.jp/player1/detail.html?id=M00001219&year=2010#entry'
        );

        $this->assertCount(2, $wins);
        $this->assertSame('normal', $wins[0]['title_category']);
        $this->assertSame('2010-11-04', $wins[0]['won_date']);
        $this->assertSame('season_trial', $wins[1]['title_category']);
        $this->assertSame('2010-10-15', $wins[1]['won_date']);
    }

    public function test_title_fingerprints_match_profile_and_tournament_page_variants(): void
    {
        $service = new JpbaOfficialPlayerTitleHistoryService;

        $this->assertSame(
            $service->titleFingerprint('JPBAシーズントライアル2016 スプリングシリーズ'),
            $service->titleFingerprint('シーズンT2016スプリングシリーズ')
        );
        $this->assertSame(
            $service->titleFingerprint('JPBAシーズントライアル2016 オータムシリーズ'),
            $service->titleFingerprint('ST2016オータムS A会場')
        );
        $this->assertSame(
            $service->titleFingerprint('中日杯2018東海オープン'),
            $service->titleFingerprint('中日杯2018東海オープンボウリングトーナメント')
        );
        $this->assertSame('season_trial', $service->titleCategory('ST2014オータムシリーズＡ会場'));
        $this->assertSame('season_trial', $service->titleCategory('シーズントライラウ2012ウィンターS'));
    }

    public function test_it_excludes_selection_and_qualifying_round_wins(): void
    {
        $method = new ReflectionMethod(JpbaOfficialPlayerTitleHistoryService::class, 'isTitleEvent');
        $service = new JpbaOfficialPlayerTitleHistoryService;

        $this->assertFalse($method->invoke($service, "East Open Tournament \u{9078}\u{629C}"));
        $this->assertFalse($method->invoke($service, "Official \u{4E88}\u{9078}\u{4F1A}"));
        $this->assertFalse($method->invoke($service, "Dream Match \u{4E88}\u{9078}\u{30D5}\u{30A1}\u{30A4}\u{30CA}\u{30EB}\u{30E9}\u{30A6}\u{30F3}\u{30C9}"));
        $this->assertFalse($method->invoke($service, "ROUND1 GRAND CHAMPIONSHIP BOWLING 2026 JPBA\u{4E88}\u{9078}\u{30E9}\u{30A6}\u{30F3}\u{30C9} A\u{4F1A}\u{5834} \u{7537}\u{5B50}R"));
        $this->assertFalse($method->invoke($service, "ROUND1 CUP \u{30D7}\u{30ED}\u{4E88}\u{9078} \u{7B2C}1\u{4F1A}\u{5834}"));
        $this->assertTrue($method->invoke($service, 'ROUND1 GRAND CHAMPIONSHIP BOWLING 2025 JPBA FINAL'));
        $this->assertTrue($method->invoke($service, 'MK Charity Cup'));
    }
}

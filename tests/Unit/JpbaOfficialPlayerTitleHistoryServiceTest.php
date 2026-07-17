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
        $this->assertSame(
            $service->titleFingerprint('コカコーラ2016千葉オープン'),
            $service->titleFingerprint('コカ・コーラカップ2016千葉オープンボウリングトーナメント')
        );
        $this->assertSame(
            $service->titleFingerprint('第9回HCプロボウリングマスターズ'),
            $service->titleFingerprint('第9回HANDA CUPプロボウリングマスターズ')
        );
        $this->assertSame(
            $service->titleFingerprint('JPBA創立50周年記念レギュラーの部'),
            $service->titleFingerprint('公益社団法人日本プロボウリング協会創立50周年記念大会')
        );
        $this->assertSame(
            $service->titleFingerprint('R1 GCB JPBA決勝大会R'),
            $service->titleFingerprint('ROUND1 GRAND CHAMPIONSHIP BOWLING 2018 JPBA決勝大会')
        );
        $this->assertSame(
            $service->titleFingerprint('R1 GCSB 2019 FINAL R部門'),
            $service->titleFingerprint('ROUND1 GRAND CHAMPIONSHIP BOWLING 2019 FINAL')
        );
        $this->assertNotSame(
            $service->titleFingerprint('ROUND1 GCB 2018 R'),
            $service->titleFingerprint('ROUND1 GRAND CHAMPIONSHIP BOWLING 2018 三団体グランドチャンピオン大会')
        );
        $this->assertSame(
            $service->titleFingerprint('ｽｶｲAｶｯﾌﾟ2019女子新人戦'),
            $service->titleFingerprint('スカイAカップ 2019プロボウリングレディース新人戦')
        );
        $this->assertSame(
            $service->titleFingerprint('ｺｶｺｰﾗｶｯﾌﾟ2019千葉ｵｰﾌﾟﾝ女子'),
            $service->titleFingerprint('コカ・コーラカップ')
        );
        $this->assertSame(
            $service->titleFingerprint('第41回JLBCプリンスカップ'),
            $service->titleFingerprint('第41回JLBCクイーンズオープンプリンスカップ')
        );
        $this->assertSame(
            $service->titleFingerprint('Ｈ.Ｃ 第47回全日本女子プロ'),
            $service->titleFingerprint('HANDA CUP 第47回全日本女子プロボウリング選手権大会')
        );
        $this->assertSame(
            $service->titleFingerprint('第39回ジャパンオープン'),
            $service->titleFingerprint('第39回STORMジャパンオープンボウリング選手権')
        );
        $this->assertSame(
            $service->titleFingerprint('グリコ17アイス杯第５回プロアマ'),
            $service->titleFingerprint('「グリコセブンティーンアイス杯」第5回プロアマボウリングトーナメント')
        );
        $this->assertSame(
            $service->titleFingerprint('第３２回六甲クイーンズＯＰ'),
            $service->titleFingerprint('第32回六甲クイーンズオープントーナメント')
        );
        $this->assertSame('season_trial', $service->titleCategory('ST2014オータムシリーズＡ会場'));
        $this->assertSame('season_trial', $service->titleCategory('シーズントライラウ2012ウィンターS'));
        $this->assertSame('season_trial', $service->titleCategory('STウィンターシリーズC'));
        $this->assertSame('normal', $service->titleCategory('STORMジャパンオープン'));
        $this->assertSame(
            'JPBAシーズントライアル2008 サマーシリーズ',
            $service->titleDisplayName('ｼｰｽﾞﾝTｻﾏｰｼﾘｰｽﾞ', 2008)
        );
        $this->assertSame(
            'JPBAシーズントライアル2013 ウィンターシリーズ',
            $service->titleDisplayName('STウィンターシリーズC', 2013)
        );
        $this->assertSame(
            'JPBAシーズントライアル2011 サマーシリーズ',
            $service->titleDisplayName('ｼｰｽﾞﾝﾄﾗｲｱﾙ2011サマー', 2011)
        );
        $this->assertSame(
            'JPBAシーズントライアル2009 ウィンターシリーズ',
            $service->titleDisplayName('‘09ｼｰｽﾞﾝﾄﾗｲｱﾙｳｨﾝﾀｰS', 2009)
        );
        $this->assertSame(
            'JPBAシーズントライアル2024 スプリングシリーズ',
            $service->titleDisplayName('JPBAシーズントライアル2024 スプリングシリーズB', 2024)
        );
        $this->assertSame('KUWATA CUP 2023男子', $service->titleDisplayName('KUWATA CUP 2023男子', 2023));
        $this->assertSame(
            'HANDA CUP 第47回全日本女子プロボウリング選手権大会',
            $service->titleDisplayName('Ｈ.Ｃ 第47回全日本女子プロ', 2015)
        );
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
        $this->assertFalse($method->invoke($service, 'JPBAプレイヤーズドリームマッチ2022C'));
        $this->assertFalse($method->invoke($service, 'プレイヤーズドリームマッチ２０２２Ｅ'));
        $this->assertFalse($method->invoke($service, '第46回STORMジャパンオープン 男子オールエベンツ'));
        $this->assertFalse($method->invoke($service, 'JAPAN OPEN MEN ALL EVENTS'));
        $this->assertFalse($method->invoke($service, 'ROUND1 GCB 2018 R'));
        $this->assertFalse($method->invoke($service, 'ROUND1 GRAND CHAMPIONSHIP BOWLING 2018 三団体グランドチャンピオン大会'));
        $this->assertFalse($method->invoke($service, '第15回全日本ミックス'));
        $this->assertFalse($method->invoke($service, '第25回全日本ﾐｯｸｽﾀﾞﾌﾞﾙｽ'));
        $this->assertFalse($method->invoke($service, '第28回全日本ﾐｯｸｽﾀﾞﾌﾞﾙｽ'));
        $this->assertFalse($method->invoke($service, '第29回全日本ﾐｯｸｽﾀﾞﾌﾞﾙｽ'));
        $this->assertFalse($method->invoke($service, '順位決定戦'));
        $this->assertFalse($method->invoke($service, '記録会'));
        $this->assertFalse($method->invoke($service, '2021年度 下半期女子トーナメント出場優先順位決定戦'));
        $this->assertFalse($method->invoke($service, '2022年度 下半期女子トーナメント出場優先順位戦'));
        $this->assertFalse($method->invoke($service, '2016下半期女子順位戦'));
        $this->assertFalse($method->invoke($service, '2004下半期順位決定戦'));
        $this->assertTrue($method->invoke($service, 'JPBAプレイヤーズドリームマッチ2022'));
        $this->assertTrue($method->invoke($service, 'JPBAプレイヤーズドリームマッチ2023A'));
        $this->assertTrue($method->invoke($service, 'R1 GCB JPBA決勝大会R'));
        $this->assertTrue($method->invoke($service, 'ROUND1 GRAND CHAMPIONSHIP BOWLING 2025 JPBA FINAL'));
        $this->assertTrue($method->invoke($service, '第30回全日本ミックスダブルス'));
        $this->assertTrue($method->invoke($service, 'MK Charity Cup'));
    }
}

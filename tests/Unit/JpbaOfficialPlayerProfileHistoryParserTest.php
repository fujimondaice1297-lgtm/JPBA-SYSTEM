<?php

namespace Tests\Unit;

use App\Services\JpbaOfficialPlayerProfileService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JpbaOfficialPlayerProfileHistoryParserTest extends TestCase
{
    public function test_it_parses_annual_records_combined_seasons_and_tournament_history(): void
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="ja">
<head><title>テスト選手</title></head>
<body>
<div class="player-detail">
  <table>
    <tr><th>年度</th><th>順位</th><th>ゲーム数</th><th>トータルピン</th><th>ポイント</th><th>アベレージ</th><th>獲得賞金</th></tr>
    <tr><td>2026年</td><td>11位</td><td>90</td><td>19,887</td><td>735</td><td>220.96</td><td>￥700,000</td></tr>
    <tr><td>2020-21年</td><td>3位</td><td>256</td><td>54,954</td><td>3,750</td><td>214.66</td><td>￥3,734,600</td></tr>
  </table>
  <table style="display:none">
    <tr><td>2019年</td><td>1位</td><td>217</td><td>48,426</td><td>4,047</td><td>223.16</td><td>￥5,666,200</td></tr>
  </table>
  <a href="/player1/detail.html?id=M00001219&amp;year=2026#entry">2026年度</a>
  <a href="/player1/detail.html?id=M00001219&amp;year=2010#entry">2010年度</a>
  <table>
    <tr><th>開催年</th><th>開催日</th><th>大会名</th><th>順位</th><th>獲得賞金</th><th>アベレージ</th></tr>
    <tr><td>2010</td><td>4/20</td><td>テスト大会</td><td>2位</td><td>￥245,700</td><td>219.66</td></tr>
    <tr><td>2010</td><td>5/20</td><td>順位なし大会</td><td></td><td>￥0</td><td>200.62</td></tr>
  </table>
</div>
</body>
</html>
HTML;

        $profile = app(JpbaOfficialPlayerProfileService::class)->parse(
            $html,
            'https://www.jpba1.jp/player1/detail.html?id=M00001219&year=2010'
        );

        $this->assertCount(3, $profile['annual_records']);
        $this->assertSame('2020-21', $profile['annual_records'][1]['season_key']);
        $this->assertSame(2020, $profile['annual_records'][1]['season_start_year']);
        $this->assertSame(2021, $profile['annual_records'][1]['season_end_year']);
        $this->assertSame(3750, (int) $profile['annual_records'][1]['points']);
        $this->assertSame('2019', $profile['annual_records'][2]['season_key']);
        $this->assertSame([2026, 2010], $profile['participation_years']);

        $this->assertCount(2, $profile['tournament_records']);
        $this->assertSame('2010-04-20', $profile['tournament_records'][0]['held_on']);
        $this->assertSame(2, $profile['tournament_records'][0]['ranking_rank']);
        $this->assertNull($profile['tournament_records'][1]['ranking_rank']);
        $this->assertSame(0, $profile['tournament_records'][1]['prize_money']);
    }

    public function test_it_fetches_multiple_tournament_years_concurrently(): void
    {
        Http::fake(function (Request $request) {
            preg_match('/[?&]year=(\d{4})/', $request->url(), $matches);
            $year = (int) ($matches[1] ?? 0);

            return Http::response(
                '<div class="player-detail"><table>'
                . '<tr><th>開催年</th><th>開催日</th><th>大会名</th><th>順位</th><th>獲得賞金</th><th>アベレージ</th></tr>'
                . "<tr><td>{$year}</td><td>4/1</td><td>{$year}年大会</td><td>1位</td><td>￥100,000</td><td>220.00</td></tr>"
                . '</table></div>'
            );
        });

        $result = app(JpbaOfficialPlayerProfileService::class)->fetchTournamentYears(
            'M00001219',
            [2011, 2010],
            2,
            0
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame([2011, 2010], array_keys($result['profiles']));
        $this->assertSame(
            '2010年大会',
            $result['profiles'][2010]['tournament_records'][0]['tournament_name']
        );
        Http::assertSentCount(2);
    }
}

<?php

namespace Tests\Unit;

use App\Services\JpbaTournamentArchiveService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class JpbaTournamentArchiveServiceTest extends TestCase
{
    public function test_it_parses_a_legacy_tournament_page_and_collects_localizable_assets(): void
    {
        $html = <<<'HTML'
        <html><body><td class="td_main">
          <h5>第39回 テスト大会</h5>
          <h2>開催要項</h2>
          <table><tr><td>期　日</td><td>2016年12月21日(水)～24日(土)</td></tr></table>
          <p>開催要項と大会成績を掲載します。</p>
          <p>https://www.jpba.or.jp/information/tournament/old.html</p>
          <a href="PDF/guide.pdf">開催要項</a>
          <a href="Result/final.pdf">最終成績</a>
          <img src="Photo/winner.jpg" alt="優勝者">
        </td></body></html>
        HTML;

        $row = app(JpbaTournamentArchiveService::class)->parseTournamentHtml(
            $html,
            'https://www.jpba.or.jp/information/tournament/tournament2016/test/index.html',
            2016,
        );

        $this->assertNotNull($row);
        $this->assertSame('第39回 テスト大会', $row['title']);
        $this->assertSame('2016-12-21', $row['start_on']);
        $this->assertSame('2016-12-24', $row['end_on']);
        $this->assertSame('completed', $row['status']);
        $this->assertCount(3, $row['assets']);
        $this->assertSame('result', collect($row['assets'])->firstWhere('title', '最終成績')['type']);
        $this->assertStringContainsString('開催要項と大会成績', $row['body_html']);
        $this->assertStringNotContainsString('jpba.or.jp', $row['body_html']);
    }

    public function test_it_marks_a_future_tournament_page_as_scheduled(): void
    {
        CarbonImmutable::setTestNow('2026-09-05 12:00:00');

        try {
            $row = app(JpbaTournamentArchiveService::class)->parseTournamentHtml(
                '<html><body><td class="td_main"><h4>今後の大会</h4><p>期 日 2026年10月10日</p></td></body></html>',
                'https://www.jpba.or.jp/information/tournament/tournament2026/test/index.html',
                2026,
            );

            $this->assertNotNull($row);
            $this->assertSame('scheduled', $row['status']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_it_prefers_the_document_title_over_a_promotional_heading(): void
    {
        $row = app(JpbaTournamentArchiveService::class)->parseTournamentHtml(
            '<html><head><title>正式な大会名</title></head><body><td class="td_main"><h5>地域連携事業</h5><h4>正式な大会名</h4><p>2026年9月1日</p></td></body></html>',
            'https://www.jpba.or.jp/information/tournament/tournament2026/test/index.html',
            2026,
        );

        $this->assertNotNull($row);
        $this->assertSame('正式な大会名', $row['title']);
    }

    public function test_it_parses_a_date_range_without_a_period_label(): void
    {
        $row = app(JpbaTournamentArchiveService::class)->parseTournamentHtml(
            '<html><body><td class="td_main"><h5>シーズントライアル</h5><p>2026年10月31日(土)～3日(火)</p></td></body></html>',
            'https://www.jpba.or.jp/information/tournament/tournament2026/test/index.html',
            2026,
        );

        $this->assertNotNull($row);
        $this->assertSame('2026-10-31', $row['start_on']);
        $this->assertSame('2026-11-03', $row['end_on']);
    }

    public function test_it_accepts_a_start_day_without_the_day_suffix(): void
    {
        $row = app(JpbaTournamentArchiveService::class)->parseTournamentHtml(
            '<html><body><td class="td_main"><h5>全日本選手権</h5><p>期 日 2026年12月8(金)～12月10日(日)</p></td></body></html>',
            'https://www.jpba.or.jp/information/tournament/tournament2026/test/index.html',
            2026,
        );

        $this->assertNotNull($row);
        $this->assertSame('2026-12-08', $row['start_on']);
        $this->assertSame('2026-12-10', $row['end_on']);
    }
}

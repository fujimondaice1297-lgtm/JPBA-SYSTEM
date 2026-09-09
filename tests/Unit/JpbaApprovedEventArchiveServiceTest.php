<?php

namespace Tests\Unit;

use App\Services\JpbaApprovedEventArchiveService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class JpbaApprovedEventArchiveServiceTest extends TestCase
{
    public function test_it_parses_approved_events_and_deduplicates_nested_rows(): void
    {
        CarbonImmutable::setTestNow('2026-09-08 12:00:00');

        try {
            $html = <<<'HTML'
            <html><body><table><tr><td><table><tr><td>
              <p id="event1">2026/8/22-23 「関東チャリティートーナメント」</p>
              <p>＜会場＞ テストボウル</p>
              <p>承認番号：A-123</p>
              <a href="files/guide.pdf">開催要項 PDF942KB</a>
              <a href="files/OilPattern.pdf">オイルパターン PDF120KB</a>
              <a href="files/result.pdf">最終成績 PDF2284KB</a>
            </td></tr></table></td></tr></table></body></html>
            HTML;

            $rows = app(JpbaApprovedEventArchiveService::class)->parseApprovedEventHtml(
                $html,
                'https://www.jpba.or.jp/information/tournament/tournament2026/event_result.html',
                2026,
            );

            $this->assertCount(1, $rows);
            $this->assertSame('approved_event', $rows[0]['classification']);
            $this->assertSame('関東チャリティートーナメント', $rows[0]['title']);
            $this->assertSame('2026-08-22', $rows[0]['start_on']);
            $this->assertSame('2026-08-23', $rows[0]['end_on']);
            $this->assertSame('テストボウル', $rows[0]['venue_name']);
            $this->assertSame('A-123', $rows[0]['approval_number']);
            $this->assertSame('completed', $rows[0]['status']);
            $this->assertCount(3, $rows[0]['assets']);
            $this->assertSame('oil_pattern', collect($rows[0]['assets'])->firstWhere('title', 'オイルパターン')['type']);
            $this->assertSame('result', collect($rows[0]['assets'])->firstWhere('title', '最終成績')['type']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_it_handles_a_cross_month_date_range_and_future_status(): void
    {
        CarbonImmutable::setTestNow('2026-09-08 12:00:00');

        try {
            $rows = app(JpbaApprovedEventArchiveService::class)->parseApprovedEventHtml(
                '<html><body><table><tr><td><p>2026/10/31-3 「未来イベント」</p><p>＜会場＞ 未来ボウル</p></td></tr></table></body></html>',
                'https://www.jpba.or.jp/information/tournament/tournament2026/event_result.html',
                2026,
            );

            $this->assertCount(1, $rows);
            $this->assertSame('2026-11-03', $rows[0]['end_on']);
            $this->assertSame('scheduled', $rows[0]['status']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_it_parses_legacy_unquoted_event_without_a_venue(): void
    {
        $rows = app(JpbaApprovedEventArchiveService::class)->parseApprovedEventHtml(
            '<html><body><table><tr><td><p>2018/11/24-25 第12回 スマイルフィールド。カップ</p><a href="final.pdf">最終成績 PDF234KB</a></td></tr></table></body></html>',
            'https://www.jpba.or.jp/information/tournament/tournament2018/event_result.html',
            2018,
        );

        $this->assertCount(1, $rows);
        $this->assertSame('第12回 スマイルフィールド。カップ', $rows[0]['title']);
        $this->assertSame('2018-11-24', $rows[0]['start_on']);
        $this->assertSame('2018-11-25', $rows[0]['end_on']);
        $this->assertNull($rows[0]['venue_name']);
        $this->assertSame('result', $rows[0]['assets'][0]['type']);
    }

    public function test_it_uses_the_page_year_when_a_legacy_date_omits_the_year(): void
    {
        $rows = app(JpbaApprovedEventArchiveService::class)->parseApprovedEventHtml(
            '<html><body><table><tr><td><p>11/23(月祝)～24(火) U-40プロアマトーナメント</p><a href="guide.pdf">開催要項</a></td></tr></table></body></html>',
            'https://www.jpba.or.jp/information/tournament/tournament2015/event_result.html',
            2015,
        );

        $this->assertCount(1, $rows);
        $this->assertSame('2015-11-23', $rows[0]['start_on']);
        $this->assertSame('2015-11-24', $rows[0]['end_on']);
        $this->assertSame('U-40プロアマトーナメント', $rows[0]['title']);
    }
}

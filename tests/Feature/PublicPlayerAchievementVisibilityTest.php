<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class PublicPlayerAchievementVisibilityTest extends TestCase
{
    public function test_achievement_history_is_collapsed_immediately_after_titles_without_source_links(): void
    {
        $sourceUrl = 'https://www.jpba.or.jp/private-source-for-test.pdf';
        $perfect = (object) [
            'record_type' => 'perfect',
            'tournament_name' => 'テスト公式大会',
            'game_numbers' => '予選3G目',
            'frame_number' => null,
            'series_label' => null,
            'series_total' => null,
            'awarded_on' => Carbon::parse('2016-05-20'),
            'source_url' => $sourceUrl,
        ];
        $eightHundred = (object) [
            'record_type' => 'eight_hundred',
            'tournament_name' => 'テストシリーズ大会',
            'game_numbers' => null,
            'frame_number' => null,
            'series_label' => '準決勝前半シリーズ',
            'series_total' => 812,
            'awarded_on' => Carbon::parse('2016-08-10'),
            'source_url' => $sourceUrl,
        ];
        $sevenTen = (object) [
            'record_type' => 'seven_ten',
            'tournament_name' => 'テスト7－10大会',
            'game_numbers' => '予選2G目',
            'frame_number' => '7F',
            'series_label' => null,
            'series_total' => null,
            'awarded_on' => Carbon::parse('2016-09-15'),
            'source_url' => $sourceUrl,
        ];

        $view = $this->baseView();
        $view['achievement_sections'] = collect([
            $this->section('perfect', '公認パーフェクト', 2, [$perfect]),
            $this->section('eight_hundred', '公認800シリーズ', 1, [$eightHundred]),
            $this->section('seven_ten', '公認7－10メイド', 1, [$sevenTen]),
        ]);
        $view['achievement_summary'] = [
            'other' => [
                'perfect' => 1,
                'eight_hundred' => 0,
                'seven_ten' => 0,
                'total' => 1,
            ],
        ];

        $html = view('public.players.show', compact('view'))->render();

        $titlePosition = strpos($html, 'id="title-heading"');
        $achievementPosition = strpos($html, 'id="achievement-heading"');
        $this->assertNotFalse($titlePosition);
        $this->assertNotFalse($achievementPosition);
        $this->assertGreaterThan($titlePosition, $achievementPosition);

        $this->assertStringContainsString('data-achievement-count="perfect"', $html);
        $this->assertStringContainsString('data-profile-detail-toggle="achievements"', $html);
        $this->assertStringContainsString('data-achievement-item="perfect"', $html);
        $this->assertStringContainsString('2016年', $html);
        $this->assertStringContainsString('テスト公式大会', $html);
        $this->assertStringContainsString('予選3G目', $html);
        $this->assertStringContainsString('2016/05/20', $html);
        $this->assertStringContainsString('準決勝前半シリーズ', $html);
        $this->assertStringContainsString('3G合計 812', $html);
        $this->assertStringContainsString('予選2G目', $html);
        $this->assertStringContainsString('7F', $html);
        $this->assertStringContainsString('その他過去達成数', $html);
        $this->assertStringContainsString('現在確認できる大会分のみデータとして記載', $html);
        $this->assertStringNotContainsString($sourceUrl, $html);

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $panel = $xpath->query('//*[@data-profile-detail-panel="achievements"]')->item(0);

        $this->assertNotNull($panel);
        $this->assertTrue($panel->hasAttribute('hidden'));
    }

    private function section(
        string $type,
        string $label,
        int $total,
        array $records
    ): array {
        return [
            'type' => $type,
            'label' => $label,
            'total_count' => $total,
            'confirmed_count' => count($records),
            'other_count' => $total - count($records),
            'records' => collect($records),
        ];
    }

    private function baseView(): array
    {
        return [
            'name' => 'テスト選手',
            'license_no' => '1',
            'sex' => '男性',
            'is_female' => false,
            'organization' => ['name' => null, 'url' => null],
            'official_titles_count' => 1,
            'titles' => collect([(object) [
                'year' => 2026,
                'title_name' => 'テストタイトル',
                'won_date' => null,
            ]]),
            'season_trial_titles_count' => 0,
            'season_trial_titles' => collect(),
            'official_stats' => [],
            'award_counts' => [],
            'sns' => [],
        ];
    }
}

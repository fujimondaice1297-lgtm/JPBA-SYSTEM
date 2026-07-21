<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPlayerTitleVisibilityTest extends TestCase
{
    public function test_female_profile_hides_all_season_trial_information(): void
    {
        $html = $this->renderProfile(true);

        $this->assertStringContainsString('data-title-count="official"', $html);
        $this->assertStringContainsString('data-title-item="official"', $html);
        $this->assertStringNotContainsString('data-title-count="season-trial"', $html);
        $this->assertStringNotContainsString('data-title-section="season-trial"', $html);
        $this->assertStringNotContainsString('シーズントライアル優勝', $html);
    }

    public function test_male_profile_shows_season_trial_count_and_history(): void
    {
        $html = $this->renderProfile(false);

        $this->assertStringContainsString('data-title-count="official"', $html);
        $this->assertStringContainsString('data-title-item="official"', $html);
        $this->assertStringContainsString('data-title-count="season-trial"', $html);
        $this->assertStringContainsString('data-title-section="season-trial"', $html);
        $this->assertStringContainsString('data-title-item="season-trial"', $html);
    }

    private function renderProfile(bool $isFemale): string
    {
        $title = (object) [
            'year' => 2026,
            'title_name' => '公式大会',
            'won_date' => null,
        ];
        $seasonTrialTitle = (object) [
            'year' => 2026,
            'title_name' => 'JPBAシーズントライアル2026 サマーシリーズ',
            'won_date' => null,
        ];

        $view = [
            'name' => 'テスト選手',
            'license_no' => '1',
            'sex' => $isFemale ? '女性' : '男性',
            'is_female' => $isFemale,
            'organization' => ['name' => null, 'url' => null],
            'official_titles_count' => 1,
            'titles' => collect([$title]),
            'season_trial_titles_count' => 1,
            'season_trial_titles' => collect([$seasonTrialTitle]),
            'official_stats' => [],
            'award_counts' => [],
            'sns' => [],
        ];

        return view('public.players.show', compact('view'))->render();
    }
}

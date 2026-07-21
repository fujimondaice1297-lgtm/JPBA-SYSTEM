<?php

namespace Tests\Unit;

use App\Models\Tournament;
use App\Services\TournamentTitleSyncService;
use Tests\TestCase;

class TournamentTitleSyncServiceTest extends TestCase
{
    public function test_it_removes_season_trial_venue_suffix_from_profile_title(): void
    {
        $service = app(TournamentTitleSyncService::class);
        $tournament = new Tournament([
            'name' => 'メリーランドカップ JPBAシーズントライアル2026 サマーシリーズ B会場',
            'year' => 2026,
            'title_category' => 'season_trial',
        ]);

        $this->assertSame(
            'メリーランドカップ JPBAシーズントライアル2026 サマーシリーズ',
            $service->canonicalTitleName($tournament),
        );
    }

    public function test_it_excludes_qualifying_and_non_title_events(): void
    {
        $service = app(TournamentTitleSyncService::class);

        $this->assertFalse($service->isEligibleTitleTournament(new Tournament([
            'name' => 'ROUND1 GRAND CHAMPIONSHIP BOWLING 2026 JPBA予選ラウンド',
            'official_type' => 'official',
        ])));
        $this->assertFalse($service->isEligibleTitleTournament(new Tournament([
            'name' => '2026年度 下半期女子トーナメント出場優先順位決定戦',
            'official_type' => 'official',
        ])));
        $this->assertFalse($service->isEligibleTitleTournament(new Tournament([
            'name' => '承認イベント',
            'official_type' => 'approved',
        ])));
        $this->assertFalse($service->isEligibleTitleTournament(new Tournament([
            'name' => 'タイトル対象外大会',
            'official_type' => 'official',
            'title_category' => 'excluded',
        ])));
        $this->assertFalse($service->isEligibleTitleTournament(new Tournament([
            'name' => '2018 ラウンドワングランドチャンピオンシップボウリング 3団体ファイナル',
            'official_type' => 'official',
        ])));
        $this->assertFalse($service->isEligibleTitleTournament(new Tournament([
            'name' => 'プレイヤーズドリームマッチ2022A',
            'official_type' => 'official',
        ])));
        $this->assertTrue($service->isEligibleTitleTournament(new Tournament([
            'name' => 'プレイヤーズドリームマッチ2022',
            'official_type' => 'official',
        ])));
    }
}

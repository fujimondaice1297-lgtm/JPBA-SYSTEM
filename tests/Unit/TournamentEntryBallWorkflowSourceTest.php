<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TournamentEntryBallWorkflowSourceTest extends TestCase
{
    public function test_staff_can_reach_and_manage_a_players_tournament_balls(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/TournamentEntryBallController.php')
        );
        $adminIndex = file_get_contents(
            resource_path('views/tournament_entries/admin_index.blade.php')
        );
        $entryBalls = file_get_contents(
            resource_path('views/member/entry_balls_edit.blade.php')
        );
        $tournamentIndex = file_get_contents(
            resource_path('views/tournaments/index.blade.php')
        );
        $memberDashboard = file_get_contents(
            resource_path('views/member/dashboard.blade.php')
        );
        $routes = file_get_contents(base_path('routes/web.php'));
        $entryAdminController = file_get_contents(
            app_path('Http/Controllers/TournamentEntryAdminController.php')
        );

        $this->assertIsString($controller);
        $this->assertStringContainsString('isStaffUser', $controller);
        $this->assertStringContainsString(
            '$entry->balls()->sync($targetIds->all())',
            $controller
        );
        $this->assertStringContainsString('approvedUsedBallIds', $controller);
        $this->assertStringContainsString('年度のスタッフ承認を受けていないボールは追加できません', $controller);
        $this->assertStringContainsString('resolveBallRegistrationLimit', $controller);
        $this->assertStringNotContainsString('MAX_BALLS_PER_ENTRY', $controller);
        $this->assertStringContainsString(
            'このエントリー選手のボールのみ登録できます',
            $controller
        );

        $this->assertIsString($adminIndex);
        $this->assertStringContainsString(
            "route('member.entries.balls.edit', \$entry->id)",
            $adminIndex
        );
        $this->assertStringContainsString('登録・閲覧', $adminIndex);
        $this->assertStringContainsString('参加選手を直接登録', $adminIndex);
        $this->assertStringContainsString('ボール登録状況', $adminIndex);
        $this->assertStringContainsString('検量証要確認', $adminIndex);
        $this->assertStringContainsString('期限間近あり', $adminIndex);
        $this->assertStringContainsString('上限超過', $adminIndex);
        $this->assertStringContainsString('ball_registration_status_label', $adminIndex);

        $this->assertStringContainsString('decorateEntriesWithBallRegistrationStatus', $entryAdminController);
        $this->assertStringContainsString('filterEntriesByBallStatus', $entryAdminController);
        $this->assertStringContainsString('tournamentEligibility', $entryAdminController);
        $this->assertStringContainsString("'ball_unregistered_count'", $entryAdminController);
        $this->assertStringContainsString("'ball_inspection_attention_count'", $entryAdminController);

        $this->assertIsString($entryBalls);
        $this->assertStringContainsString('スタッフ代理入力', $entryBalls);
        $this->assertStringContainsString('選択内容を保存', $entryBalls);
        $this->assertStringContainsString(
            'チェックを外したボールは大会から解除します',
            $entryBalls
        );

        $this->assertIsString($tournamentIndex);
        $this->assertStringContainsString('選手・ボール登録', $tournamentIndex);

        $this->assertIsString($memberDashboard);
        $this->assertStringContainsString(
            '大会エントリー・使用ボール登録',
            $memberDashboard
        );
        $this->assertStringContainsString('マイボール管理', $memberDashboard);

        $this->assertIsString($routes);
        $this->assertStringContainsString(
            "name('tournaments.entries.store')",
            $routes
        );

        $this->assertIsString($entryAdminController);
        $this->assertStringContainsString(
            'function storeEntry',
            $entryAdminController
        );
        $this->assertStringContainsString(
            'entry_registered_by_staff',
            $entryAdminController
        );
    }

    public function test_every_named_route_used_by_the_admin_entry_view_exists(): void
    {
        $source = file_get_contents(
            resource_path('views/tournament_entries/admin_index.blade.php')
        );
        $this->assertIsString($source);

        preg_match_all("/route\\('([^']+)'/", $source, $matches);
        $routeNames = array_values(array_unique($matches[1] ?? []));

        $this->assertNotEmpty($routeNames);
        foreach ($routeNames as $routeName) {
            $this->assertTrue(
                Route::has($routeName),
                "大会エントリー管理画面のルートが未定義です: {$routeName}"
            );
        }
    }

    public function test_score_views_link_players_to_their_tournament_registered_balls(): void
    {
        $scoreController = file_get_contents(
            app_path('Http/Controllers/ScoreController.php')
        );
        $ballController = file_get_contents(
            app_path('Http/Controllers/TournamentEntryBallController.php')
        );
        $detailView = file_get_contents(
            resource_path('views/scores/entry_balls_show.blade.php')
        );
        $linkPartial = file_get_contents(
            resource_path('views/scores/partials/player_ball_link.blade.php')
        );

        $this->assertTrue(Route::has('scores.entry_balls.show'));
        $this->assertIsString($scoreController);
        $this->assertStringContainsString('buildScoreEntryBallLookup', $scoreController);
        $this->assertStringContainsString("'ball_count' => (int) \$entry->balls_count", $scoreController);
        $this->assertIsString($ballController);
        $this->assertStringContainsString('function showForResults', $ballController);
        preg_match(
            '/public function showForResults.*?(?=public function bulkStore)/s',
            $ballController,
            $publicMethodMatches
        );
        $publicBallSource = $publicMethodMatches[0] ?? '';
        $this->assertNotSame('', $publicBallSource);
        $this->assertStringContainsString("'used_balls.approved_ball_id'", $publicBallSource);
        $this->assertStringContainsString('public_photo_url', $publicBallSource);
        $this->assertStringNotContainsString("'used_balls.serial_number'", $publicBallSource);
        $this->assertStringNotContainsString("'used_balls.inspection_number'", $publicBallSource);
        $this->assertStringNotContainsString("'used_balls.expires_at'", $publicBallSource);
        $this->assertIsString($detailView);
        $this->assertStringContainsString('大会登録ボール', $detailView);
        $this->assertStringContainsString('$portraitUrl', $detailView);
        $this->assertStringContainsString('大会登録済み', $detailView);
        $this->assertStringNotContainsString('serial_number', $detailView);
        $this->assertStringNotContainsString('inspection_number', $detailView);
        $this->assertStringNotContainsString('expires_at', $detailView);
        $this->assertStringNotContainsString('シリアルNo', $detailView);
        $this->assertStringNotContainsString('検量証番号', $detailView);
        $this->assertStringNotContainsString('有効期限', $detailView);
        $this->assertIsString($linkPartial);
        $this->assertStringContainsString("route('scores.entry_balls.show'", $linkPartial);

        foreach ([
            'result',
            'round_robin_result',
            'step_ladder_result',
            'single_elimination_result',
            'shootout_result',
        ] as $viewName) {
            $source = file_get_contents(
                resource_path('views/scores/' . $viewName . '.blade.php')
            );

            $this->assertIsString($source);
            $this->assertStringContainsString(
                "scores.partials.player_ball_link",
                $source,
                "速報ビュー {$viewName} に登録ボール導線がありません。"
            );
        }
    }
}

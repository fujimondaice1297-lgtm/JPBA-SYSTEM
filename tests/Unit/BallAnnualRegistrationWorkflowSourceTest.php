<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BallAnnualRegistrationWorkflowSourceTest extends TestCase
{
    public function test_player_level_annual_application_and_staff_bulk_approval_are_routed(): void
    {
        foreach ([
            'ball_annual_registrations.edit',
            'ball_annual_registrations.draft',
            'ball_annual_registrations.submit',
            'ball_annual_registrations.index',
            'ball_annual_registrations.approve',
            'ball_annual_registrations.return',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "年度ボール申請ルートがありません: {$routeName}");
        }

        $controller = file_get_contents(
            app_path('Http/Controllers/BallAnnualRegistrationController.php')
        );
        $this->assertIsString($controller);
        $this->assertStringContainsString('function saveDraft', $controller);
        $this->assertStringContainsString('function submit', $controller);
        $this->assertStringContainsString('function approve', $controller);
        $this->assertStringContainsString('function sendBack', $controller);
        $this->assertStringContainsString('STATUS_SUPERSEDED', $controller);
        $this->assertStringContainsString("'ball_count' => \$locked->usedBalls()->count()", $controller);
    }

    public function test_annual_approval_is_revisioned_and_keeps_an_audit_history(): void
    {
        $migration = file_get_contents(
            database_path('migrations/2026_08_10_000001_create_ball_annual_registration_workflow.php')
        );
        $model = file_get_contents(app_path('Models/BallAnnualRegistration.php'));
        $service = file_get_contents(app_path('Services/BallAnnualRegistrationService.php'));

        $this->assertIsString($migration);
        $this->assertStringContainsString("Schema::create('ball_annual_registrations'", $migration);
        $this->assertStringContainsString("Schema::create('ball_annual_registration_items'", $migration);
        $this->assertStringContainsString("Schema::create('ball_annual_registration_histories'", $migration);
        $this->assertStringContainsString("['pro_bowler_id', 'registration_year', 'revision']", $migration);

        $this->assertIsString($model);
        $this->assertStringContainsString("STATUS_SUBMITTED = 'submitted'", $model);
        $this->assertStringContainsString("STATUS_APPROVED = 'approved'", $model);
        $this->assertStringContainsString("STATUS_RETURNED = 'returned'", $model);

        $this->assertIsString($service);
        $this->assertStringContainsString('latestApproved', $service);
        $this->assertStringContainsString('approvedUsedBallIds', $service);
        $this->assertStringContainsString('registrationYearForTournament', $service);
        $this->assertStringContainsString('latestApprovedOrCarryover', $service);
        $this->assertStringContainsString('ensureInspectionCarryover', $service);
        $this->assertStringContainsString('inspection_carryover', $service);
    }

    public function test_tournament_registration_only_accepts_annual_approved_new_balls(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/TournamentEntryBallController.php')
        );
        $view = file_get_contents(
            resource_path('views/member/entry_balls_edit.blade.php')
        );

        $this->assertIsString($controller);
        $this->assertStringContainsString('approvedUsedBallIds', $controller);
        $this->assertStringContainsString('スタッフ承認を受けていないボールは追加できません', $controller);
        $this->assertStringContainsString('->merge($linkedIds)', $controller);

        $this->assertIsString($view);
        $this->assertStringContainsString('年度ボール申請', $view);
        $this->assertStringContainsString('年度承認が必要', $view);
        $this->assertStringContainsString('既存登録（年度承認前）', $view);
    }

    public function test_annual_screen_shows_tournament_usage_history_and_role_boundaries(): void
    {
        $view = file_get_contents(
            resource_path('views/ball_annual_registrations/edit.blade.php')
        );
        $dashboard = file_get_contents(
            resource_path('views/member/dashboard.blade.php')
        );

        $this->assertIsString($view);
        $this->assertStringContainsString('年度ボール管理の役割', $view);
        $this->assertStringContainsString('年度 大会使用履歴', $view);
        $this->assertStringContainsString('仮登録へ降格（期限切れ）', $view);
        $this->assertStringContainsString('翌年度へ自動で引き継がれます', $view);

        $this->assertIsString($dashboard);
        $this->assertStringContainsString('1. マイボール管理', $dashboard);
        $this->assertStringContainsString('2. 年度ボール管理', $dashboard);
        $this->assertStringContainsString('3. 大会使用ボール', $dashboard);
    }

    public function test_member_and_staff_navigation_contains_the_annual_workflow(): void
    {
        foreach ([
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/partials/side_menu.blade.php'),
            resource_path('views/member/dashboard.blade.php'),
            resource_path('views/used_balls/index.blade.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringContainsString('ball_annual_registrations.', $source, $path);
        }
    }
}

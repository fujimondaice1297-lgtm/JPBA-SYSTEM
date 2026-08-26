<?php

namespace Tests\Unit;

use Tests\TestCase;

class PlayerAccountBallLinkageSourceTest extends TestCase
{
    public function test_account_issuance_always_links_the_member_to_the_player_id(): void
    {
        $command = file_get_contents(app_path('Console/Commands/SeedUsersFromProBowlers.php'));
        $service = file_get_contents(app_path('Services/PlayerAccountService.php'));
        $registration = file_get_contents(app_path('Http/Controllers/Auth/RegisterController.php'));
        $forgotPassword = file_get_contents(app_path('Http/Controllers/Auth/ForgotPasswordController.php'));
        $middleware = file_get_contents(app_path('Http/Middleware/RoleMiddleware.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('{--bowler-id=*', $command);
        $this->assertStringContainsString('{--dry-run', $command);
        $this->assertStringContainsString('{--send-setup-link', $command);
        $this->assertStringContainsString('PlayerAccountService', $command);
        $this->assertStringNotContainsString("Hash::make('changeme')", $command);

        $this->assertIsString($service);
        $this->assertStringContainsString("'role' => 'member'", $service);
        $this->assertStringContainsString("in_array(\$account->role, ['admin', 'editor'], true)", $service);
        $this->assertStringContainsString("'pro_bowler_id' => \$bowler->id", $service);
        $this->assertStringContainsString("'license_no' => \$licenseNo", $service);
        $this->assertStringContainsString('Str::random(48)', $service);
        $this->assertStringContainsString('changeStatus(', $service);

        $this->assertIsString($registration);
        $this->assertStringContainsString("'role'", $registration);
        $this->assertStringContainsString("'pro_bowler_id'", $registration);
        $this->assertStringContainsString("'pro_bowler_license_no'", $registration);

        $this->assertIsString($forgotPassword);
        $this->assertStringContainsString('$user?->isAccountActive()', $forgotPassword);
        $this->assertStringNotContainsString('new User()', $forgotPassword);

        $this->assertIsString($middleware);
        $this->assertStringContainsString("\$actual === 'bowler'", $middleware);
        $this->assertStringContainsString("\$actual = 'member'", $middleware);
        $this->assertStringContainsString('isAccountActive()', $middleware);
    }

    public function test_registered_ball_and_tournament_flows_share_the_player_id_linkage_service(): void
    {
        $model = file_get_contents(app_path('Models/RegisteredBall.php'));
        $service = file_get_contents(app_path('Services/RegisteredBallLinkageService.php'));
        $registeredController = file_get_contents(app_path('Http/Controllers/RegisteredBallController.php'));
        $entryController = file_get_contents(app_path('Http/Controllers/TournamentEntryBallController.php'));

        $this->assertIsString($model);
        $this->assertStringContainsString("'pro_bowler_id'", $model);

        $this->assertIsString($service);
        $this->assertStringContainsString('function sync(', $service);
        $this->assertStringContainsString('function syncForBowler(', $service);
        $this->assertStringContainsString("['pro_bowler_id' => \$proBowler->id]", $service);

        $this->assertIsString($registeredController);
        $this->assertStringContainsString("'pro_bowler_id'", $registeredController);
        $this->assertStringContainsString('$this->linkageService->sync(', $registeredController);

        $this->assertIsString($entryController);
        $this->assertStringContainsString('$this->linkageService->syncForBowler(', $entryController);
        $this->assertStringContainsString('(int) $entry->pro_bowler_id', $entryController);
    }

    public function test_admin_profile_distinguishes_live_accounts_from_legacy_login_fields(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ProBowlerController.php'));
        $form = file_get_contents(resource_path('views/pro_bowlers/athlete_form.blade.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString("'userAccount'", $controller);

        $this->assertIsString($form);
        $this->assertStringContainsString('新システム 選手アカウント', $form);
        $this->assertStringContainsString('選手ID結線', $form);
        $this->assertStringContainsString('暗号化保存のため表示できません', $form);
        $this->assertStringContainsString('旧サイトログインID', $form);
        $this->assertStringContainsString('admin.player_accounts.show', $form);
        $this->assertStringNotContainsString('name="mypage_temp_password"', $form);
    }

    public function test_rollback_audit_command_covers_the_full_ball_registration_chain(): void
    {
        $command = file_get_contents(app_path('Console/Commands/AuditPlayerBallLinkage.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('jpba:audit-player-ball-linkage', $command);
        $this->assertStringContainsString('{--smoke', $command);
        $this->assertStringContainsString('DB::beginTransaction()', $command);
        $this->assertStringContainsString('DB::rollBack()', $command);
        $this->assertStringContainsString('approvedUsedBallIds', $command);
        $this->assertStringContainsString('$entry->balls()->attach', $command);
    }
}

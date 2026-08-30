<?php

namespace Tests\Unit;

use Tests\TestCase;

class ScheduledOperationsSafetySourceTest extends TestCase
{
    public function test_current_scheduler_registers_required_operations_without_destructive_ball_cleanup(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));
        $legacyKernel = file_get_contents(app_path('Console/Kernel.php'));
        $legacyNoCertificate = file_get_contents(app_path('Console/Commands/DeleteBallsWithoutCertificate.php'));
        $legacyExpired = file_get_contents(app_path('Console/Commands/DeleteExpiredUsedBalls.php'));

        $this->assertStringContainsString("Schedule::command('tournament:send-draw-reminders')", $schedule);
        $this->assertStringContainsString("Schedule::command('tournament:auto-draw-pending')", $schedule);
        $this->assertStringContainsString("Schedule::command('balls:audit-retention')", $schedule);
        $this->assertStringContainsString("Schedule::command('balls:sync-catalog --manufacturer=all --force')", $schedule);
        $this->assertStringContainsString("Schedule::command('balls:sync-usbc-approved --force')", $schedule);
        $this->assertStringContainsString("Schedule::command('balls:carry-over-annual-registrations --force')", $schedule);
        $this->assertStringContainsString('scheduled-ball-annual-carryover.log', $schedule);
        $this->assertLessThan(
            strpos($schedule, "Schedule::command('balls:sync-usbc-approved --force')"),
            strpos($schedule, "Schedule::command('balls:sync-catalog --manufacturer=all --force')")
        );
        $this->assertStringContainsString('scheduled-ball-catalog-sync.log', $schedule);
        $this->assertStringContainsString('scheduled-usbc-approved-ball-sync.log', $schedule);
        $this->assertGreaterThanOrEqual(3, substr_count($schedule, 'withoutOverlapping(120)'));
        $this->assertGreaterThanOrEqual(3, substr_count($schedule, 'appendOutputTo'));
        $this->assertStringNotContainsString("Schedule::command('usedballs:delete-expired')", $schedule);
        $this->assertStringNotContainsString("Schedule::command('balls:delete-without-certificate')", $schedule);
        $this->assertStringNotContainsString('->delete()', $legacyKernel);
        $this->assertStringNotContainsString('->delete()', $legacyNoCertificate);
        $this->assertStringNotContainsString('->delete()', $legacyExpired);
        $this->assertStringContainsString('削除件数: 0件', $legacyNoCertificate);
        $this->assertStringContainsString('削除件数: 0件', $legacyExpired);
    }
}

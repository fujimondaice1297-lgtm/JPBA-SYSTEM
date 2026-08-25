<?php

namespace Tests\Unit;

use Tests\TestCase;

class JpbaBackupSafetySourceTest extends TestCase
{
    public function test_backup_is_encrypted_versioned_and_restores_only_to_isolated_targets(): void
    {
        $service = file_get_contents(app_path('Services/JpbaBackupService.php'));
        $schedule = file_get_contents(base_path('routes/console.php'));
        $gitignore = file_get_contents(base_path('.gitignore'));

        $this->assertStringContainsString('ZipArchive::EM_AES_256', $service);
        $this->assertStringContainsString("'key_embedded' => false", $service);
        $this->assertStringContainsString('retentionGenerations()', $service);
        $this->assertStringContainsString('assertRestoreDatabaseNameIsSafe', $service);
        $this->assertStringContainsString('removeDirectorySafely', $service);
        $this->assertStringContainsString("'jpba:backup --isolated'", $schedule);
        $this->assertStringContainsString('withoutOverlapping(720)', $schedule);
        $this->assertStringContainsString('/storage/*.key', $gitignore);
        $this->assertStringNotContainsString("'password' =>", $service);
        $this->assertStringNotContainsString('APP_KEY', $service);
    }
}

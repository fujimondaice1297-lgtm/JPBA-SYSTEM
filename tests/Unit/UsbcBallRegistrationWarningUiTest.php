<?php

namespace Tests\Unit;

use Tests\TestCase;

class UsbcBallRegistrationWarningUiTest extends TestCase
{
    public function test_every_ball_registration_form_contains_the_unlisted_warning(): void
    {
        $paths = [
            resource_path('views/registered_balls/create.blade.php'),
            resource_path('views/registered_balls/edit.blade.php'),
            resource_path('views/used_balls/create.blade.php'),
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringContainsString(
                'data-usbc-status',
                $source,
                $path
            );
            $this->assertStringContainsString(
                'アブプールリストに記載のないボールです',
                $source,
                $path
            );
        }
    }
}

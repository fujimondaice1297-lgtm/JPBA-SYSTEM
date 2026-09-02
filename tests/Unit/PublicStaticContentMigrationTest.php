<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicStaticContentMigrationTest extends TestCase
{
    public function test_new_public_content_uses_internal_routes_and_archived_assets(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_02_000001_migrate_remaining_static_public_pages.php'));

        foreach ([
            'support',
            'support-corporate',
            'support-individual',
            'instructor-flow',
            'instructor-plan',
            'instructor-school-about',
            'instructor-signage',
        ] as $slug) {
            $this->assertStringContainsString("'slug' => '{$slug}'", $migration);
        }

        foreach ([
            '/documents/jpba/individual-support-application.pdf',
            '/documents/jpba/individual-support-application.docx',
            '/documents/jpba/instructor-license-flow.pdf',
            '/documents/jpba/instructor-school-application.pdf',
            '/documents/jpba/instructor-school-tools-order.pdf',
            '/images/jpba/instructor/Sticker_A.jpg',
            '/images/jpba/instructor/Sticker_BC.jpg',
            '/images/jpba/instructor/Sticker_N.jpg',
            '/images/jpba/instructor/Wappen_A.jpg',
            '/images/jpba/instructor/Wappen_BC.jpg',
            '/images/jpba/instructor/Wappen_02.jpg',
            '/images/jpba/instructor/Wappen_03.jpg',
        ] as $path) {
            $this->assertStringContainsString($path, $migration);
            $this->assertFileExists(public_path(ltrim($path, '/')));
        }

        $this->assertTrue(Route::has('public.support'));
        $this->assertStringNotContainsString('href="https://www.jpba1.jp/', $migration);
        $this->assertStringNotContainsString('href="https://www.jpba.or.jp/', $migration);
    }
}

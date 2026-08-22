<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicLegacyDependencyRemovalTest extends TestCase
{
    public function test_public_configuration_has_no_legacy_jpba_domain_dependency(): void
    {
        $serialized = json_encode(config('jpba_public'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertIsString($serialized);
        $this->assertStringNotContainsString('jpba.or.jp', $serialized);
        $this->assertStringNotContainsString('jpba1.jp', $serialized);
    }

    public function test_public_pdf_links_resolve_to_archived_pdf_files(): void
    {
        $pdfPaths = [];
        $collectPdfPaths = function (array $items) use (&$collectPdfPaths, &$pdfPaths): void {
            foreach ($items as $key => $value) {
                if (is_array($value)) {
                    $collectPdfPaths($value);
                    continue;
                }

                if ($key === 'url' && is_string($value) && str_ends_with(strtolower($value), '.pdf')) {
                    $pdfPaths[] = $value;
                }
            }
        };

        $collectPdfPaths(config('jpba_public'));

        $this->assertCount(10, $pdfPaths);

        foreach ($pdfPaths as $path) {
            $this->assertStringStartsWith('/documents/jpba/', $path);

            $absolutePath = public_path(ltrim($path, '/'));
            $this->assertFileExists($absolutePath);
            $this->assertGreaterThan(50_000, filesize($absolutePath));

            $handle = fopen($absolutePath, 'rb');
            $this->assertIsResource($handle);
            $this->assertSame('%PDF-', fread($handle, 5), "PDF署名が不正です: {$path}");
            fclose($handle);
        }
    }

    public function test_replaced_topic_links_resolve_inside_the_new_site(): void
    {
        $links = config('jpba_public.topics.legacy_links', []);

        $this->assertCount(4, $links);
        foreach ($links as $link) {
            $this->assertArrayHasKey('route', $link);
            $this->assertArrayNotHasKey('url', $link);
            $this->assertTrue(Route::has($link['route']), "未定義ルート: {$link['route']}");
        }

        $view = file_get_contents(resource_path('views/public/topics.blade.php'));
        $this->assertStringContainsString('$urlFor($link)', $view);
        $this->assertStringContainsString('関連ページ', $view);
        $this->assertStringNotContainsString('現行サイト導線', $view);
    }

    public function test_managed_media_page_seed_and_upgrade_use_archived_documents(): void
    {
        $initialMigration = file_get_contents(database_path('migrations/2026_08_13_000001_create_managed_public_pages.php'));
        $upgradeMigration = file_get_contents(database_path('migrations/2026_08_22_000001_internalize_media_public_document_links.php'));

        foreach ([
            '/documents/jpba/media-compliance-rules-2023-05-08.pdf',
            '/documents/jpba/media-application-2024.pdf',
        ] as $path) {
            $this->assertStringContainsString($path, $initialMigration);
            $this->assertStringContainsString($path, $upgradeMigration);
        }

        $this->assertStringNotContainsString('href="https://www.jpba1.jp/media/PDF/', $initialMigration);
        $this->assertStringContainsString("where('slug', 'media')", $upgradeMigration);
        $this->assertStringContainsString('source_url は移行元を示す非公開監査情報', $upgradeMigration);
    }
}

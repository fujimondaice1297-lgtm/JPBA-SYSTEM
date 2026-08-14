<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class ReleaseReadinessSourceAuditTest extends TestCase
{
    private const AUDITED_MISSING_LITERAL_ROUTES = [
        'editor.dashboard',
        'password.confirm',
        'tournaments.results.store',
        'verification.send',
        'web.tournament_entries.balls.store',
    ];

    public function test_blade_named_route_gaps_match_the_audited_baseline(): void
    {
        $missing = [];
        $referenceCount = 0;

        foreach ($this->bladeSources() as $path => $source) {
            preg_match_all(
                '/\broute\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/',
                $source,
                $matches
            );

            foreach (array_unique($matches[1] ?? []) as $routeName) {
                $referenceCount++;
                if (!Route::has($routeName)) {
                    $missing[$routeName][] = $path;
                }
            }
        }

        $this->assertGreaterThan(100, $referenceCount);
        $actual = array_keys($missing);
        sort($actual);

        $expected = self::AUDITED_MISSING_LITERAL_ROUTES;
        sort($expected);

        $this->assertSame(
            $expected,
            $actual,
            '監査済み基準と異なる未定義の名前付きルートがあります: ' . json_encode($missing, JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_every_literal_blade_parent_and_include_exists(): void
    {
        $missing = [];
        $referenceCount = 0;

        foreach ($this->bladeSources() as $path => $source) {
            preg_match_all(
                '/@(?:extends|include|includeIf|includeWhen)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/',
                $source,
                $matches
            );

            foreach (array_unique($matches[1] ?? []) as $viewName) {
                $referenceCount++;
                $viewPath = resource_path(
                    'views/' . str_replace('.', '/', $viewName) . '.blade.php'
                );

                if (!is_file($viewPath)) {
                    $missing[$viewName][] = $path;
                }
            }
        }

        $this->assertGreaterThan(50, $referenceCount);
        $this->assertSame(
            [],
            $missing,
            '参照先がないBladeがあります: ' . json_encode($missing, JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_public_tournament_ball_view_does_not_reference_private_certificate_fields(): void
    {
        $source = file_get_contents(resource_path('views/scores/entry_balls_show.blade.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('serial_number', $source);
        $this->assertStringNotContainsString('inspection_number', $source);
        $this->assertStringNotContainsString('expires_at', $source);
    }

    /**
     * @return array<string, string>
     */
    private function bladeSources(): array
    {
        $sources = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'))
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (!is_string($source)) {
                continue;
            }

            $sources[str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname())] = $source;
        }

        return $sources;
    }
}

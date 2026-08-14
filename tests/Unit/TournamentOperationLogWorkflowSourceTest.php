<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TournamentOperationLogWorkflowSourceTest extends TestCase
{
    public function test_operation_log_uses_supported_blade_syntax_and_defined_routes(): void
    {
        $source = file_get_contents(resource_path('views/tournament_entries/operation_logs.blade.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('@php(', $source);
        $this->assertStringNotContainsString('tournaments.results.apply_awards_points', $source);
        $this->assertStringNotContainsString('tournaments.results.sync', $source);

        foreach ([
            'tournaments.operation_logs.index',
            'tournaments.entries.index',
            'tournaments.draws.index',
            'tournaments.result_snapshots.index',
            'tournaments.result_publications.index',
            'tournaments.results.pdf',
            'tournaments.seed_players.index',
            'tournaments.seed_players.pdf',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "大会運用ログのルートが未定義です: {$routeName}");
        }
    }

    public function test_official_result_publication_is_the_single_write_path_for_awards_and_titles(): void
    {
        $publicationService = file_get_contents(app_path('Services/TournamentResultPublicationService.php'));
        $publicationController = file_get_contents(app_path('Http/Controllers/TournamentResultPublicationController.php'));

        $this->assertStringContainsString("TournamentResult::query()->where('tournament_id'", $publicationService);
        $this->assertStringContainsString('$this->titleSyncService->sync($lockedTournament)', $publicationService);
        $this->assertStringContainsString("'points'", $publicationService);
        $this->assertStringContainsString("'prize_money'", $publicationService);
        $this->assertStringContainsString("auth()->user()?->isAdmin()", $publicationController);
        $this->assertStringContainsString('expected_checksum', $publicationController);
        $this->assertStringContainsString('confirm_publish', $publicationController);
    }
}

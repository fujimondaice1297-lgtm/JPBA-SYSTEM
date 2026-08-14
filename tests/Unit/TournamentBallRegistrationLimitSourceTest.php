<?php

namespace Tests\Unit;

use Tests\TestCase;

class TournamentBallRegistrationLimitSourceTest extends TestCase
{
    public function test_tournament_forms_and_persistence_support_a_per_tournament_limit(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/TournamentController.php')
        );
        $model = file_get_contents(app_path('Models/Tournament.php'));
        $createView = file_get_contents(
            resource_path('views/tournaments/create.blade.php')
        );
        $editView = file_get_contents(
            resource_path('views/tournaments/edit.blade.php')
        );

        $this->assertIsString($controller);
        $this->assertStringContainsString(
            "'ball_registration_limit' => 'required|integer|min:1|max:100'",
            $controller
        );
        $this->assertStringContainsString(
            "\$validated['ball_registration_limit'] = (int)",
            $controller
        );
        $this->assertStringContainsString("'ball_registration_limit',", $controller);
        $this->assertStringContainsString("orWhere('id', \$currentTournament->venue_id)", $controller);
        $this->assertStringContainsString("orWhere('id', \$currentTournament->tournament_result_format_version_id)", $controller);
        $this->assertStringNotContainsString('orWhereKey(', $controller);

        $this->assertIsString($model);
        $this->assertStringContainsString("'ball_registration_limit',", $model);
        $this->assertStringContainsString("'ball_registration_limit' => 'integer'", $model);

        foreach ([$createView, $editView] as $view) {
            $this->assertIsString($view);
            $this->assertStringContainsString('name="ball_registration_limit"', $view);
            $this->assertStringContainsString('大会使用ボール登録上限', $view);
            $this->assertStringContainsString('個／選手', $view);
        }
    }

    public function test_tournament_entry_ball_limit_comes_from_the_tournament_setting(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/TournamentEntryBallController.php')
        );

        $this->assertIsString($controller);
        $this->assertStringNotContainsString('MAX_BALLS_PER_ENTRY', $controller);
        $this->assertStringContainsString('resolveBallRegistrationLimit', $controller);
        $this->assertStringContainsString(
            '$entry->tournament?->ball_registration_limit',
            $controller
        );
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($controller, '$this->resolveBallRegistrationLimit($entry)')
        );
        $this->assertStringContainsString(
            "'used_ball_ids' => '1大会で登録できるボールは最大'",
            $controller
        );
    }

    public function test_migration_preserves_existing_tournaments_with_a_default_of_twelve(): void
    {
        $migration = file_get_contents(
            database_path('migrations/2026_08_11_000001_add_ball_registration_limit_to_tournaments.php')
        );
        $templateService = file_get_contents(
            app_path('Services/TournamentTemplateService.php')
        );

        $this->assertIsString($migration);
        $this->assertStringContainsString(
            "unsignedSmallInteger('ball_registration_limit')",
            $migration
        );
        $this->assertStringContainsString('->default(12)', $migration);
        $this->assertStringContainsString("dropColumn('ball_registration_limit')", $migration);

        $this->assertIsString($templateService);
        $this->assertStringContainsString("'ball_registration_limit',", $templateService);
    }
}

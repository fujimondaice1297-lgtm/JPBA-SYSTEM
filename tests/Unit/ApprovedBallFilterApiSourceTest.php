<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApprovedBallFilterApiSourceTest extends TestCase
{
    public function test_filter_route_and_release_date_query_match_the_current_schema(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Api/ApprovedBallController.php'));
        $model = file_get_contents(app_path('Models/ApprovedBall.php'));

        $this->assertTrue(Route::has('api.approved-balls.filter') || collect(Route::getRoutes())->contains(
            fn ($route) => $route->uri() === 'api/approved-balls/filter'
        ));

        $this->assertIsString($controller);
        $this->assertStringContainsString("whereYear('release_date', \$releaseYear)", $controller);
        $this->assertStringContainsString("'release_date' => \$ball->release_date?->format('Y-m-d')", $controller);
        $this->assertStringContainsString("'release_year' => \$ball->release_year", $controller);
        $this->assertStringContainsString("'name' => ['nullable', 'string', 'max:255']", $controller);
        $this->assertStringNotContainsString("where('release_year'", $controller);
        $this->assertStringNotContainsString("select('id', 'name', 'manufacturer', 'release_year')", $controller);

        $this->assertIsString($model);
        $this->assertStringContainsString('function getReleaseYearAttribute(): ?int', $model);
        $this->assertStringContainsString("'release_year'", $model);
    }
}

<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ManagementNavigation;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ManagementNavigationSourceTest extends TestCase
{
    public function test_every_management_navigation_route_exists(): void
    {
        $admin = new User(['role' => 'admin']);
        $navigation = app(ManagementNavigation::class);

        $items = collect($navigation->groups($admin))
            ->flatMap(fn (array $group) => $group['items'])
            ->merge($navigation->quickActions($admin));

        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertTrue(
                Route::has($item['route']),
                "管理導線のルートが未定義です: {$item['route']}"
            );
        }
    }

    public function test_editor_does_not_receive_admin_only_links(): void
    {
        $editor = new User(['role' => 'editor']);
        $navigation = app(ManagementNavigation::class);

        $routeNames = collect($navigation->groups($editor))
            ->flatMap(fn (array $group) => $group['items'])
            ->pluck('route');

        $this->assertFalse($routeNames->contains('admin.informations.index'));
        $this->assertFalse($routeNames->contains('admin.compliance.index'));
        $this->assertTrue($routeNames->contains('tournaments.index'));
        $this->assertTrue($routeNames->contains('scores.input'));
    }

    public function test_management_views_share_the_work_category_navigation(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $sideMenu = file_get_contents(resource_path('views/partials/side_menu.blade.php'));
        $dashboard = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString("route('management.home')", $layout);
        $this->assertIsString($sideMenu);
        $this->assertStringContainsString('ManagementNavigation', $sideMenu);
        $this->assertStringContainsString('管理ホーム', $sideMenu);
        $this->assertIsString($dashboard);
        $this->assertStringContainsString('管理者ワークスペース', $dashboard);
        $this->assertStringContainsString('大会ごとの作業', $dashboard);
        $this->assertStringContainsString('management-workflow', $dashboard);
        $this->assertStringContainsString('quick-tone-', $dashboard);
        $this->assertStringContainsString('tone-blue .management-group-link::after', $dashboard);
        $this->assertStringContainsString('btn btn-warning btn-sm', $dashboard);
        $this->assertStringContainsString('btn btn-success btn-sm', $dashboard);
        $this->assertStringContainsString('btn btn-primary btn-sm', $dashboard);
        $this->assertIsString($routes);
        $this->assertStringContainsString("name('management.home')", $routes);
    }
}

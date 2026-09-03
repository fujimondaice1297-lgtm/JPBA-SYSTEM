<?php

namespace App\Providers;

use App\Models\Tournament;
use App\Observers\TournamentObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    // ★ Router を DI する。これで $router が確実に使える
    public function boot(Router $router): void
    {
        Paginator::useBootstrapFive();

        Tournament::observe(TournamentObserver::class);

        // Kernel が読まれてなくても Router 側に alias を生やす（今回の主目的）
        $router->aliasMiddleware('role', \App\Http\Middleware\RoleMiddleware::class);
    }
}

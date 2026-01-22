<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use App\Models\Tournament;
use App\Observers\TournamentObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    // ★ Router を DI する。これで $router が確実に使える
    public function boot(Router $router): void
    {
        Tournament::observe(TournamentObserver::class);

        // Kernel が読まれてなくても Router 側に alias を生やす（今回の主目的）
        $router->aliasMiddleware('role', \App\Http\Middleware\RoleMiddleware::class);
    }
}



<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        // 管理者（role=admin or is_admin=1）
        Gate::define('admin', fn(User $user) => $user->isAdmin());

        // 会員（プロボウラー紐づきがある人を会員とみなす）
        Gate::define('member', fn(User $user) =>
            (bool) ($user->bowler // users.pro_bowler_id → pro_bowlers.id
                 ?? $user->pro_bowler_license_no // レガシー互換
            )
        );
    }
}

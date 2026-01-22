<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * 全体に適用されるHTTPミドルウェア
     */
    protected $middleware = [
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * ルートグループに適用されるミドルウェア
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * 個別ルートに適用可能なミドルウェアのエイリアス（Laravel 12）
     */
    protected $middlewareAliases = [
        'auth'      => \App\Http\Middleware\Authenticate::class,
        'guest'     => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'verified'  => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'throttle'  => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'signed'    => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'substituteBindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,

        // ★ここが今回の主役
        'role'      => \App\Http\Middleware\RoleMiddleware::class,
        // 'can'   => \Illuminate\Auth\Middleware\Authorize::class, // Gate/Policy使うなら有効に
    ];
}

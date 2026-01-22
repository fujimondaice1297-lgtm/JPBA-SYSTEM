<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();
        if (!$user) abort(401);

        // "admin,editor" / "admin|editor" どちらでもOK
        if (count($roles) === 1) {
            $roles = str_contains($roles[0], ',')
                ? explode(',', $roles[0])
                : (str_contains($roles[0], '|') ? explode('|', $roles[0]) : $roles);
        }
        $roles = array_map('trim', $roles);

        // ★ 旧データ救済：null は member 扱い
        $actual = $user->role ?? 'member';

        // ★一時ログ
    Log::debug('role-mw', ['need'=>$roles, 'actual'=>$actual, 'uid'=>$user->id]);

        if (!in_array($actual, $roles, true)) {
            abort(403);
        }
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $user->isAccountActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'このアカウントは現在利用できません。事務局へお問い合わせください。',
            ]);
        }

        // "admin,editor" / "admin|editor" どちらでもOK
        if (count($roles) === 1) {
            $roles = str_contains($roles[0], ',')
                ? explode(',', $roles[0])
                : (str_contains($roles[0], '|') ? explode('|', $roles[0]) : $roles);
        }
        $roles = array_map('trim', $roles);

        // 旧データ救済：旧既定値 bowler / null は member 扱い
        $actual = $user->role ?? 'member';
        if ($actual === 'bowler') {
            $actual = 'member';
        }

        if (! in_array($actual, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}

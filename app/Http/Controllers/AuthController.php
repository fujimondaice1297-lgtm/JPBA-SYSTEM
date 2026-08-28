<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        // 既にログイン済みなら、権限に合った開始画面へ
        if (Auth::check()) {
            return redirect()->route($this->homeRoute(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],  // email または ライセンスNo
            'password' => ['required', 'string'],
        ]);

        $login = trim($request->string('login'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        // 入力が email か license かを雑に判定
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        // 大文字小文字の揺れ対策（メールは小文字、ライセンスは大文字）
        $emailOrLicense = $isEmail ? strtolower($login) : strtoupper($login);

        $user = $this->findUserForLogin($emailOrLicense, $isEmail);

        if (! $user || ! Hash::check($password, $user->password)) {
            return back()->withErrors([
                'login' => 'メールアドレス / ライセンスNo またはパスワードが違います。',
            ])->withInput(['login' => $request->input('login')]);
        }

        if (! $user->isAccountActive()) {
            return back()->withErrors([
                'login' => 'このアカウントは現在利用できません。事務局へお問い合わせください。',
            ])->withInput(['login' => $request->input('login')]);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        // 以前開こうとした管理URLをセッションに保持していても、
        // 正会員は必ずマイページ、管理者・編集者は管理ホームから開始する。
        return redirect()->route($this->homeRoute($user));
    }

    private function homeRoute(User $user): string
    {
        return $user->isAdmin() || $user->isEditor()
            ? 'management.home'
            : 'member.dashboard';
    }

    private function findUserForLogin(string $login, bool $isEmail): ?User
    {
        if ($isEmail) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($login, 'UTF-8')])
                ->first();
        }

        $candidate = mb_strtoupper(trim($login), 'UTF-8');

        $user = User::query()
            ->where('pro_bowler_license_no', $candidate)
            ->orWhere('license_no', $candidate)
            ->first();

        if ($user) {
            return $user;
        }

        $bowlers = ProBowler::query()
            ->whereRaw('UPPER(login_id) = ?', [$candidate])
            ->limit(2)
            ->get(['id', 'license_no']);

        if ($bowlers->count() !== 1) {
            return null;
        }

        $bowler = $bowlers->first();

        return User::query()
            ->where('pro_bowler_id', $bowler->id)
            ->orWhere('pro_bowler_license_no', $bowler->license_no)
            ->orWhere('license_no', $bowler->license_no)
            ->first();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

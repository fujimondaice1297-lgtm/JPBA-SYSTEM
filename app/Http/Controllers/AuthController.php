<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        // 既にログイン済みならマイページへ
        if (Auth::check()) {
            return redirect()->route('member.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login'    => ['required', 'string'],  // email または ライセンスNo
            'password' => ['required', 'string'],
        ]);

        $login    = trim($request->string('login'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        // 入力が email か license かを雑に判定
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        // 大文字小文字の揺れ対策（メールは小文字、ライセンスは大文字）
        $emailOrLicense = $isEmail ? strtolower($login) : strtoupper($login);

        // users.email もしくは users.pro_bowler_license_no で取得
        $user = User::query()
            ->when($isEmail,
                fn ($q) => $q->where('email', $emailOrLicense),
                fn ($q) => $q->where('pro_bowler_license_no', $emailOrLicense)
            )
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return back()->withErrors([
                'login' => 'メールアドレス / ライセンスNo またはパスワードが違います。',
            ])->withInput(['login' => $request->input('login')]);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        // ここもマイページに統一
        return redirect()->intended(route('member.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}

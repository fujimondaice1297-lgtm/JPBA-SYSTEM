<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ProBowler;
use App\Models\User;
use App\Models\UserAccountStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $request->validate([
            'license_no' => ['required', 'string'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // ライセンス番号 + メールが一致するプロボウラーを確認
        $proBowler = ProBowler::where('license_no', $request->license_no)
            ->where('email', $request->email)
            ->first();

        if (! $proBowler) {
            return back()
                ->withErrors(['license_no' => 'ライセンス番号とメールアドレスが一致しません。'])
                ->withInput();
        }

        // 表示名はプロファイルから拝借
        $nameFromProfile = $proBowler->name
            ?? $proBowler->name_kanji
            ?? $proBowler->name_kana
            ?? '会員';

        // ★ ここが本題：既定ロールを member、ID/ライセンス両方で紐付け
        $user = User::create([
            'name' => $nameFromProfile,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'member',                // 既定は会員
            'pro_bowler_id' => $proBowler->id,          // IDでの紐付け
            'pro_bowler_license_no' => $proBowler->license_no,  // 旧実装の互換
            'license_no' => $proBowler->license_no,
            'account_status' => User::STATUS_ACTIVE,
            'password_set_at' => now(),
        ]);
        UserAccountStatusLog::query()->create([
            'user_id' => $user->id,
            'from_status' => null,
            'to_status' => User::STATUS_ACTIVE,
            'reason' => '選手本人登録',
            'changed_by' => $user->id,
        ]);
        $proBowler->forceFill(['password_change_status' => 0])->saveQuietly();

        Auth::login($user);
        session()->flash('status', "{$nameFromProfile} さんの新規登録が完了しました。");

        return redirect()->route('member.dashboard');
    }
}

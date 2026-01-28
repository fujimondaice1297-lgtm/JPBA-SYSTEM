<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ProBowler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordSetupController extends Controller
{
    /**
     * 初期パスワード設定（=パスワードリセット）メール送信フォーム
     */
    public function requestForm()
    {
        return view('auth.password_setup_request');
    }

    /**
     * メール照合 → users作成/紐付け → リセットリンク送信
     * ※存在しないメールでも同じレスポンスにする（列挙対策）
     */
    public function sendLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower(trim($data['email']));

        // pro_bowlers 側にメールが登録されている前提（なければ移行不可）
        $proBowler = ProBowler::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($proBowler) {
            // users を作成/更新（passwordはランダムで埋める。実際の設定はリセットで行う）
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $proBowler->name_kanji ?? $proBowler->name_kana ?? $proBowler->license_no ?? 'member',
                    'role' => 'member',
                    'is_admin' => false,
                    'pro_bowler_id' => $proBowler->id,
                    'pro_bowler_license_no' => $proBowler->license_no,
                    'license_no' => $proBowler->license_no,
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            // リセットリンク送信（メール設定が必要）
            Password::sendResetLink(['email' => $email]);
        }

        // 列挙対策：見つからなくても同じメッセージ
        return back()->with('status', 'メールアドレスが登録されている場合、初期パスワード設定用のメールを送信しました。');
    }

    /**
     * リセットフォーム表示
     * ※ Password::sendResetLink の通知がこの route 名を使うため、name は password.reset が必須
     */
    public function resetForm(Request $request, string $token)
    {
        $email = $request->query('email');

        return view('auth.password_setup_reset', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * パスワード更新（トークン検証込み）
     */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'パスワードを設定しました。ログインしてください。');
        }

        return back()->withErrors(['email' => 'パスワード設定に失敗しました。リンクが期限切れの可能性があります。'])->withInput();
    }
}

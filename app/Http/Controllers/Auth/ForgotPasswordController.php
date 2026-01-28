<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ProBowler;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * 初期パスワード設定（リセットリンク送信）
     * - 入力 email を users.email と照合
     * - users に無ければ pro_bowlers.email と照合して 1件一致なら users を自動作成
     * - その上で Password::sendResetLink() を 1回だけ実行（ログも正しい status を出す）
     *
     * セキュリティ：存在しない場合でも同じメッセージを返す（列挙対策）
     */
    public function sendResetLinkEmail(Request $request)
    {
        // 先にバリデーション（これが正しい順番）
        $request->validate(['email' => 'required|email']);

        $email = mb_strtolower(trim($request->input('email')));

        \Log::debug('ForgotPasswordController@sendResetLinkEmail hit', [
            'email' => $email,
        ]);

        $created = false;

        // 既に users に存在するなら、そのまま通常のリセットを許可
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // users に居ない場合だけ、pro_bowlers 側から救済（自動作成）
        if (!$user) {
            // 同一メールが複数 pro_bowlers に紐付く場合は危険なので自動紐付けしない
            $candidates = ProBowler::query()
                ->whereNotNull('email')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->limit(2)
                ->get();

            if ($candidates->count() === 1) {
                $pb = $candidates->first();

                $user = new User();
                $user->email = $email;

                // 初期はランダム（本人がリセットで設定する前提）
                $user->password = Hash::make(Str::random(32));

                // 既存カラムに合わせて紐付け（存在しないカラムがあればその行だけ削除でOK）
                $user->name = $pb->name_kanji ?? $pb->name_kana ?? ($pb->license_no ?? 'member');
                $user->role = 'member';
                $user->is_admin = false;
                $user->pro_bowler_id = $pb->id;
                $user->pro_bowler_license_no = $pb->license_no ?? null;
                $user->license_no = $pb->license_no ?? null;

                $user->save();
                $created = true;
            }
        }

        // users が存在する時だけ送る（存在しない場合も同じメッセージで返す：列挙対策）
        if ($user) {
            $status = Password::sendResetLink(['email' => $user->email]);

            \Log::debug('ForgotPasswordController@sendResetLinkEmail status', [
                'email'    => $email,
                'status'   => $status,
                'created'  => $created,
                'user_id'  => $user->id,
            ]);
        } else {
            \Log::debug('ForgotPasswordController@sendResetLinkEmail status', [
                'email'   => $email,
                'status'  => 'skipped(no-user)',
                'created' => $created,
            ]);
        }

        // 列挙対策：常に同じ文言
        return back()->with('status', 'メールアドレスが登録されている場合、初期パスワード設定用のメールを送信しました。');
    }

    /**
     * リセットフォーム表示
     */
    public function showResetForm(Request $request, $token)
    {
        $email = $request->query('email');

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * リセット実行（新パスワード設定）
     * 成功時：pro_bowlers.password_change_status を 0（更新済）にする（存在する場合のみ）
     */
    public function reset(Request $request)
    {
        \Log::debug('ForgotPasswordController@reset hit', [
            'email' => $request->input('email'),
            'has_token' => (bool)$request->input('token'),
        ]);

        // ※スペース事故を防ぐため、パスワードは「空白なし」を強制（超おすすめ）
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^\S+$/u'],
        ]);

        $email = mb_strtolower(trim($request->input('email')));

        $status = Password::reset(
            [
                'email' => $email,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();

                \Log::debug('ForgotPasswordController@reset updated user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                // pro_bowlers 側に「更新済」を記録（カラムがあれば）
                if (!empty($user->pro_bowler_id)) {
                    try {
                        ProBowler::where('id', $user->pro_bowler_id)
                            ->update(['password_change_status' => 0]);
                    } catch (\Throwable $e) {
                        // カラムが無い/更新できない環境でもリセット自体は成功させる
                    }
                }
            }
        );

        \Log::debug('ForgotPasswordController@reset status', [
            'email' => $email,
            'status' => $status,
        ]);

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}

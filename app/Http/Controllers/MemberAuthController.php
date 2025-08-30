<?

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class MemberAuthController extends Controller
{
    public function showLogin() {
        if (Auth::check()) return redirect()->route('member.dashboard');
        return view('member_auth.login');
    }

    public function login(Request $request) {
        $data = $request->validate([
            'login'    => ['required','string'], // メール or ライセンスNo.
            'password' => ['required','string'],
            'remember' => ['sometimes','boolean'],
        ]);
        $login = $data['login'];

        // 1) license_no 優先
        $user = User::where('license_no', $login)->first();
        // 2) email fallback
        if (!$user && filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $login)->first();
        }
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['login' => '認証に失敗しました'])->withInput();
        }
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('member.dashboard'));
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('member.login');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ProBowler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            'email'      => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $proBowler = \App\Models\ProBowler::where('license_no', $request->license_no)
            ->where('email', $request->email)
            ->first();

        if (!$proBowler) {
            return back()->withErrors(['license_no' => 'ライセンス番号とメールアドレスが一致しません。'])->withInput();
        }

        // ★ ProBowlerの名前をUser.nameへ
        $nameFromProfile = $proBowler->name
            ?? $proBowler->name_kanji
            ?? $proBowler->name_kana
            ?? '会員';

        $user = \App\Models\User::create([
            'name'                   => $nameFromProfile,                 // ← これを追加
            'email'                  => $request->email,
            'password'               => \Illuminate\Support\Facades\Hash::make($request->password),
            'pro_bowler_license_no'  => $proBowler->license_no,
        ]);

        \Illuminate\Support\Facades\Auth::login($user);
        session()->flash('status', "{$nameFromProfile} さんの新規登録が完了しました。");

        return redirect()->route('member.dashboard');
    }
}

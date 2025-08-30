<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function showForm()
    {
        return view('auth.change-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password'     => ['required', 'confirmed', 'min:8'],
        ]);

        $user = Auth::user();
        $user->password = Hash::make($request->new_password);
        $user->save();

        // ★ 表示名の取り方をちゃんとやる（優先: 漢字 → name → email）
        $bowler = $user->proBowler()->first();
        $displayName =
            $bowler->name_kanji
            ?? $bowler->name
            ?? $user->name
            ?? explode('@', $user->email)[0]  // 最後の砦
        ;

        session()->flash('status', "{$displayName} さんのパスワード変更が完了しました。");
        return redirect()->route('member.dashboard');
    }
}

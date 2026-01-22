<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MemberDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // プロボウラー（ID優先、なければライセンス紐付け）
        $bowler = $user->proBowler ?? $user->proBowlerByLicense;

        // マイページに表示するグループ（期限切れは除外）
        $mypageGroups = collect();
        if ($bowler) {
            $mypageGroups = $bowler->groups()
                ->where('show_on_mypage', true)
                ->where(function ($q) {
                    $q->whereNull('group_members.expires_at')
                      ->orWhere('group_members.expires_at', '>=', now());
                })
                ->orderBy('name')
                ->get();
        }

        return view('member.dashboard', [
            'user'          => $user,
            'bowler'        => $bowler,
            'b'             => $bowler,       // 互換用
            'mypageGroups'  => $mypageGroups, // ← ビューでそのまま使う
        ]);
    }
}

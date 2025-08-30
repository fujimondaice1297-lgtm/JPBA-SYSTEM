<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class MemberDashboardController extends Controller
{
    public function index()
    {
        $user   = Auth::user();
        $bowler = $user->proBowler()->first(); // 公開情報表示用

        return view('member.dashboard', compact('user', 'bowler'));
    }
}

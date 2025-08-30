<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProBowler;

class ProBowlerController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q'); // 入力されたキーワードを取得

        return ProBowler::where('name_kanji', 'like', "%{$query}%")
            ->orWhere('license_no', 'like', "%{$query}%")
            ->select('license_no', 'name_kanji')
            ->limit(20)
            ->get();
    }
}

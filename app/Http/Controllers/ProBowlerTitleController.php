<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\ProBowlerTitle;
use Illuminate\Http\Request;

class ProBowlerTitleController extends Controller
{
    public function store(Request $request, $bowlerId)
    {
        $bowler = ProBowler::findOrFail($bowlerId);

        $data = $request->validate([
            'title_name' => 'required|string|max:255',
            'year'       => 'required|integer|min:1950|max:2100',
            'won_date'   => 'nullable|date',
        ]);

        $data['pro_bowler_id'] = $bowler->id;
        $data['source'] = 'manual';

        ProBowlerTitle::create($data);

        return back()->with('success', 'タイトルを追加しました');
    }

    public function destroy($bowlerId, $titleId)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'この操作は許可されていません。');
        }
        $title = ProBowlerTitle::where('pro_bowler_id', $bowlerId)->findOrFail($titleId);
        $title->delete();
        return back()->with('success', 'タイトルを削除しました');
    }
}

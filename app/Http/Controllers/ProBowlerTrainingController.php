<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\ProBowler;
use App\Models\Training;
use App\Models\ProBowlerTraining;
use Illuminate\Support\Facades\Log;

class ProBowlerTrainingController extends Controller
{
    public function store(Request $request, ProBowler $pro_bowler)
    {
        $validated = $request->validate([
            'training_code' => 'required|string',
            'completed_at'  => 'required|date',
        ]);

        $training  = Training::where('code', $validated['training_code'])->firstOrFail();

        // 受講日
        $completed = Carbon::parse($validated['completed_at']);

        // 3年後の“前日”まで有効（うるう年でも破綻しない）
        $expires = (clone $completed)->addYearsNoOverflow(3)->subDay();

        // 常に新規レコードを作成（過去の有効期限が残っていても履歴を追加）
        ProBowlerTraining::create([
            'pro_bowler_id' => $pro_bowler->id,
            'training_id'   => $training->id,
            'completed_at'  => $completed,
            'expires_at'    => $expires,
            'proof_path'    => null,
            'notes'         => null,
        ]);

        // 保存成功直後（参照だけ、未使用でも害なし）
        $target = route('pro_bowlers.edit', ['id' => $pro_bowler->id]);

        // 編集画面へリダイレクト
        return redirect()->route('pro_bowlers.edit', ['id' => $pro_bowler->id])
            ->with('success', '受講記録を登録しました。');
    }
}

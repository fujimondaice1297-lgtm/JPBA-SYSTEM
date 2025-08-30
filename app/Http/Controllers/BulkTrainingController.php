<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\ProBowler;
use App\Models\Training;
use App\Models\ProBowlerTraining;

class BulkTrainingController extends Controller
{
    public function create()
    {
        return view('trainings.bulk_form');
    }

    public function store(Request $request)
    {
        // rows は最大20行まで
        $validated = $request->validate([
            'rows'                     => ['required','array','max:20'],
            'rows.*.license_no'        => ['nullable','string','max:255'],
            'rows.*.completed_at'      => ['nullable','date'],
        ]);

        $rows = $validated['rows'];

        // 必修トレーニングIDを取得
        $trainingId = Training::where('code', 'mandatory')->value('id');
        if (!$trainingId) {
            return back()->withErrors(['training' => '必修講習（mandatory）が未定義です。Seederを流してください。']);
        }

        $results = ['ok'=>0,'ng'=>0,'detail'=>[]];

        DB::transaction(function () use ($rows, $trainingId, &$results) {
            foreach ($rows as $i => $r) {
                $ln  = trim((string)($r['license_no'] ?? ''));
                $dt  = $r['completed_at'] ?? null;

                // 空行はスキップ
                if ($ln === '' && !$dt) continue;

                // バリデーション
                if ($ln === '' || !$dt) {
                    $results['ng']++;
                    $results['detail'][] = "行".($i+1).": ライセンスNoと受講日は必須。";
                    continue;
                }

                $bowler = ProBowler::where('license_no', $ln)->first();
                if (!$bowler) {
                    $results['ng']++;
                    $results['detail'][] = "行".($i+1).": ライセンスNo {$ln} の選手が見つかりません。";
                    continue;
                }

                $completed = Carbon::parse($dt);
                $expires   = (clone $completed)->addYearsNoOverflow(3)->subDay();

                // 記録を追加（同じ日が既にあれば上書き、無ければ新規）
                ProBowlerTraining::updateOrCreate(
                    [
                        'pro_bowler_id' => $bowler->id,
                        'training_id'   => $trainingId,
                        'completed_at'  => $completed,   // 同一日を一意キーの一部にする運用
                    ],
                    [
                        'expires_at'    => $expires,
                        'proof_path'    => null,
                        'notes'         => null,
                    ]
                );

                $results['ok']++;
            }
        });

        $msg = "登録完了: {$results['ok']}件 / 失敗: {$results['ng']}件";
        return back()->with('success', $msg)->with('bulk_detail', $results['detail']);
    }
}

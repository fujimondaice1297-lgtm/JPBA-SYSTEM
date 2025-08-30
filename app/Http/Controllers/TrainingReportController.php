<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use App\Models\Training;
use App\Models\ProBowler;

class TrainingReportController extends Controller
{
    /**
     * /admin/trainings/reports/{scope?}
     * scope: compliant | missing | expired | expiring
     */
    public function index(Request $request, ?string $scope = null)
    {
        // フィルタ日数（デフォルト365日）
        $days   = (int) $request->input('days', 365);
        $today  = CarbonImmutable::today();
        $border = $today->addDays($days);

        // 必修講習のID
        $mandatoryId = Training::where('code', 'mandatory')->value('id');

        // pro_bowler_idごとの最新の有効期限（MAX(expires_at)）を1行にまとめるサブクエリ
        $latestSub = DB::table('pro_bowler_trainings')
            ->selectRaw('pro_bowler_id, MAX(expires_at) AS last_exp')
            ->where('training_id', $mandatoryId)
            ->groupBy('pro_bowler_id');

        // 一覧は常に最新mandatory履歴を一緒にロード（画面表示用）
        $q = ProBowler::query()->with([
            'latestMandatoryTraining' => function ($s) use ($mandatoryId) {
                $s->where('training_id', $mandatoryId);
            },
        ]);

        // 抽出条件
        switch ($scope) {
            case 'missing':
                // mandatoryの履歴が一件もない
                $q->whereNotIn('id', function ($sub) use ($mandatoryId) {
                    $sub->select('pro_bowler_id')
                        ->from('pro_bowler_trainings')
                        ->where('training_id', $mandatoryId);
                });
                break;

            case 'expired':
                // 最新期限が今日より前
                $q->whereIn('id', function ($sub) use ($latestSub, $today) {
                    $sub->fromSub($latestSub, 'mt')
                        ->select('mt.pro_bowler_id')
                        ->whereDate('mt.last_exp', '<', $today->toDateString());
                });
                break;

            case 'expiring':
                // 最新期限が今日〜border以内
                $q->whereIn('id', function ($sub) use ($latestSub, $today, $border) {
                    $sub->fromSub($latestSub, 'mt')
                        ->select('mt.pro_bowler_id')
                        ->whereDate('mt.last_exp', '>=', $today->toDateString())
                        ->whereDate('mt.last_exp', '<=', $border->toDateString());
                });
                break;

            case 'compliant':
            default:
                // 最新期限がborderより先（十分余裕）
                $q->whereIn('id', function ($sub) use ($latestSub, $border) {
                    $sub->fromSub($latestSub, 'mt')
                        ->select('mt.pro_bowler_id')
                        ->whereDate('mt.last_exp', '>', $border->toDateString());
                });
                $scope = 'compliant';
                break;
        }

        $bowlers = $q->orderBy('id')->paginate(50)->withQueryString();

        return view('trainings.reports', [
            'bowlers' => $bowlers,
            'scope'   => $scope,
            'days'    => $days,
        ]);
    }
}

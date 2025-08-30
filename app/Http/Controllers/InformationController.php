<?php

namespace App\Http\Controllers;

use App\Models\Information;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformationController extends Controller
{
    /** 一般公開 */
    public function index(Request $request)
    {
        $year = $request->input('year');
        if ($year === null) { $year = now()->year; } // デフォルト：今年

        $infos = Information::active()->public()
            ->when($year, function ($q) use ($year) {
                // updated_at の年でフィルタ。なければ starts_at/created_at を使っても良いが設計統一のため updated_at で。
                $q->whereYear('updated_at', $year);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20) // 1ページ20件（好みで変更）
            ->withQueryString();

        $availableYears = $this->years();

        return view('informations.index', compact('infos','availableYears'));
    }

    /** 会員向け（要ログイン） */
    public function member(Request $request)
    {
        $user = $request->user();
        $year = $request->input('year');
        if ($year === null) { $year = now()->year; }

        $infos = Information::active()->forUser($user)
            ->when($year, fn($q) => $q->whereYear('updated_at', $year))
            ->orderByDesc('updated_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $availableYears = $this->years();

        return view('informations.member', compact('infos','availableYears'));
    }

    /** 情報が存在する年一覧（降順） */
    private function years(): array
    {
        return DB::table('informations')
            ->selectRaw("DISTINCT EXTRACT(YEAR FROM COALESCE(updated_at, starts_at, created_at))::int AS y")
            ->orderByDesc('y')
            ->pluck('y')
            ->all();
    }
}

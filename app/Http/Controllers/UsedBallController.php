<?php

namespace App\Http\Controllers;

use App\Models\UsedBall;
use App\Models\ProBowler;
use App\Models\ApprovedBall;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UsedBallController extends Controller
{
    public function index(Request $request)
    {
        $query = UsedBall::with(['approvedBall', 'proBowler']);

        // 検索：プロボウラーのIDまたは名前
        if ($request->filled('search')) {
            $keyword = $request->input('search');
            $query->whereHas('proBowler', function ($q) use ($keyword) {
                $q->where('id', 'like', "%$keyword%")
                ->orWhere('name_kanji', 'like', "%$keyword%")
                ->orWhere('license_no', 'like', "%$keyword%");
            });
        }

        // ★修正：仮登録（expires_at が NULL）も表示対象に含める
        $query->where(function($q){
            $q->whereNull('expires_at')
            ->orWhereDate('expires_at', '>=', today());
        });

        $usedBalls = $query->orderByDesc('registered_at')->paginate(10);

        return view('used_balls.index', compact('usedBalls'));
    }

    public function create(Request $request)
    {
        $manufacturer = $request->query('manufacturer');

        $query = ApprovedBall::query();
        if ($manufacturer) {
            $query->where('manufacturer', $manufacturer);
        }

        $balls = $query->get();
        $manufacturers = ApprovedBall::distinct()->pluck('manufacturer');

        return view('used_balls.create', compact('balls', 'manufacturers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'license_no'         => 'required|string|exists:pro_bowlers,license_no',
            'approved_ball_id'   => 'required|integer|exists:approved_balls,id',
            'serial_number'      => 'required|string|unique:used_balls,serial_number',
            'inspection_number'  => 'nullable|string|unique:used_balls,inspection_number',
            'registered_at'      => 'required|date',
        ]);

        $proBowler = ProBowler::where('license_no', $validated['license_no'])->first();
        if (!$proBowler) {
            return back()->withErrors(['license_no' => 'ライセンス番号に一致するプロボウラーがいません'])->withInput();
        }

        $validated['pro_bowler_id'] = $proBowler->id;
        unset($validated['license_no']);

        // ★修正：検量証がある場合のみ有効期限を付与。なければ NULL（=仮登録）
        if (!empty($validated['inspection_number'])) {
            $validated['expires_at'] = Carbon::parse($validated['registered_at'])->addYear()->subDay();
        } else {
            $validated['expires_at'] = null;
        }

        UsedBall::create($validated);

        return redirect()->route('used_balls.index')->with('success', '使用ボールを登録しました。');
    }

    // 有効期限を1年延長（更新）
    public function update(Request $request, UsedBall $usedBall)
    {
        $validated = $request->validate([
            'inspection_number' => 'nullable|string|unique:used_balls,inspection_number,' . $usedBall->id,
        ]);

        // もし検量証番号が入力された場合は有効期限をセット
        if (!empty($validated['inspection_number'])) {
            $validated['registered_at'] = now();
            $validated['expires_at']    = now()->addYear()->subDay();
        }

        $usedBall->update($validated);

        return back()->with('success', 'ボール情報を更新しました');
    }


    // 削除処理
    public function destroy(UsedBall $usedBall)
    {
        $usedBall->delete();
        return back()->with('success', '削除しました');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RegisteredBall;
use App\Models\UsedBall;
use App\Models\ProBowler;
use App\Models\ApprovedBall;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class RegisteredBallController extends Controller
{
    public function index(Request $request)
    {
        // ===== まずは RegisteredBall（本登録） =====
        $regQ = RegisteredBall::with(['approvedBall', 'proBowler']);

        if ($request->filled('license_no')) {
            $regQ->where('license_no', 'like', '%' . $request->license_no . '%');
        }
        if ($request->filled('name')) {
            $regQ->whereHas('proBowler', function ($qq) use ($request) {
                $qq->where('name_kanji', 'like', '%' . $request->name . '%');
            });
        }
        // 1=あり, 0=なし, 空=両方
        if ($request->has('has_certificate') && $request->has_certificate !== '') {
            if ($request->has_certificate === '1') {
                $regQ->whereNotNull('inspection_number');
            } else {
                $regQ->whereNull('inspection_number');
            }
        }
        $regs = $regQ->get()->map(function (RegisteredBall $rb) {
            return [
                'source'            => 'registered', // 本登録
                'id'                => $rb->id,
                'license_no'        => optional($rb->proBowler)->license_no,
                'name_kanji'        => optional($rb->proBowler)->name_kanji,
                'manufacturer'      => $rb->approvedBall->manufacturer ?? $rb->approvedBall->brand ?? '',
                'ball_name'         => $rb->approvedBall->name ?? '',
                'serial_number'     => $rb->serial_number,
                'registered_at'     => $rb->registered_at,
                'expires_at'        => $rb->expires_at,
                'inspection_number' => $rb->inspection_number,
                // 画面用に元モデルも持たせておく（編集リンク等で使用）
                '_model'            => $rb,
            ];
        });

        // ===== 次に UsedBall（仮登録＝検量証未入力のみを拾う） =====
        $usedQ = UsedBall::with(['approvedBall', 'proBowler'])
            ->whereNull('inspection_number'); // 仮登録だけ

        if ($request->filled('license_no')) {
            // used_balls 側は license_no を pro_bowlers 経由で絞り込み
            $license = $request->license_no;
            $usedQ->whereHas('proBowler', function ($qq) use ($license) {
                $qq->where('license_no', 'like', '%' . $license . '%');
            });
        }
        if ($request->filled('name')) {
            $name = $request->name;
            $usedQ->whereHas('proBowler', function ($qq) use ($name) {
                $qq->where('name_kanji', 'like', '%' . $name . '%');
            });
        }
        // 検量証フィルタ：1=あり の場合は仮登録は対象外、0=なし の場合だけ含める、空は両方表示（=仮登録も表示）
        if ($request->has('has_certificate') && $request->has_certificate !== '') {
            if ($request->has_certificate === '1') {
                $usedQ->whereRaw('1=0'); // 強制的に0件（仮登録は検量証なしのため）
            } else {
                // 0 のときはそのまま（検量証なし＝仮登録を表示）
            }
        }
        $useds = $usedQ->get()->map(function (UsedBall $ub) {
            return [
                'source'            => 'used', // 仮登録
                'id'                => $ub->id,
                'license_no'        => optional($ub->proBowler)->license_no,
                'name_kanji'        => optional($ub->proBowler)->name_kanji,
                'manufacturer'      => $ub->approvedBall->manufacturer ?? $ub->approvedBall->brand ?? '',
                'ball_name'         => $ub->approvedBall->name ?? '',
                'serial_number'     => $ub->serial_number,
                'registered_at'     => $ub->registered_at,
                'expires_at'        => $ub->expires_at,      // 仮登録は基本 null
                'inspection_number' => $ub->inspection_number, // 仮登録は null
                '_model'            => $ub,
            ];
        });

        // ===== 結合して新しい順にソート、手動ページネーション =====
        /** @var \Illuminate\Support\Collection $all */
        $all = $regs->concat($useds)->sortByDesc(function ($row) {
            // 日付優先、同日なら「仮→本」より「本→仮」の優先を下げたいなら id で調整
            return sprintf('%s-%09d', optional($row['registered_at'])->format('YmdHis') ?? '00000000000000', $row['id']);
        })->values();

        $perPage  = 20;
        $page     = (int)($request->input('page', 1));
        $slice    = $all->slice(($page - 1) * $perPage, $perPage)->values();
        $balls    = new LengthAwarePaginator($slice, $all->count(), $perPage, $page, [
            'path'     => $request->url(),
            'query'    => $request->query(),
        ]);

        return view('registered_balls.index', compact('balls'));
    }

    public function create()
    {
        $approvedBalls = ApprovedBall::where('approved', true)->get();
        $proBowlers    = ProBowler::all();

        $manufacturers = [
            'ABS','900Global','Pro-am','MOTIV','HI-SP','STORM','ROTOGRIP',
            'Hammer','EBONITE','Track','Columbia300','Brunswick','Radical','DV8'
        ];

        return view('registered_balls.create', compact('approvedBalls','proBowlers','manufacturers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'license_no'        => ['required','exists:pro_bowlers,license_no'],
            'approved_ball_id'  => ['required','exists:approved_balls,id'],
            'serial_number'     => ['required','string','max:255',
                Rule::unique('registered_balls')->where(function ($q) use ($request) {
                    $year = \Carbon\Carbon::parse($request->registered_at)->year;
                    return $q->where('license_no', $request->license_no)
                             ->whereYear('registered_at', $year);
                }),
            ],
            'registered_at'     => ['required','date'],
            'inspection_number' => ['nullable','string','max:255'],
            'certificate_number'=> ['nullable','string','max:255'],
        ]);

        $inspection = trim((string)($request->input('inspection_number')
            ?? $request->input('certificate_number') ?? ''));

        $data = $request->only(['license_no','approved_ball_id','serial_number','registered_at']);
        $data['inspection_number'] = ($inspection === '') ? null : $inspection;
        $data['expires_at'] = $data['inspection_number']
            ? \Carbon\Carbon::parse($data['registered_at'])->addYear()->subDay()->toDateString()
            : null;

        RegisteredBall::create($data);

        return redirect()->route('registered_balls.index')->with('success', '登録完了');
    }

    public function edit(RegisteredBall $registeredBall)
    {
        $approvedBalls = ApprovedBall::where('approved', true)->get();
        $proBowlers    = ProBowler::all();

        return view('registered_balls.edit', compact('registeredBall','approvedBalls','proBowlers'));
    }

    public function update(Request $request, RegisteredBall $registeredBall)
    {
        $request->validate([
            'license_no'        => ['required','exists:pro_bowlers,license_no'],
            'approved_ball_id'  => ['required','exists:approved_balls,id'],
            'serial_number'     => ['required','string','max:255',
                Rule::unique('registered_balls')
                    ->ignore($registeredBall->id)
                    ->where(function ($q) use ($request) {
                        $year = \Carbon\Carbon::parse($request->registered_at)->year;
                        return $q->where('license_no', $request->license_no)
                                 ->whereYear('registered_at', $year);
                    }),
            ],
            'registered_at'     => ['required','date'],
            'inspection_number' => ['nullable','string','max:255'],
            'certificate_number'=> ['nullable','string','max:255'],
        ]);

        $inspection = trim((string)($request->input('inspection_number')
            ?? $request->input('certificate_number') ?? ''));

        $data = $request->only(['license_no','approved_ball_id','serial_number','registered_at']);
        $data['inspection_number'] = ($inspection === '') ? null : $inspection;
        $data['expires_at'] = $data['inspection_number']
            ? \Carbon\Carbon::parse($data['registered_at'])->addYear()->subDay()->toDateString()
            : null;

        $registeredBall->update($data);

        return redirect()->route('registered_balls.index')->with('success', '更新完了');
    }

    public function destroy(RegisteredBall $registeredBall)
    {
        $registeredBall->delete();
        return redirect()->route('registered_balls.index')->with('success', '削除完了');
    }
}

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
        $user = $request->user();

        // ===== 本登録 =====
        $regQ = RegisteredBall::with(['approvedBall', 'proBowler']);

        // ★ 会員は自分だけ（license_no で紐付け）
        if (!($user->isAdmin() || $user->isEditor())) {
            $regQ->where('license_no', $user->pro_bowler_license_no);
        }

        if ($request->filled('license_no')) {
            $regQ->where('license_no', 'like', '%' . $request->license_no . '%');
        }
        if ($request->filled('name')) {
            $regQ->whereHas('proBowler', function ($qq) use ($request) {
                $qq->where('name_kanji', 'like', '%' . $request->name . '%');
            });
        }
        if ($request->has('has_certificate') && $request->has_certificate !== '') {
            $request->has_certificate === '1'
                ? $regQ->whereNotNull('inspection_number')
                : $regQ->whereNull('inspection_number');
        }

        $regs = $regQ->get()->map(function (RegisteredBall $rb) {
            return [
                'source'            => 'registered',
                'id'                => $rb->id,
                'license_no'        => optional($rb->proBowler)->license_no,
                'name_kanji'        => optional($rb->proBowler)->name_kanji,
                'manufacturer'      => $rb->approvedBall->manufacturer ?? $rb->approvedBall->brand ?? '',
                'ball_name'         => $rb->approvedBall->name ?? '',
                'serial_number'     => $rb->serial_number,
                'registered_at'     => $rb->registered_at,
                'expires_at'        => $rb->expires_at,
                'inspection_number' => $rb->inspection_number,
                '_model'            => $rb,
            ];
        });

        // ===== 仮登録（UsedBall：検量証なしのみ） =====
        $usedQ = UsedBall::with(['approvedBall', 'proBowler'])
            ->whereNull('inspection_number');

        // ★ 会員は自分だけ（pro_bowler_id で絞る）
        if (!($user->isAdmin() || $user->isEditor())) {
            $usedQ->where('pro_bowler_id', $user->pro_bowler_id);
        }

        if ($request->filled('license_no')) {
            $license = $request->license_no;
            $usedQ->whereHas('proBowler', fn($qq) => $qq->where('license_no','like',"%{$license}%"));
        }
        if ($request->filled('name')) {
            $name = $request->name;
            $usedQ->whereHas('proBowler', fn($qq) => $qq->where('name_kanji','like',"%{$name}%"));
        }
        if ($request->has('has_certificate') && $request->has_certificate !== '') {
            if ($request->has_certificate === '1') $usedQ->whereRaw('1=0');
        }

        $useds = $usedQ->get()->map(function (UsedBall $ub) {
            return [
                'source'            => 'used',
                'id'                => $ub->id,
                'license_no'        => optional($ub->proBowler)->license_no,
                'name_kanji'        => optional($ub->proBowler)->name_kanji,
                'manufacturer'      => $ub->approvedBall->manufacturer ?? $ub->approvedBall->brand ?? '',
                'ball_name'         => $ub->approvedBall->name ?? '',
                'serial_number'     => $ub->serial_number,
                'registered_at'     => $ub->registered_at,
                'expires_at'        => $ub->expires_at,
                'inspection_number' => $ub->inspection_number,
                '_model'            => $ub,
            ];
        });

        // ===== 結合・ソート・ページネーション =====
        $all = $regs->concat($useds)->sortByDesc(function ($row) {
            return sprintf('%s-%09d', optional($row['registered_at'])->format('YmdHis') ?? '00000000000000', $row['id']);
        })->values();

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();
        $balls = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice, $all->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

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
        if (!auth()->user()->isAdmin()) {
            abort(403, 'この操作は許可されていません。');
        }
        $registeredBall->delete();
        return redirect()->route('registered_balls.index')->with('success', '削除完了');
    }
}

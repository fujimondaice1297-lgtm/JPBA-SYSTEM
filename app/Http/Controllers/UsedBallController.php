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
        $user = $request->user();
        $isStaff = $this->canManageAllBalls($user);

        $query = UsedBall::with(['approvedBall', 'proBowler']);

        if ($request->filled('search')) {
            $keyword = trim((string) $request->input('search'));

            $query->where(function ($q) use ($keyword) {
                $q->where('serial_number', 'like', "%{$keyword}%")
                    ->orWhere('inspection_number', 'like', "%{$keyword}%")
                    ->orWhereHas('proBowler', function ($qq) use ($keyword) {
                        $qq->where('license_no', 'like', "%{$keyword}%")
                            ->orWhere('name_kanji', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('approvedBall', function ($qq) use ($keyword) {
                        $qq->where('name', 'like', "%{$keyword}%")
                            ->orWhere('manufacturer', 'like', "%{$keyword}%");
                    });
            });
        }

        if (!$isStaff) {
            $query->where('pro_bowler_id', $user->pro_bowler_id);
        }

        $status = (string) $request->input('status');
        switch ($status) {
            case 'temporary':
                $query->whereNull('inspection_number');
                break;

            case 'active':
                $query->whereNotNull('inspection_number')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>=', today());
                break;

            case 'expiring':
                $query->whereNotNull('inspection_number')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>=', today())
                    ->whereDate('expires_at', '<=', today()->copy()->addDays(30));
                break;

            case 'expired':
                $query->whereNotNull('inspection_number')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '<', today());
                break;
        }

        $usedBalls = $query
            ->orderByRaw("case when inspection_number is null then 0 else 1 end asc")
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->appends($request->query());

        return view('used_balls.index', compact('usedBalls'));
    }

    public function create(Request $request)
    {
        $manufacturer = $request->query('manufacturer');

        $query = ApprovedBall::query();
        if ($manufacturer) {
            $query->where('manufacturer', $manufacturer);
        }

        $balls = $query
            ->orderBy('manufacturer')
            ->orderBy('name')
            ->get();

        $manufacturers = ApprovedBall::query()
            ->whereNotNull('manufacturer')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer');

        return view('used_balls.create', compact('balls', 'manufacturers'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isStaff = $this->canManageAllBalls($user);

        $rules = [
            'approved_ball_id'  => 'required|integer|exists:approved_balls,id',
            'serial_number'     => 'required|string|unique:used_balls,serial_number',
            'inspection_number' => 'nullable|string|unique:used_balls,inspection_number',
            'registered_at'     => 'required|date',
        ];

        if ($isStaff) {
            $rules['license_no'] = 'required|string|exists:pro_bowlers,license_no';
        }

        $validated = $request->validate($rules);

        if ($isStaff) {
            $proBowler = ProBowler::where('license_no', $validated['license_no'])->first();
            if (!$proBowler) {
                return back()
                    ->withErrors(['license_no' => 'ライセンス番号に一致するプロボウラーがいません'])
                    ->withInput();
            }
        } else {
            $proBowler = ProBowler::find($user->pro_bowler_id);
            if (!$proBowler || empty($proBowler->license_no)) {
                return back()
                    ->withErrors(['license_no' => '自分のプロ情報が見つからないため登録できません。'])
                    ->withInput();
            }
        }

        $inspectionNumber = trim((string) ($validated['inspection_number'] ?? ''));
        $registeredAt = Carbon::parse($validated['registered_at']);

        UsedBall::create([
            'pro_bowler_id'     => $proBowler->id,
            'approved_ball_id'  => $validated['approved_ball_id'],
            'serial_number'     => $validated['serial_number'],
            'inspection_number' => $inspectionNumber === '' ? null : $inspectionNumber,
            'registered_at'     => $registeredAt,
            'expires_at'        => $inspectionNumber === ''
                ? null
                : $registeredAt->copy()->addYear()->subDay(),
        ]);

        return redirect()
            ->route('used_balls.index')
            ->with('success', '使用ボールを登録しました。');
    }

    public function edit(UsedBall $usedBall)
    {
        $this->authorizeBallOwnerOrStaff($usedBall);

        return view('used_balls.edit', compact('usedBall'));
    }

    public function update(Request $request, UsedBall $usedBall)
    {
        $this->authorizeBallOwnerOrStaff($usedBall);

        $validated = $request->validate([
            'inspection_number' => 'nullable|string|unique:used_balls,inspection_number,' . $usedBall->id,
        ]);

        $inspectionNumber = trim((string) ($validated['inspection_number'] ?? ''));

        if ($inspectionNumber === '') {
            $usedBall->update([
                'inspection_number' => null,
                'expires_at'        => null,
            ]);

            return back()->with('success', '仮登録状態に更新しました。');
        }

        $now = now();

        $usedBall->update([
            'inspection_number' => $inspectionNumber,
            'registered_at'     => $now,
            'expires_at'        => $now->copy()->addYear()->subDay(),
        ]);

        return back()->with('success', 'ボール情報を更新しました。');
    }

    public function destroy(UsedBall $usedBall)
    {
        $this->authorizeAdminUser();

        $usedBall->delete();

        return back()->with('success', '削除しました');
    }

    private function canManageAllBalls($user): bool
    {
        if (!$user) {
            return false;
        }

        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : (bool) ($user->is_admin ?? false);
        $isEditor = method_exists($user, 'isEditor') ? $user->isEditor() : (bool) ($user->is_editor ?? false);

        return $isAdmin || $isEditor;
    }

    private function authorizeBallOwnerOrStaff(UsedBall $usedBall): void
    {
        $user = auth()->user();

        if ($this->canManageAllBalls($user)) {
            return;
        }

        if ((int) ($user?->pro_bowler_id ?? 0) !== (int) $usedBall->pro_bowler_id) {
            abort(403, 'このボールは編集できません。');
        }
    }

    private function authorizeAdminUser(): void
    {
        $user = auth()->user();
        $isAdmin = $user && (method_exists($user, 'isAdmin') ? $user->isAdmin() : (bool) ($user->is_admin ?? false));

        if (!$isAdmin) {
            abort(403, 'この操作は許可されていません。');
        }
    }
}
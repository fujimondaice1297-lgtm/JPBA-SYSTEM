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
        $isPrivileged = $this->isPrivilegedUser($user);

        $query = UsedBall::with(['approvedBall', 'proBowler']);

        if ($request->filled('search')) {
            $keyword = trim((string) $request->input('search'));

            $query->where(function ($q) use ($keyword) {
                $q->where('serial_number', 'like', "%{$keyword}%")
                    ->orWhere('inspection_number', 'like', "%{$keyword}%")
                    ->orWhereHas('approvedBall', function ($qq) use ($keyword) {
                        $qq->where('name', 'like', "%{$keyword}%")
                            ->orWhere('model_name', 'like', "%{$keyword}%")
                            ->orWhere('manufacturer', 'like', "%{$keyword}%")
                            ->orWhere('brand', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('proBowler', function ($qq) use ($keyword) {
                        $qq->where('id', 'like', "%{$keyword}%")
                            ->orWhere('name_kanji', 'like', "%{$keyword}%")
                            ->orWhere('license_no', 'like', "%{$keyword}%");
                    });
            });
        }

        if (!$isPrivileged) {
            $userProBowlerId = (int) ($user->pro_bowler_id ?? 0);
            if ($userProBowlerId <= 0) {
                abort(403, 'プロ情報が未結線のため、使用ボール一覧を利用できません。');
            }
            $query->where('pro_bowler_id', $userProBowlerId);
        }

        $status = (string) $request->input('status', '');
        $this->applyStatusFilter($query, $status);

        $usedBalls = $query->orderByDesc('registered_at')->paginate(10)->withQueryString();

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
        $fixedLicenseNo = null;

        if (!$this->isPrivilegedUser($request->user())) {
            $fixedLicenseNo = $this->resolveCurrentUserLicenseNo($request->user());
            if (!$fixedLicenseNo) {
                abort(403, 'プロ情報が未結線のため、使用ボールを登録できません。');
            }
        }

        return view('used_balls.create', compact('balls', 'manufacturers', 'fixedLicenseNo'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isPrivileged = $this->isPrivilegedUser($user);
        $licenseNo = $isPrivileged ? $request->input('license_no') : $this->resolveCurrentUserLicenseNo($user);

        if (!$licenseNo) {
            abort(403, 'プロ情報が未結線のため、使用ボールを登録できません。');
        }

        $validated = $request->validate([
            'license_no'        => 'nullable|string|exists:pro_bowlers,license_no',
            'approved_ball_id'  => 'required|integer|exists:approved_balls,id',
            'serial_number'     => 'required|string|unique:used_balls,serial_number',
            'inspection_number' => 'nullable|string|unique:used_balls,inspection_number',
            'registered_at'     => 'required|date',
        ]);

        $proBowler = ProBowler::where('license_no', $licenseNo)->first();
        if (!$proBowler) {
            return back()->withErrors(['license_no' => 'ライセンス番号に一致するプロボウラーがいません'])->withInput();
        }

        $inspectionNumber = trim((string) ($validated['inspection_number'] ?? ''));
        if ($inspectionNumber === '') {
            $inspectionNumber = null;
        }

        $payload = [
            'pro_bowler_id'     => $proBowler->id,
            'approved_ball_id'  => $validated['approved_ball_id'],
            'serial_number'     => $validated['serial_number'],
            'inspection_number' => $inspectionNumber,
            'registered_at'     => $validated['registered_at'],
            'expires_at'        => $inspectionNumber
                ? Carbon::parse($validated['registered_at'])->addYear()->subDay()
                : null,
        ];

        UsedBall::create($payload);

        return redirect()->route('used_balls.index')->with('success', '使用ボールを登録しました。');
    }

    public function update(Request $request, UsedBall $usedBall)
    {
        $this->authorizeUsedBallAccess($request->user(), $usedBall);

        $validated = $request->validate([
            'inspection_number' => 'nullable|string|unique:used_balls,inspection_number,' . $usedBall->id,
        ]);

        $inspectionNumber = trim((string) ($validated['inspection_number'] ?? ''));
        if ($inspectionNumber === '') {
            $inspectionNumber = null;
        }

        $payload = [
            'inspection_number' => $inspectionNumber,
        ];

        if ($inspectionNumber) {
            $payload['registered_at'] = now();
            $payload['expires_at'] = now()->addYear()->subDay();
        } else {
            $payload['expires_at'] = null;
        }

        $usedBall->update($payload);

        return back()->with('success', 'ボール情報を更新しました');
    }

    public function destroy(UsedBall $usedBall)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'この操作は許可されていません。');
        }

        $usedBall->delete();

        return back()->with('success', '削除しました');
    }

    private function applyStatusFilter($query, string $status): void
    {
        $today = today();
        $soon = today()->copy()->addDays(30);

        switch ($status) {
            case 'provisional':
                $query->where(function ($q) {
                    $q->whereNull('inspection_number')
                        ->orWhereNull('expires_at');
                });
                break;

            case 'valid':
                $query->whereNotNull('inspection_number')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>', $soon);
                break;

            case 'expiring_soon':
                $query->whereNotNull('inspection_number')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>=', $today)
                    ->whereDate('expires_at', '<=', $soon);
                break;

            case 'expired':
                $query->whereNotNull('inspection_number')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '<', $today);
                break;
        }
    }

    private function authorizeUsedBallAccess($user, UsedBall $usedBall): void
    {
        if ($this->isPrivilegedUser($user)) {
            return;
        }

        if ((int) ($user?->pro_bowler_id ?? 0) !== (int) $usedBall->pro_bowler_id) {
            abort(403, 'この使用ボールは操作できません。');
        }
    }

    private function resolveCurrentUserLicenseNo($user): ?string
    {
        if (!$user) {
            return null;
        }

        $licenseNo = trim((string) ($user->pro_bowler_license_no ?? ''));
        if ($licenseNo !== '') {
            return $licenseNo;
        }

        $proBowlerId = (int) ($user->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            return ProBowler::query()->whereKey($proBowlerId)->value('license_no');
        }

        return null;
    }

    private function isPrivilegedUser($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->isAdmin() || $user->isEditor();
    }
}

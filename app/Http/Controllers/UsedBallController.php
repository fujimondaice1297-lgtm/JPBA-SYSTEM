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
        $query = UsedBall::with(['approvedBall', 'proBowler']);

        if ($request->filled('search')) {
            $keyword = trim((string) $request->input('search'));

            $query->where(function ($q) use ($keyword) {
                $q->where('serial_number', 'like', "%{$keyword}%")
                    ->orWhere('inspection_number', 'like', "%{$keyword}%")
                    ->orWhereHas('proBowler', function ($qq) use ($keyword) {
                        $qq->where('id', 'like', "%{$keyword}%")
                            ->orWhere('name_kanji', 'like', "%{$keyword}%")
                            ->orWhere('license_no', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('approvedBall', function ($qq) use ($keyword) {
                        $qq->where('name', 'like', "%{$keyword}%")
                            ->orWhere('manufacturer', 'like', "%{$keyword}%");
                    });
            });
        }

        if (!$this->isPrivilegedUser($user)) {
            $query->where('pro_bowler_id', $user->pro_bowler_id);
        }

        $status = (string) $request->input('status');
        switch ($status) {
            case 'temporary':
            case 'provisional':
                $query->where(function ($q) {
                    $q->whereNull('inspection_number')
                        ->orWhereNull('expires_at');
                });
                break;

            case 'valid':
                $query->whereNotNull('inspection_number')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>', today()->copy()->addDays(30));
                break;

            case 'expiring_soon':
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

            default:
                $query->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhereDate('expires_at', '>=', today());
                });
                break;
        }

        $usedBalls = $query
            ->orderByRaw("case when inspection_number is null then 0 else 1 end asc")
            ->orderByDesc('registered_at')
            ->paginate(10)
            ->appends($request->query());

        return view('used_balls.index', compact('usedBalls'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $manufacturer = (string) $request->query('manufacturer', '');
        $requestedLicenseNo = trim((string) $request->query('license_no', ''));
        $fixedLicenseNo = null;

        if ($this->isPrivilegedUser($user)) {
            $prefillLicenseNo = $requestedLicenseNo;
        } else {
            $fixedLicenseNo = $this->resolveCurrentUserLicenseNo($user);
            if (!$fixedLicenseNo) {
                abort(403, 'プロ情報が未結線のため、使用ボールを作成できません。');
            }
            $prefillLicenseNo = $fixedLicenseNo;
        }

        $query = ApprovedBall::query();
        if ($manufacturer !== '') {
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

        return view('used_balls.create', compact(
            'balls',
            'manufacturers',
            'manufacturer',
            'prefillLicenseNo',
            'fixedLicenseNo'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isPrivileged = $this->isPrivilegedUser($user);
        $fixedLicenseNo = $isPrivileged ? null : $this->resolveCurrentUserLicenseNo($user);

        if (!$isPrivileged && !$fixedLicenseNo) {
            abort(403, 'プロ情報が未結線のため、使用ボールを作成できません。');
        }

        $request->validate([
            'license_no'         => ['nullable', 'string', 'exists:pro_bowlers,license_no'],
            'approved_ball_id'   => ['required', 'integer', 'exists:approved_balls,id'],
            'serial_number'      => ['required', 'string', 'unique:used_balls,serial_number'],
            'inspection_number'  => ['nullable', 'string', 'unique:used_balls,inspection_number'],
            'registered_at'      => ['required', 'date'],
            'return_to'          => ['nullable', 'string', 'max:50'],
            'entry_id'           => ['nullable', 'integer'],
        ]);

        $licenseNo = $fixedLicenseNo ?: $request->input('license_no');

        $proBowler = ProBowler::where('license_no', $licenseNo)->first();
        if (!$proBowler) {
            return back()->withErrors([
                'license_no' => 'ライセンス番号に一致するプロボウラーがいません。',
            ])->withInput();
        }

        $inspectionNumber = trim((string) ($request->input('inspection_number') ?? ''));

        $payload = [
            'pro_bowler_id'     => $proBowler->id,
            'approved_ball_id'  => $request->input('approved_ball_id'),
            'serial_number'     => $request->input('serial_number'),
            'inspection_number' => $inspectionNumber === '' ? null : $inspectionNumber,
            'registered_at'     => $request->input('registered_at'),
        ];

        if ($inspectionNumber !== '') {
            $payload['expires_at'] = Carbon::parse($payload['registered_at'])->addYear()->subDay();
        } else {
            $payload['expires_at'] = null;
        }

        UsedBall::create($payload);

        return $this->redirectAfterSave($request, 'used_balls.index', '使用ボールを登録しました。');
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
            'return_to'         => ['nullable', 'string', 'max:50'],
            'entry_id'          => ['nullable', 'integer'],
        ]);

        $inspectionNumber = trim((string) ($validated['inspection_number'] ?? ''));

        if ($inspectionNumber === '') {
            $usedBall->update([
                'inspection_number' => null,
                'expires_at'        => null,
            ]);

            return $this->redirectAfterSave($request, 'used_balls.index', '仮登録状態に更新しました。');
        }

        $now = now();

        $usedBall->update([
            'inspection_number' => $inspectionNumber,
            'registered_at'     => $now,
            'expires_at'        => $now->copy()->addYear()->subDay(),
        ]);

        return $this->redirectAfterSave($request, 'used_balls.index', 'ボール情報を更新しました');
    }

    public function destroy(UsedBall $usedBall)
    {
        $user = auth()->user();
        $isAdmin = $user && (method_exists($user, 'isAdmin') ? $user->isAdmin() : (bool) ($user->is_admin ?? false));

        if (!$isAdmin) {
            abort(403, 'この操作は許可されていません。');
        }

        $usedBall->delete();

        return back()->with('success', '削除しました');
    }

    private function isPrivilegedUser($user): bool
    {
        if (!$user) {
            return false;
        }

        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : (bool) ($user->is_admin ?? false);
        $isEditor = method_exists($user, 'isEditor') ? $user->isEditor() : (bool) ($user->is_editor ?? false);

        return $isAdmin || $isEditor;
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

    private function authorizeBallOwnerOrStaff(UsedBall $usedBall): void
    {
        $user = auth()->user();

        if ($this->isPrivilegedUser($user)) {
            return;
        }

        if ((int) ($user?->pro_bowler_id ?? 0) !== (int) $usedBall->pro_bowler_id) {
            abort(403, 'このボールは編集できません。');
        }
    }

    private function redirectAfterSave(Request $request, string $defaultRoute, string $message)
    {
        $returnTo = (string) $request->input('return_to', '');
        $entryId = (int) $request->input('entry_id', 0);

        if ($returnTo === 'entry_balls' && $entryId > 0) {
            return redirect()
                ->route('member.entries.balls.edit', $entryId)
                ->with('success', $message);
        }

        return redirect()
            ->route($defaultRoute)
            ->with('success', $message);
    }
}
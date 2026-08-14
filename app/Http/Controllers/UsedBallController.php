<?php

namespace App\Http\Controllers;

use App\Models\UsedBall;
use App\Models\RegisteredBall;
use App\Models\ProBowler;
use App\Models\ApprovedBall;
use App\Services\BallInspectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsedBallController extends Controller
{
    public function __construct(
        private readonly BallInspectionService $inspectionService
    ) {
    }

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
            $payload['expires_at'] = $this->inspectionService
                ->expiresOn($payload['registered_at']);
        } else {
            $payload['expires_at'] = null;
        }

        $usedBall = UsedBall::create($payload);

        return $this->redirectAfterSave(
            $request,
            'used_balls.index',
            '使用ボールを登録しました。',
            $this->usbcWarningForBallId((int) $usedBall->approved_ball_id)
        );
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
            'registered_at'     => ['nullable', 'date', 'required_with:inspection_number'],
            'return_to'         => ['nullable', 'string', 'max:50'],
            'entry_id'          => ['nullable', 'integer'],
        ]);

        $inspectionNumber = trim((string) ($validated['inspection_number'] ?? ''));

        if ($inspectionNumber === '') {
            DB::transaction(function () use ($usedBall) {
                $usedBall->update([
                    'inspection_number' => null,
                    'expires_at'        => null,
                ]);

                $this->syncInspectionToRegisteredBall($usedBall->fresh());
            });

            return $this->redirectAfterSave($request, 'used_balls.index', '仮登録状態に更新しました。');
        }

        $inspectionDate = $validated['registered_at'];

        DB::transaction(function () use ($usedBall, $inspectionNumber, $inspectionDate) {
            $usedBall->update([
                'inspection_number' => $inspectionNumber,
                'registered_at'     => $inspectionDate,
                'expires_at'        => $this->inspectionService->expiresOn($inspectionDate),
            ]);

            $this->syncInspectionToRegisteredBall($usedBall->fresh());
        });

        return $this->redirectAfterSave($request, 'used_balls.index', '検量証情報を更新しました。');
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

    private function syncInspectionToRegisteredBall(UsedBall $usedBall): void
    {
        $licenseNo = trim((string) ($usedBall->proBowler?->license_no ?? ''));
        if ($licenseNo === '') {
            return;
        }

        $registeredBall = RegisteredBall::query()
            ->where('license_no', $licenseNo)
            ->whereRaw('upper(serial_number) = ?', [mb_strtoupper((string) $usedBall->serial_number)])
            ->first();

        if (!$registeredBall) {
            return;
        }

        $registeredBall->update([
            'inspection_number' => $usedBall->inspection_number,
            'registered_at' => $usedBall->registered_at,
            'expires_at' => $usedBall->expires_at,
        ]);
    }

    private function redirectAfterSave(
        Request $request,
        string $defaultRoute,
        string $message,
        ?string $warning = null
    )
    {
        $returnTo = (string) $request->input('return_to', '');
        $entryId = (int) $request->input('entry_id', 0);

        if ($returnTo === 'entry_balls' && $entryId > 0) {
            $redirect = redirect()
                ->route('member.entries.balls.edit', $entryId)
                ->with('success', $message);

            return $warning ? $redirect->with('warning', $warning) : $redirect;
        }

        $redirect = redirect()
            ->route($defaultRoute)
            ->with('success', $message);

        return $warning ? $redirect->with('warning', $warning) : $redirect;
    }

    private function usbcWarningForBallId(int $ballId): ?string
    {
        $status = ApprovedBall::query()
            ->whereKey($ballId)
            ->value('usbc_match_status');

        return match ($status) {
            'matched' => null,
            'ambiguous' => 'アブプールリストとの照合結果が要確認のボールです。',
            'unchecked' => 'アブプールリストとの照合が未実施のボールです。',
            default => 'アブプールリストに記載のないボールです',
        };
    }
}

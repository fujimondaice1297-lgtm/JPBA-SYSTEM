<?php

namespace App\Http\Controllers;

use App\Models\ApprovedBall;
use App\Models\ProBowler;
use App\Models\RegisteredBall;
use App\Models\UsedBall;
use App\Services\RegisteredBallLinkageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class RegisteredBallController extends Controller
{
    public function __construct(
        private readonly RegisteredBallLinkageService $linkageService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $isPrivileged = $this->isPrivilegedUser($user);
        $currentLicenseNo = $this->resolveCurrentUserLicenseNo($user);

        $status = (string) $request->input('status', '');
        $source = (string) $request->input('source', '');

        $regQ = RegisteredBall::with(['approvedBall', 'proBowler']);

        if (! $isPrivileged) {
            if (! $currentLicenseNo) {
                abort(403, 'プロ情報が未結線のため、登録ボール一覧を利用できません。');
            }
            $regQ->where('license_no', $currentLicenseNo);
        }

        if ($request->filled('license_no')) {
            $regQ->where('license_no', 'like', '%'.trim((string) $request->license_no).'%');
        }

        if ($request->filled('name')) {
            $name = trim((string) $request->name);
            $regQ->whereHas('proBowler', function ($qq) use ($name) {
                $qq->where('name_kanji', 'like', '%'.$name.'%');
            });
        }

        if ($request->has('has_certificate') && $request->has_certificate !== '') {
            $request->has_certificate === '1'
                ? $regQ->whereNotNull('inspection_number')
                : $regQ->whereNull('inspection_number');
        }

        $this->applyRegisteredStatusFilter($regQ, $status);

        $regs = $regQ->get()->map(function (RegisteredBall $rb) {
            $status = $this->buildStatusMeta($rb->inspection_number, $rb->expires_at);
            $licenseNo = $rb->license_no ?: optional($rb->proBowler)->license_no;

            return [
                'source' => 'registered',
                'source_label' => $status['key'] === 'provisional' ? '仮登録' : '本登録',
                'id' => $rb->id,
                'license_no' => $licenseNo,
                'name_kanji' => optional($rb->proBowler)->name_kanji,
                'brand' => $rb->approvedBall?->registration_brand ?? '',
                'ball_name' => $rb->approvedBall->name ?? $rb->approvedBall->model_name ?? '',
                'serial_number' => $rb->serial_number,
                'registered_at' => $rb->registered_at,
                'expires_at' => $rb->expires_at,
                'inspection_number' => $rb->inspection_number,
                'status_key' => $status['key'],
                'status_label' => $status['label'],
                'status_badge' => $status['badge'],
                'days_to_expire' => $status['days_to_expire'],
                'logical_key' => $this->logicalBallKey(
                    (int) ($rb->pro_bowler_id ?? optional($rb->proBowler)->id),
                    $licenseNo,
                    (string) $rb->serial_number
                ),
                '_model' => $rb,
            ];
        });

        $usedQ = UsedBall::with(['approvedBall', 'proBowler'])
            ->whereNull('inspection_number');

        if (! $isPrivileged) {
            $userProBowlerId = (int) ($user?->pro_bowler_id ?? 0);
            if ($userProBowlerId <= 0) {
                abort(403, 'プロ情報が未結線のため、登録ボール一覧を利用できません。');
            }
            $usedQ->where('pro_bowler_id', $userProBowlerId);
        }

        if ($request->filled('license_no')) {
            $license = trim((string) $request->license_no);
            $usedQ->whereHas('proBowler', fn ($qq) => $qq->where('license_no', 'like', "%{$license}%"));
        }

        if ($request->filled('name')) {
            $name = trim((string) $request->name);
            $usedQ->whereHas('proBowler', fn ($qq) => $qq->where('name_kanji', 'like', "%{$name}%"));
        }

        if ($request->has('has_certificate') && $request->has_certificate !== '') {
            if ($request->has_certificate === '1') {
                $usedQ->whereRaw('1 = 0');
            }
        }

        if ($status !== '' && $status !== 'provisional') {
            $usedQ->whereRaw('1 = 0');
        }

        $useds = $usedQ->get()->map(function (UsedBall $ub) {
            $status = $this->buildStatusMeta($ub->inspection_number, $ub->expires_at);
            $licenseNo = optional($ub->proBowler)->license_no;

            return [
                'source' => 'used',
                'source_label' => '仮登録',
                'id' => $ub->id,
                'license_no' => $licenseNo,
                'name_kanji' => optional($ub->proBowler)->name_kanji,
                'brand' => $ub->approvedBall?->registration_brand ?? '',
                'ball_name' => $ub->approvedBall->name ?? $ub->approvedBall->model_name ?? '',
                'serial_number' => $ub->serial_number,
                'registered_at' => $ub->registered_at,
                'expires_at' => $ub->expires_at,
                'inspection_number' => $ub->inspection_number,
                'status_key' => $status['key'],
                'status_label' => $status['label'],
                'status_badge' => $status['badge'],
                'days_to_expire' => $status['days_to_expire'],
                'logical_key' => $this->logicalBallKey(
                    (int) $ub->pro_bowler_id,
                    $licenseNo,
                    (string) $ub->serial_number
                ),
                '_model' => $ub,
            ];
        });

        // 本登録データからマイボールへ同期した同一実物は、保存上は2テーブルに存在する。
        // 検量証待ちの間は仮登録（used_balls）だけを表示し、利用者には1件として見せる。
        $mirroredRegisteredBalls = $regs
            ->where('status_key', 'provisional')
            ->keyBy('logical_key');
        $useds = $useds->map(function (array $row) use ($mirroredRegisteredBalls): array {
            $row['mirrored_registered_ball'] = $mirroredRegisteredBalls
                ->get($row['logical_key'])['_model'] ?? null;

            return $row;
        });
        $mirroredProvisionalBalls = $useds->keyBy('logical_key');
        $regs = $regs->reject(
            fn (array $row): bool => $row['status_key'] === 'provisional'
                && $mirroredProvisionalBalls->has($row['logical_key'])
        )->values();

        $all = $regs->concat($useds);

        if ($source === 'registered') {
            $all = $all->reject(fn (array $row): bool => $row['status_key'] === 'provisional')->values();
        } elseif ($source === 'used') {
            $all = $all->where('status_key', 'provisional')->values();
        }

        $summary = [
            'total' => $all->count(),
            'registered' => $all->where('status_key', '!=', 'provisional')->count(),
            'used' => $all->where('status_key', 'provisional')->count(),
            'provisional' => $all->where('status_key', 'provisional')->count(),
            'valid' => $all->where('status_key', 'valid')->count(),
            'expiring_soon' => $all->where('status_key', 'expiring_soon')->count(),
            'expired' => $all->where('status_key', 'expired')->count(),
        ];

        $all = $all->sortByDesc(function ($row) {
            $registeredAt = $row['registered_at'];
            $registeredKey = $registeredAt ? $registeredAt->format('YmdHis') : '00000000000000';

            return sprintf('%s-%09d', $registeredKey, $row['id']);
        })->values();

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();

        $balls = new LengthAwarePaginator(
            $slice,
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('registered_balls.index', compact('balls', 'summary'));
    }

    public function create(Request $request)
    {
        [$approvedBalls, $brands, $years] = $this->registrationCatalogData();
        $proBowlers = ProBowler::all();

        $fixedLicenseNo = null;
        if (! $this->isPrivilegedUser($request->user())) {
            $fixedLicenseNo = $this->resolveCurrentUserLicenseNo($request->user());
            if (! $fixedLicenseNo) {
                abort(403, 'プロ情報が未結線のため、本登録ボールを作成できません。');
            }
        }

        return view('registered_balls.create', compact(
            'approvedBalls',
            'proBowlers',
            'brands',
            'years',
            'fixedLicenseNo'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isPrivileged = $this->isPrivilegedUser($user);
        $fixedLicenseNo = $isPrivileged ? null : $this->resolveCurrentUserLicenseNo($user);

        if (! $isPrivileged && ! $fixedLicenseNo) {
            abort(403, 'プロ情報が未結線のため、本登録ボールを作成できません。');
        }

        $licenseNo = $fixedLicenseNo ?: $request->input('license_no');

        $request->validate([
            'license_no' => ['nullable', 'exists:pro_bowlers,license_no'],
            'approved_ball_id' => ['required', 'exists:approved_balls,id'],
            'serial_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('registered_balls')->where(function ($q) use ($request, $licenseNo) {
                    $year = Carbon::parse($request->registered_at)->year;

                    return $q->where('license_no', $licenseNo)
                        ->whereYear('registered_at', $year);
                }),
            ],
            'registered_at' => ['required', 'date'],
            'inspection_number' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'return_to' => ['nullable', 'string', 'max:50'],
            'entry_id' => ['nullable', 'integer'],
        ]);

        $inspection = trim((string) ($request->input('inspection_number')
            ?? $request->input('certificate_number')
            ?? ''));

        $data = [
            'pro_bowler_id' => ProBowler::query()->where('license_no', $licenseNo)->value('id'),
            'license_no' => $licenseNo,
            'approved_ball_id' => $request->input('approved_ball_id'),
            'serial_number' => $request->input('serial_number'),
            'registered_at' => $request->input('registered_at'),
        ];

        $data['inspection_number'] = ($inspection === '') ? null : $inspection;
        $data['expires_at'] = $data['inspection_number']
            ? Carbon::parse($data['registered_at'])->addYear()->subDay()->toDateString()
            : null;

        $registeredBall = RegisteredBall::create($data);

        $this->linkageService->sync($registeredBall);

        return $this->redirectAfterSave(
            $request,
            'registered_balls.index',
            '登録完了',
            $this->usbcWarningForBallId((int) $registeredBall->approved_ball_id)
        );
    }

    public function edit(Request $request, RegisteredBall $registeredBall)
    {
        $this->authorizeRegisteredBallAccess($request->user(), $registeredBall);

        [$approvedBalls, $brands, $years] = $this->registrationCatalogData();
        $proBowlers = ProBowler::all();
        $fixedLicenseNo = null;

        if (! $this->isPrivilegedUser($request->user())) {
            $fixedLicenseNo = $registeredBall->license_no;
        }

        return view('registered_balls.edit', compact(
            'registeredBall',
            'approvedBalls',
            'proBowlers',
            'brands',
            'years',
            'fixedLicenseNo'
        ));
    }

    public function update(Request $request, RegisteredBall $registeredBall)
    {
        $this->authorizeRegisteredBallAccess($request->user(), $registeredBall);

        $user = $request->user();
        $isPrivileged = $this->isPrivilegedUser($user);
        $licenseNo = $isPrivileged ? $request->input('license_no') : $registeredBall->license_no;

        $request->validate([
            'license_no' => ['nullable', 'exists:pro_bowlers,license_no'],
            'approved_ball_id' => ['required', 'exists:approved_balls,id'],
            'serial_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('registered_balls')
                    ->ignore($registeredBall->id)
                    ->where(function ($q) use ($request, $licenseNo) {
                        $year = Carbon::parse($request->registered_at)->year;

                        return $q->where('license_no', $licenseNo)
                            ->whereYear('registered_at', $year);
                    }),
            ],
            'registered_at' => ['required', 'date'],
            'inspection_number' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'return_to' => ['nullable', 'string', 'max:50'],
            'entry_id' => ['nullable', 'integer'],
        ]);

        $inspection = trim((string) ($request->input('inspection_number')
            ?? $request->input('certificate_number')
            ?? ''));

        $data = [
            'pro_bowler_id' => ProBowler::query()->where('license_no', $licenseNo)->value('id'),
            'license_no' => $licenseNo,
            'approved_ball_id' => $request->input('approved_ball_id'),
            'serial_number' => $request->input('serial_number'),
            'registered_at' => $request->input('registered_at'),
        ];

        $data['inspection_number'] = ($inspection === '') ? null : $inspection;
        $data['expires_at'] = $data['inspection_number']
            ? Carbon::parse($data['registered_at'])->addYear()->subDay()->toDateString()
            : null;

        $registeredBall->update($data);

        $this->linkageService->sync($registeredBall->fresh());

        return $this->redirectAfterSave(
            $request,
            'registered_balls.index',
            '更新完了',
            $this->usbcWarningForBallId((int) $registeredBall->approved_ball_id)
        );
    }

    public function destroy(Request $request, RegisteredBall $registeredBall)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'この操作は許可されていません。');
        }

        $registeredBall->delete();

        return redirect()->route('registered_balls.index')->with('success', '削除完了');
    }

    private function applyRegisteredStatusFilter($query, string $status): void
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

    private function buildStatusMeta(?string $inspectionNumber, $expiresAt): array
    {
        if (! $inspectionNumber || ! $expiresAt) {
            return [
                'key' => 'provisional',
                'label' => '仮登録 / 検量証待ち',
                'badge' => 'warning',
                'days_to_expire' => null,
            ];
        }

        $expires = $expiresAt instanceof Carbon ? $expiresAt->copy() : Carbon::parse($expiresAt);
        $today = today();
        $days = $today->diffInDays($expires, false);

        if ($expires->lt($today)) {
            return [
                'key' => 'expired',
                'label' => '期限切れ',
                'badge' => 'danger',
                'days_to_expire' => $days,
            ];
        }

        if ($expires->lte($today->copy()->addDays(30))) {
            return [
                'key' => 'expiring_soon',
                'label' => '期限間近',
                'badge' => 'warning',
                'days_to_expire' => $days,
            ];
        }

        return [
            'key' => 'valid',
            'label' => '有効',
            'badge' => 'success',
            'days_to_expire' => $days,
        ];
    }

    private function logicalBallKey(int $proBowlerId, ?string $licenseNo, string $serialNumber): string
    {
        $ownerKey = $proBowlerId > 0
            ? 'id:'.$proBowlerId
            : 'license:'.mb_strtoupper(trim((string) $licenseNo));

        return $ownerKey.'|serial:'.mb_strtoupper(trim($serialNumber));
    }

    private function authorizeRegisteredBallAccess($user, RegisteredBall $registeredBall): void
    {
        if ($this->isPrivilegedUser($user)) {
            return;
        }

        $licenseNo = $this->resolveCurrentUserLicenseNo($user);
        if (! $licenseNo || $licenseNo !== $registeredBall->license_no) {
            abort(403, 'この本登録ボールは操作できません。');
        }
    }

    private function resolveCurrentUserLicenseNo($user): ?string
    {
        if (! $user) {
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
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->isEditor();
    }

    private function redirectAfterSave(
        Request $request,
        string $defaultRoute,
        string $message,
        ?string $warning = null
    ) {
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

    /**
     * @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection,2:\Illuminate\Support\Collection}
     */
    private function registrationCatalogData(): array
    {
        $balls = ApprovedBall::query()
            ->where('catalog_status', '<>', 'hidden')
            ->get()
            ->sortBy(
                fn (ApprovedBall $ball): string => mb_strtolower(
                    $ball->registration_brand.'|'.($ball->sort_name ?: $ball->name),
                    'UTF-8'
                ),
                SORT_NATURAL | SORT_FLAG_CASE
            )
            ->values();

        $brands = $balls
            ->pluck('registration_brand')
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $years = $balls
            ->pluck('release_year')
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return [$balls, $brands, $years];
    }
}

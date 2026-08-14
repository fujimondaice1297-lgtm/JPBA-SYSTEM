<?php

namespace App\Http\Controllers;

use App\Models\BallAnnualRegistration;
use App\Models\BallAnnualRegistrationHistory;
use App\Models\ProBowler;
use App\Models\UsedBall;
use App\Services\BallAnnualRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BallAnnualRegistrationController extends Controller
{
    public function __construct(
        private readonly BallAnnualRegistrationService $service
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizeStaff($request);

        $year = $this->resolveYear($request);
        $search = trim((string) $request->query('search', ''));

        $query = ProBowler::query()
            ->select(['id', 'license_no', 'name_kanji', 'name_kana'])
            ->whereHas('usedBalls')
            ->withCount('usedBalls');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('license_no', 'like', "%{$search}%")
                    ->orWhere('name_kanji', 'like', "%{$search}%")
                    ->orWhere('name_kana', 'like', "%{$search}%");
            });
        }

        $bowlers = $query
            ->orderBy('license_no')
            ->paginate(50)
            ->appends($request->query());

        $bowlerIds = $bowlers->getCollection()->pluck('id')->map(fn ($id) => (int) $id);
        $registrations = BallAnnualRegistration::query()
            ->withCount('usedBalls')
            ->whereIn('pro_bowler_id', $bowlerIds)
            ->where('registration_year', $year)
            ->orderByDesc('revision')
            ->get()
            ->groupBy('pro_bowler_id');

        $registrationRows = [];
        foreach ($bowlerIds as $bowlerId) {
            $rows = $registrations->get($bowlerId, collect());
            $working = $rows->first(fn (BallAnnualRegistration $registration) => in_array(
                $registration->status,
                [
                    BallAnnualRegistration::STATUS_DRAFT,
                    BallAnnualRegistration::STATUS_SUBMITTED,
                    BallAnnualRegistration::STATUS_RETURNED,
                ],
                true
            ));
            $approved = $rows->firstWhere('status', BallAnnualRegistration::STATUS_APPROVED);

            $registrationRows[$bowlerId] = [
                'current' => $working ?: $approved,
                'approved' => $approved,
            ];
        }

        return view('ball_annual_registrations.index', compact(
            'bowlers',
            'year',
            'search',
            'registrationRows'
        ));
    }

    public function edit(Request $request)
    {
        $year = $this->resolveYear($request);
        $proBowler = $this->resolveTargetBowler($request);

        if (!$proBowler) {
            return redirect()
                ->route('ball_annual_registrations.index', ['year' => $year])
                ->with('error', '年度申請する選手を選択してください。');
        }

        $usedBalls = UsedBall::query()
            ->with('approvedBall.catalogManufacturer')
            ->where('pro_bowler_id', $proBowler->id)
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->get();

        $workingRegistration = $this->service->workingRegistration((int) $proBowler->id, $year);
        $latestApproved = $this->service->latestApproved((int) $proBowler->id, $year);

        if ($workingRegistration) {
            $selectedIds = $workingRegistration->usedBalls()
                ->pluck('used_balls.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } elseif ($latestApproved) {
            $selectedIds = $latestApproved->usedBalls()
                ->pluck('used_balls.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } else {
            $selectedIds = $usedBalls->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $histories = BallAnnualRegistrationHistory::query()
            ->with(['registration', 'actor'])
            ->whereHas('registration', function ($q) use ($proBowler, $year) {
                $q->where('pro_bowler_id', $proBowler->id)
                    ->where('registration_year', $year);
            })
            ->latest('id')
            ->limit(20)
            ->get();

        $staffProxy = $this->isStaffUser($request->user());
        $canEdit = !$workingRegistration || in_array(
            $workingRegistration->status,
            [BallAnnualRegistration::STATUS_DRAFT, BallAnnualRegistration::STATUS_RETURNED],
            true
        );

        return view('ball_annual_registrations.edit', compact(
            'year',
            'proBowler',
            'usedBalls',
            'workingRegistration',
            'latestApproved',
            'selectedIds',
            'histories',
            'staffProxy',
            'canEdit'
        ));
    }

    public function saveDraft(Request $request)
    {
        return $this->saveRegistration($request, false);
    }

    public function submit(Request $request)
    {
        return $this->saveRegistration($request, true);
    }

    public function approve(Request $request, BallAnnualRegistration $registration)
    {
        $this->authorizeStaff($request);

        DB::transaction(function () use ($request, $registration) {
            /** @var BallAnnualRegistration $locked */
            $locked = BallAnnualRegistration::query()
                ->lockForUpdate()
                ->findOrFail($registration->id);

            if ($locked->status !== BallAnnualRegistration::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => '承認待ちの年度申請だけ承認できます。',
                ]);
            }

            $previousApprovals = BallAnnualRegistration::query()
                ->where('pro_bowler_id', $locked->pro_bowler_id)
                ->where('registration_year', $locked->registration_year)
                ->where('status', BallAnnualRegistration::STATUS_APPROVED)
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->get();

            foreach ($previousApprovals as $previous) {
                $previous->update(['status' => BallAnnualRegistration::STATUS_SUPERSEDED]);
                $this->service->recordHistory(
                    $previous,
                    'superseded',
                    BallAnnualRegistration::STATUS_APPROVED,
                    BallAnnualRegistration::STATUS_SUPERSEDED,
                    (int) $request->user()->id,
                    '新しい年度申請の承認により更新済みとなりました。'
                );
            }

            $locked->update([
                'status' => BallAnnualRegistration::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by_user_id' => $request->user()->id,
                'returned_at' => null,
                'returned_by_user_id' => null,
                'return_reason' => null,
            ]);

            $this->service->recordHistory(
                $locked,
                'approved',
                BallAnnualRegistration::STATUS_SUBMITTED,
                BallAnnualRegistration::STATUS_APPROVED,
                (int) $request->user()->id,
                null,
                ['ball_count' => $locked->usedBalls()->count()]
            );
        });

        return back()->with('success', '選手の年度ボール申請を一括承認しました。');
    }

    public function sendBack(Request $request, BallAnnualRegistration $registration)
    {
        $this->authorizeStaff($request);
        $validated = $request->validate([
            'return_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $registration, $validated) {
            /** @var BallAnnualRegistration $locked */
            $locked = BallAnnualRegistration::query()
                ->lockForUpdate()
                ->findOrFail($registration->id);

            if ($locked->status !== BallAnnualRegistration::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => '承認待ちの年度申請だけ差し戻せます。',
                ]);
            }

            $locked->update([
                'status' => BallAnnualRegistration::STATUS_RETURNED,
                'returned_at' => now(),
                'returned_by_user_id' => $request->user()->id,
                'return_reason' => $validated['return_reason'],
            ]);

            $this->service->recordHistory(
                $locked,
                'returned',
                BallAnnualRegistration::STATUS_SUBMITTED,
                BallAnnualRegistration::STATUS_RETURNED,
                (int) $request->user()->id,
                $validated['return_reason']
            );
        });

        return back()->with('success', '年度ボール申請を差し戻しました。');
    }

    private function saveRegistration(Request $request, bool $submit)
    {
        $year = $this->resolveYear($request);
        $proBowler = $this->resolveTargetBowler($request, true);

        $rules = [
            'used_ball_ids' => [$submit ? 'required' : 'nullable', 'array', $submit ? 'min:1' : 'min:0'],
            'used_ball_ids.*' => [
                'integer',
                Rule::exists('used_balls', 'id')
                    ->where(fn ($q) => $q->where('pro_bowler_id', $proBowler->id)),
            ],
        ];
        $validated = $request->validate($rules);
        $ballIds = collect($validated['used_ball_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $registration = DB::transaction(function () use ($request, $proBowler, $year, $ballIds, $submit) {
            $working = BallAnnualRegistration::query()
                ->where('pro_bowler_id', $proBowler->id)
                ->where('registration_year', $year)
                ->whereIn('status', [
                    BallAnnualRegistration::STATUS_DRAFT,
                    BallAnnualRegistration::STATUS_SUBMITTED,
                    BallAnnualRegistration::STATUS_RETURNED,
                ])
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();

            if ($working?->status === BallAnnualRegistration::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => '承認待ちの申請は編集できません。スタッフの承認または差戻しをお待ちください。',
                ]);
            }

            if (!$working) {
                $lastRevision = (int) BallAnnualRegistration::query()
                    ->where('pro_bowler_id', $proBowler->id)
                    ->where('registration_year', $year)
                    ->lockForUpdate()
                    ->max('revision');

                $working = BallAnnualRegistration::create([
                    'pro_bowler_id' => $proBowler->id,
                    'registration_year' => $year,
                    'revision' => $lastRevision + 1,
                    'status' => BallAnnualRegistration::STATUS_DRAFT,
                ]);
            }

            $fromStatus = (string) $working->status;
            $working->usedBalls()->sync($ballIds->all());

            if ($submit) {
                $working->update([
                    'status' => BallAnnualRegistration::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'submitted_by_user_id' => $request->user()->id,
                    'returned_at' => null,
                    'returned_by_user_id' => null,
                    'return_reason' => null,
                ]);
                $action = 'submitted';
                $toStatus = BallAnnualRegistration::STATUS_SUBMITTED;
            } else {
                $working->update([
                    'status' => BallAnnualRegistration::STATUS_DRAFT,
                    'returned_at' => null,
                    'returned_by_user_id' => null,
                    'return_reason' => null,
                ]);
                $action = 'draft_saved';
                $toStatus = BallAnnualRegistration::STATUS_DRAFT;
            }

            $this->service->recordHistory(
                $working,
                $action,
                $fromStatus,
                $toStatus,
                (int) $request->user()->id,
                null,
                ['ball_ids' => $ballIds->all(), 'ball_count' => $ballIds->count()]
            );

            return $working;
        });

        $message = $submit
            ? '年度ボール申請を提出しました。スタッフ承認後、大会登録で選択できます。'
            : '年度ボール申請を下書き保存しました。';

        return redirect()
            ->route('ball_annual_registrations.edit', [
                'year' => $year,
                'pro_bowler_id' => $registration->pro_bowler_id,
            ])
            ->with('success', $message);
    }

    private function resolveTargetBowler(Request $request, bool $required = false): ?ProBowler
    {
        $user = $request->user();
        $proBowlerId = 0;

        if ($this->isStaffUser($user)) {
            $proBowlerId = (int) $request->input('pro_bowler_id', 0);
        } else {
            $proBowlerId = (int) ($user?->pro_bowler_id ?? 0);
            if ($proBowlerId <= 0) {
                $licenseNo = trim((string) ($user?->pro_bowler_license_no ?? ''));
                if ($licenseNo !== '') {
                    $proBowlerId = (int) ProBowler::query()
                        ->where('license_no', $licenseNo)
                        ->value('id');
                }
            }
        }

        $proBowler = $proBowlerId > 0
            ? ProBowler::query()->find($proBowlerId)
            : null;

        if ($required && !$proBowler) {
            throw ValidationException::withMessages([
                'pro_bowler_id' => '年度申請する選手を選択してください。',
            ]);
        }

        return $proBowler;
    }

    private function resolveYear(Request $request): int
    {
        $year = (int) $request->input('year', now()->year);
        $maxYear = (int) now()->year + 1;

        if ($year < 2000 || $year > $maxYear) {
            throw ValidationException::withMessages([
                'year' => "年度は2000年から{$maxYear}年までで指定してください。",
            ]);
        }

        return $year;
    }

    private function authorizeStaff(Request $request): void
    {
        if (!$this->isStaffUser($request->user())) {
            abort(403, 'スタッフだけが年度申請を承認できます。');
        }
    }

    private function isStaffUser($user): bool
    {
        return (bool) ($user && ($user->isAdmin() || $user->isEditor()));
    }
}

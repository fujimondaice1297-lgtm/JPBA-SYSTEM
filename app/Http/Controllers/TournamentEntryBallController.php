<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\TournamentEntry;
use App\Models\UsedBall;
use App\Services\BallAnnualRegistrationService;
use App\Services\BallInspectionService;
use App\Services\RegisteredBallLinkageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentEntryBallController extends Controller
{
    private const DEFAULT_BALL_REGISTRATION_LIMIT = 12;

    public function __construct(
        private readonly BallAnnualRegistrationService $annualRegistrationService,
        private readonly BallInspectionService $inspectionService,
        private readonly RegisteredBallLinkageService $linkageService
    ) {}

    /**
     * 使用ボール選択画面（会員）
     * - 画面表示前に registered_balls -> used_balls を同期
     * - 有効期限内 もしくは 検量証待ち（expires_at NULL）を表示
     */
    public function edit(TournamentEntry $entry)
    {
        $entry->loadMissing(['tournament', 'bowler']);

        if ($guard = $this->guardEntryAccess($entry)) {
            return $guard;
        }

        $this->linkageService->syncForBowler((int) $entry->pro_bowler_id);

        $linkedIds = $entry->balls()->pluck('used_balls.id')->all();
        $registrationYear = $this->annualRegistrationService
            ->registrationYearForTournament($entry->tournament);
        $approvedAnnualRegistration = $this->annualRegistrationService
            ->latestApprovedOrCarryover((int) $entry->pro_bowler_id, $registrationYear);
        $approvedAnnualBallIds = $this->annualRegistrationService
            ->approvedUsedBallIds((int) $entry->pro_bowler_id, $registrationYear)
            ->all();
        $candidateIds = collect($approvedAnnualBallIds)
            ->merge($linkedIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $usedBalls = UsedBall::with('approvedBall')
            ->where('pro_bowler_id', $entry->pro_bowler_id)
            ->whereIn('id', $candidateIds)
            ->orderByRaw('case when inspection_number is null then 0 else 1 end asc')
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->get();

        $existingCount = count($linkedIds);
        $ballLimit = $this->resolveBallRegistrationLimit($entry);
        $remaining = max(0, $ballLimit - $existingCount);
        $inspectionRequired = (bool) ($entry->tournament->inspection_required ?? false);
        $inspectionReferenceDate = $this->inspectionService
            ->referenceDateForTournament($entry->tournament);
        $inspectionStatuses = $usedBalls->mapWithKeys(function (UsedBall $ball) use ($entry) {
            return [
                (int) $ball->id => [
                    'current' => $this->inspectionService->status(
                        $ball->inspection_number,
                        $ball->expires_at
                    ),
                    'tournament' => $this->inspectionService
                        ->tournamentEligibility($ball, $entry->tournament),
                ],
            ];
        })->all();
        $staffProxy = $this->isStaffUser(Auth::user());

        $summary = [
            'total' => $usedBalls->count(),
            'linked' => collect($usedBalls)->whereIn('id', $linkedIds)->count(),
            'available' => collect($usedBalls)->reject(fn ($ball) => in_array($ball->id, $linkedIds, true))->count(),
            'provisional' => collect($inspectionStatuses)->where('current.key', 'provisional')->count(),
            'expiring_soon' => collect($inspectionStatuses)->where('current.key', 'expiring_soon')->count(),
            'expired' => collect($inspectionStatuses)->where('current.key', 'expired')->count(),
            'valid' => collect($inspectionStatuses)->whereIn('current.key', ['valid', 'expiring_soon'])->count(),
            'tournament_ineligible' => $inspectionRequired
                ? collect($inspectionStatuses)->where('tournament.allowed', false)->count()
                : 0,
        ];

        $entryLicenseNo = $this->resolveEntryLicenseNo($entry);

        return view('member.entry_balls_edit', compact(
            'entry',
            'usedBalls',
            'linkedIds',
            'existingCount',
            'remaining',
            'ballLimit',
            'inspectionRequired',
            'inspectionReferenceDate',
            'inspectionStatuses',
            'summary',
            'entryLicenseNo',
            'staffProxy',
            'registrationYear',
            'approvedAnnualRegistration',
            'approvedAnnualBallIds'
        ));
    }

    /**
     * 速報・成績画面から開く、大会登録ボールの閲覧専用画面。
     */
    public function showForResults(Request $request, TournamentEntry $entry)
    {
        $entry->loadMissing(['tournament', 'bowler']);
        $isPublic = (int) $request->query('public', 0) === 1 || ! Auth::check();

        if ($isPublic && $entry->status !== 'entry') {
            abort(404);
        }

        // 公開閲覧では個人情報を取得しない。
        // シリアル番号・検量証番号・有効期限は管理画面だけで扱う。
        $balls = $entry->balls()
            ->select([
                'used_balls.id',
                'used_balls.approved_ball_id',
            ])
            ->with('approvedBall.catalogManufacturer')
            ->get()
            ->sortBy(function (UsedBall $ball): string {
                $brand = (string) ($ball->approvedBall?->registration_brand ?? '');
                $name = (string) ($ball->approvedBall?->name ?? '');

                return mb_strtolower($brand.'|'.$name.'|'.(string) $ball->id);
            })
            ->values();

        $portraitUrl = $entry->bowler?->public_photo_url;

        $requestedReturn = trim((string) $request->query('return', ''));
        $applicationRoot = rtrim(url('/'), '/');
        $defaultReturn = $isPublic
            ? route('public.tournaments.show', $entry->tournament_id)
            : route('member.dashboard');
        $returnUrl = $requestedReturn !== ''
            && ($requestedReturn === $applicationRoot || str_starts_with($requestedReturn, $applicationRoot.'/'))
                ? $requestedReturn
                : $defaultReturn;

        return view('scores.entry_balls_show', compact(
            'entry',
            'balls',
            'portraitUrl',
            'returnUrl',
            'isPublic'
        ));
    }

    /**
     * 大会で使用するボールをまとめて保存（追加・解除、大会設定上限）
     */
    public function bulkStore(Request $request, TournamentEntry $entry)
    {
        $entry->loadMissing('tournament');

        if ($guard = $this->guardEntryAccess($entry)) {
            return $guard;
        }

        $data = $request->validate([
            'used_ball_ids' => ['array'],
            'used_ball_ids.*' => ['integer', 'exists:used_balls,id'],
        ]);

        $targetIds = collect($data['used_ball_ids'] ?? [])->unique()->values();
        $already = $entry->balls()->pluck('used_balls.id')->all();
        $registrationYear = $this->annualRegistrationService
            ->registrationYearForTournament($entry->tournament);
        $approvedAnnualBallIds = $this->annualRegistrationService
            ->approvedUsedBallIds((int) $entry->pro_bowler_id, $registrationYear)
            ->all();
        $inspectionRequired = (bool) ($entry->tournament?->inspection_required ?? false);
        $ballLimit = $this->resolveBallRegistrationLimit($entry);
        if ($targetIds->count() > $ballLimit) {
            return back()->withErrors([
                'used_ball_ids' => '1大会で登録できるボールは最大'
                    .$ballLimit
                    .'個までです。（選択 '.$targetIds->count().' 個）',
            ]);
        }

        foreach ($targetIds as $ballId) {
            $usedBall = UsedBall::findOrFail($ballId);
            $isNewSelection = ! in_array((int) $ballId, array_map('intval', $already), true);

            if ((int) $usedBall->pro_bowler_id !== (int) $entry->pro_bowler_id) {
                return back()->withErrors([
                    'used_ball_ids' => "このエントリー選手のボールのみ登録できます。（ID: {$ballId}）",
                ]);
            }

            if (
                $isNewSelection
                && ! in_array((int) $ballId, array_map('intval', $approvedAnnualBallIds), true)
            ) {
                return back()->withErrors([
                    'used_ball_ids' => "{$registrationYear}年度のスタッフ承認を受けていないボールは追加できません。（SN: {$usedBall->serial_number}）",
                ]);
            }

            if ($isNewSelection && $inspectionRequired) {
                $inspectionEligibility = $this->inspectionService
                    ->tournamentEligibility($usedBall, $entry->tournament);

                if (! $inspectionEligibility['allowed']) {
                    return back()->withErrors([
                        'used_ball_ids' => 'この大会は検量証必須です。'
                            .$inspectionEligibility['message']
                            ."（SN: {$usedBall->serial_number}）",
                    ]);
                }
            }

        }

        $changes = $entry->balls()->sync($targetIds->all());
        $attachedCount = count($changes['attached'] ?? []);
        $detachedCount = count($changes['detached'] ?? []);

        return redirect()
            ->route('member.entries.balls.edit', $entry->id)
            ->with(
                'success',
                "大会使用ボールを更新しました。（登録 {$attachedCount} 個、解除 {$detachedCount} 個、現在 {$targetIds->count()} 個）"
            );
    }

    /**
     * （保持）単発API：テスト用途
     */
    public function store(Request $request, TournamentEntry $entry)
    {
        $entry->loadMissing('tournament');

        if ($guard = $this->guardEntryAccess($entry)) {
            return $guard;
        }

        $data = $request->validate([
            'used_ball_id' => ['required', 'integer', 'exists:used_balls,id'],
        ]);

        $usedBall = UsedBall::findOrFail($data['used_ball_id']);

        if (
            ! $this->isStaffUser(Auth::user())
            && Auth::check()
            && Auth::user()->pro_bowler_id
        ) {
            if ((int) $usedBall->pro_bowler_id !== (int) Auth::user()->pro_bowler_id) {
                return back()->withErrors(['used_ball_id' => '自分のボールのみ登録できます。']);
            }
        }

        if ((int) $usedBall->pro_bowler_id !== (int) $entry->pro_bowler_id) {
            return back()->withErrors(['used_ball_id' => 'このエントリーの選手のボールではありません。']);
        }

        $registrationYear = $this->annualRegistrationService
            ->registrationYearForTournament($entry->tournament);
        $approvedAnnualBallIds = $this->annualRegistrationService
            ->approvedUsedBallIds((int) $entry->pro_bowler_id, $registrationYear)
            ->all();

        if (! in_array((int) $usedBall->id, array_map('intval', $approvedAnnualBallIds), true)) {
            return back()->withErrors([
                'used_ball_id' => "{$registrationYear}年度のスタッフ承認を受けていないボールは登録できません。",
            ]);
        }

        $alreadyLinked = $entry->balls()->where('used_ball_id', $usedBall->id)->exists();
        $inspectionRequired = (bool) ($entry->tournament?->inspection_required ?? false);

        if (! $alreadyLinked && $inspectionRequired) {
            $inspectionEligibility = $this->inspectionService
                ->tournamentEligibility($usedBall, $entry->tournament);

            if (! $inspectionEligibility['allowed']) {
                return back()->withErrors([
                    'used_ball_id' => 'この大会は検量証必須です。'.$inspectionEligibility['message'],
                ]);
            }
        }

        if (! $alreadyLinked) {
            $ballLimit = $this->resolveBallRegistrationLimit($entry);
            if ($entry->balls()->count() >= $ballLimit) {
                return back()->withErrors([
                    'used_ball_id' => '1大会で登録できるボールは最大'
                        .$ballLimit
                        .'個までです。',
                ]);
            }
            $entry->balls()->attach($usedBall->id);
        }

        return back()->with('success', 'ボールを紐付けました。');
    }

    /**
     * 解除（会員は禁止。管理者のみ想定）
     */
    public function destroy(TournamentEntry $entry, UsedBall $usedBall)
    {
        $user = auth()->user();
        $isAdmin = $user && (method_exists($user, 'isAdmin') ? $user->isAdmin() : (bool) ($user->is_admin ?? false));

        if (! $isAdmin) {
            abort(403, 'この操作は許可されていません。');
        }

        $entry->balls()->detach($usedBall->id);

        return back()->with('success', 'ボールの紐付けを解除しました。');
    }

    private function guardEntryAccess(TournamentEntry $entry)
    {
        $user = Auth::user();
        $isStaff = $this->isStaffUser($user);
        $userProBowlerId = (int) (Auth::user()?->pro_bowler_id ?? 0);

        if (
            ! $isStaff
            && ($userProBowlerId <= 0 || $userProBowlerId !== (int) $entry->pro_bowler_id)
        ) {
            abort(403, '自分のエントリー以外は操作できません。');
        }

        if ($entry->status !== 'entry') {
            if ($isStaff) {
                return redirect()
                    ->route('tournaments.entries.index', $entry->tournament_id)
                    ->with('error', '参加登録済みの選手だけ大会使用ボールを操作できます。');
            }

            return redirect()
                ->route('tournament.entry.select')
                ->with('error', 'エントリー有効時のみ大会使用ボールを操作できます。');
        }

        if ($isStaff) {
            return null;
        }

        $bowler = ProBowler::query()->find($entry->pro_bowler_id);
        $eligibility = $this->resolveEntryEligibility($bowler, $entry->tournament()->first());

        if (! $eligibility['allowed']) {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', $eligibility['message']);
        }

        return null;
    }

    private function isStaffUser($user): bool
    {
        if (! $user) {
            return false;
        }

        $isAdmin = method_exists($user, 'isAdmin')
            ? $user->isAdmin()
            : (bool) ($user->is_admin ?? false);
        $isEditor = method_exists($user, 'isEditor')
            ? $user->isEditor()
            : (bool) ($user->is_editor ?? false);

        return $isAdmin || $isEditor;
    }

    private function resolveEntryEligibility(?ProBowler $bowler, ?\App\Models\Tournament $tournament = null): array
    {
        return app(\App\Services\TournamentEntryEligibilityService::class)->evaluate($bowler, $tournament);
    }

    private function memberClassLabel(?string $memberClass): string
    {
        return match ($memberClass) {
            'player' => '競技者',
            'pro_instructor' => 'プロインストラクター',
            'honorary_or_overseas' => '名誉プロ・海外プロ',
            'other' => 'その他',
            default => '-',
        };
    }

    private function isProvisionalBall(UsedBall $ball): bool
    {
        return blank($ball->inspection_number) || is_null($ball->expires_at);
    }

    private function resolveBallRegistrationLimit(TournamentEntry $entry): int
    {
        $configuredLimit = (int) ($entry->tournament?->ball_registration_limit ?? 0);

        return $configuredLimit > 0
            ? $configuredLimit
            : self::DEFAULT_BALL_REGISTRATION_LIMIT;
    }

    private function resolveEntryLicenseNo(TournamentEntry $entry): ?string
    {
        $licenseNo = trim((string) (optional($entry->bowler)->license_no ?? ''));
        if ($licenseNo !== '') {
            return $licenseNo;
        }

        $userLicenseNo = trim((string) (Auth::user()?->pro_bowler_license_no ?? ''));
        if ($userLicenseNo !== '') {
            return $userLicenseNo;
        }

        return ProBowler::query()
            ->whereKey($entry->pro_bowler_id)
            ->value('license_no');
    }
}

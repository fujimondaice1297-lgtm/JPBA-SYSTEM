<?php

namespace App\Http\Controllers;

use App\Models\TournamentEntry;
use App\Models\UsedBall;
use App\Models\RegisteredBall;
use App\Models\ProBowler;
use App\Services\BallAnnualRegistrationService;
use App\Services\BallInspectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentEntryBallController extends Controller
{
    private const DEFAULT_BALL_REGISTRATION_LIMIT = 12;

    public function __construct(
        private readonly BallAnnualRegistrationService $annualRegistrationService,
        private readonly BallInspectionService $inspectionService
    ) {
    }

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

        $this->syncFromRegisteredBalls((int) $entry->pro_bowler_id);

        $linkedIds = $entry->balls()->pluck('used_balls.id')->all();
        $registrationYear = $this->annualRegistrationService
            ->registrationYearForTournament($entry->tournament);
        $approvedAnnualRegistration = $this->annualRegistrationService
            ->latestApproved((int) $entry->pro_bowler_id, $registrationYear);
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
            ->orderByRaw("case when inspection_number is null then 0 else 1 end asc")
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
            'total'       => $usedBalls->count(),
            'linked'      => collect($usedBalls)->whereIn('id', $linkedIds)->count(),
            'available'   => collect($usedBalls)->reject(fn ($ball) => in_array($ball->id, $linkedIds, true))->count(),
            'provisional' => collect($inspectionStatuses)->where('current.key', 'provisional')->count(),
            'expiring_soon' => collect($inspectionStatuses)->where('current.key', 'expiring_soon')->count(),
            'expired'     => collect($inspectionStatuses)->where('current.key', 'expired')->count(),
            'valid'       => collect($inspectionStatuses)->whereIn('current.key', ['valid', 'expiring_soon'])->count(),
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
                $manufacturer = (string) (
                    $ball->approvedBall?->catalogManufacturer?->name
                    ?? $ball->approvedBall?->manufacturer
                    ?? ''
                );
                $name = (string) ($ball->approvedBall?->name ?? '');

                return mb_strtolower($manufacturer . '|' . $name . '|' . (string) $ball->id);
            })
            ->values();

        $portraitUrl = $entry->bowler?->public_photo_url;

        $requestedReturn = trim((string) $request->query('return', ''));
        $applicationRoot = rtrim(url('/'), '/');
        $returnUrl = $requestedReturn !== ''
            && ($requestedReturn === $applicationRoot || str_starts_with($requestedReturn, $applicationRoot . '/'))
                ? $requestedReturn
                : route('member.dashboard');
        $isPublic = (int) $request->query('public', 0) === 1;

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
            'used_ball_ids'   => ['array'],
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
            $isNewSelection = !in_array((int) $ballId, array_map('intval', $already), true);

            if ((int) $usedBall->pro_bowler_id !== (int) $entry->pro_bowler_id) {
                return back()->withErrors([
                    'used_ball_ids' => "このエントリー選手のボールのみ登録できます。（ID: {$ballId}）",
                ]);
            }

            if (
                $isNewSelection
                && !in_array((int) $ballId, array_map('intval', $approvedAnnualBallIds), true)
            ) {
                return back()->withErrors([
                    'used_ball_ids' => "{$registrationYear}年度のスタッフ承認を受けていないボールは追加できません。（SN: {$usedBall->serial_number}）",
                ]);
            }

            if ($isNewSelection && $inspectionRequired) {
                $inspectionEligibility = $this->inspectionService
                    ->tournamentEligibility($usedBall, $entry->tournament);

                if (!$inspectionEligibility['allowed']) {
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
            !$this->isStaffUser(Auth::user())
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

        if (!in_array((int) $usedBall->id, array_map('intval', $approvedAnnualBallIds), true)) {
            return back()->withErrors([
                'used_ball_id' => "{$registrationYear}年度のスタッフ承認を受けていないボールは登録できません。",
            ]);
        }

        $alreadyLinked = $entry->balls()->where('used_ball_id', $usedBall->id)->exists();
        $inspectionRequired = (bool) ($entry->tournament?->inspection_required ?? false);

        if (!$alreadyLinked && $inspectionRequired) {
            $inspectionEligibility = $this->inspectionService
                ->tournamentEligibility($usedBall, $entry->tournament);

            if (!$inspectionEligibility['allowed']) {
                return back()->withErrors([
                    'used_ball_id' => 'この大会は検量証必須です。'.$inspectionEligibility['message'],
                ]);
            }
        }

        if (!$alreadyLinked) {
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

        if (!$isAdmin) {
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
            !$isStaff
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

        if (!$eligibility['allowed']) {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', $eligibility['message']);
        }

        return null;
    }

    private function isStaffUser($user): bool
    {
        if (!$user) {
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

    /**
     * registered_balls -> used_balls 同期
     * - RegisteredBall は license_no ベース
     * - UsedBall は pro_bowler_id を要求するので、対応する ProBowler を解決して保存
     * - serial_number が同じものは「スキップ」ではなく更新して、本登録側の修正を反映する
     * - expires_at は RegisteredBall 側のロジックに従う（NULL=仮登録OK）
     */
    private function syncFromRegisteredBalls(int $proBowlerId): void
    {
        $pro = ProBowler::find($proBowlerId);
        if (!$pro || empty($pro->license_no)) {
            return;
        }

        $registered = RegisteredBall::where('license_no', $pro->license_no)->get();
        if ($registered->isEmpty()) {
            return;
        }

        $existingUsedBalls = UsedBall::where('pro_bowler_id', $pro->id)
            ->get()
            ->keyBy(fn ($ball) => mb_strtoupper((string) $ball->serial_number));

        foreach ($registered as $rb) {
            $serialKey = mb_strtoupper((string) $rb->serial_number);

            $payload = [
                'approved_ball_id'  => $rb->approved_ball_id,
                'serial_number'     => $rb->serial_number,
                'inspection_number' => $rb->inspection_number,
                'registered_at'     => $rb->registered_at,
                'expires_at'        => $rb->expires_at,
            ];

            if ($existingUsedBalls->has($serialKey)) {
                /** @var \App\Models\UsedBall $existing */
                $existing = $existingUsedBalls->get($serialKey);

                $needsUpdate =
                    (int) $existing->approved_ball_id !== (int) $rb->approved_ball_id ||
                    (string) ($existing->inspection_number ?? '') !== (string) ($rb->inspection_number ?? '') ||
                    optional($existing->registered_at)->format('Y-m-d') !== optional($rb->registered_at)->format('Y-m-d') ||
                    optional($existing->expires_at)->format('Y-m-d') !== optional($rb->expires_at)->format('Y-m-d');

                if ($needsUpdate) {
                    $existing->update($payload);
                }

                continue;
            }

            $created = UsedBall::create(array_merge(
                ['pro_bowler_id' => $pro->id],
                $payload
            ));

            $existingUsedBalls->put($serialKey, $created);
        }
    }
}

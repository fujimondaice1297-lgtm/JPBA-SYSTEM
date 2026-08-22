<?php

namespace App\Console\Commands;

use App\Http\Middleware\RoleMiddleware;
use App\Models\ApprovedBall;
use App\Models\BallAnnualRegistration;
use App\Models\ProBowler;
use App\Models\RegisteredBall;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Services\BallAnnualRegistrationService;
use App\Services\RegisteredBallLinkageService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AuditPlayerBallLinkage extends Command
{
    protected $signature = 'jpba:audit-player-ball-linkage
        {--smoke : 一時データでアカウントから大会登録まで確認し、最後に全件ロールバックする}';

    protected $description = '選手アカウント・マイボール・年度申請・大会登録の所有者結線を監査する';

    public function handle(
        RegisteredBallLinkageService $linkageService,
        BallAnnualRegistrationService $annualRegistrationService
    ): int {
        $summary = $this->summary();
        $this->table(['監査項目', '件数'], collect($summary)->map(
            fn ($count, $label) => [$label, $count]
        )->values()->all());

        $mismatches = [
            '選手IDなし会員アカウント' => $summary['選手IDなし会員アカウント'],
            '選手IDとライセンス不一致アカウント' => $summary['選手IDとライセンス不一致アカウント'],
            '年度申請と所有者が違うボール' => $summary['年度申請と所有者が違うボール'],
            '大会エントリーと所有者が違うボール' => $summary['大会エントリーと所有者が違うボール'],
        ];

        foreach ($mismatches as $label => $count) {
            if ($count > 0) {
                $this->error("NG: {$label} が {$count}件あります。");
            }
        }

        if (array_sum($mismatches) > 0) {
            return self::FAILURE;
        }

        $this->info('OK: 現存データの所有者不一致はありません。');

        if (! $this->option('smoke')) {
            return self::SUCCESS;
        }

        return $this->runRollbackSmoke($linkageService, $annualRegistrationService);
    }

    private function summary(): array
    {
        return [
            'users総数' => User::query()->count(),
            '選手会員アカウント' => User::query()->whereIn('role', ['member', 'bowler'])->count(),
            '選手IDなし会員アカウント' => User::query()
                ->whereIn('role', ['member', 'bowler'])
                ->whereNull('pro_bowler_id')
                ->count(),
            '選手IDとライセンス不一致アカウント' => DB::table('users as users')
                ->join('pro_bowlers as bowlers', 'bowlers.id', '=', 'users.pro_bowler_id')
                ->whereNotNull('users.pro_bowler_license_no')
                ->whereColumn('users.pro_bowler_license_no', '<>', 'bowlers.license_no')
                ->count(),
            '本登録ボール' => RegisteredBall::query()->count(),
            '選手ID付き本登録ボール' => RegisteredBall::query()->whereNotNull('pro_bowler_id')->count(),
            '年度申請と所有者が違うボール' => DB::table('ball_annual_registration_items as items')
                ->join('ball_annual_registrations as registrations', 'registrations.id', '=', 'items.registration_id')
                ->join('used_balls as balls', 'balls.id', '=', 'items.used_ball_id')
                ->whereColumn('registrations.pro_bowler_id', '<>', 'balls.pro_bowler_id')
                ->count(),
            '大会エントリーと所有者が違うボール' => DB::table('tournament_entry_balls as links')
                ->join('tournament_entries as entries', 'entries.id', '=', 'links.tournament_entry_id')
                ->join('used_balls as balls', 'balls.id', '=', 'links.used_ball_id')
                ->whereColumn('entries.pro_bowler_id', '<>', 'balls.pro_bowler_id')
                ->count(),
        ];
    }

    private function runRollbackSmoke(
        RegisteredBallLinkageService $linkageService,
        BallAnnualRegistrationService $annualRegistrationService
    ): int {
        $before = $this->mutableCounts();
        DB::beginTransaction();

        try {
            $bowler = ProBowler::query()->whereNotNull('license_no')->orderBy('id')->firstOrFail();
            $approvedBall = ApprovedBall::query()->orderBy('id')->firstOrFail();
            $tournament = Tournament::query()
                ->whereDoesntHave('entries', fn ($query) => $query->where('pro_bowler_id', $bowler->id))
                ->orderByDesc('start_date')
                ->firstOrFail();
            $token = Str::lower(Str::random(20));

            $member = User::create([
                'name' => '連動監査用（自動削除）',
                'email' => "linkage-{$token}@example.invalid",
                'password' => Hash::make(Str::random(48)),
                'role' => 'member',
                'is_admin' => false,
                'pro_bowler_id' => $bowler->id,
                'pro_bowler_license_no' => $bowler->license_no,
                'license_no' => $bowler->license_no,
            ]);

            Auth::setUser($member);
            $request = Request::create('/member', 'GET');
            $request->setUserResolver(fn () => $member);
            $middlewareResponse = app(RoleMiddleware::class)->handle(
                $request,
                fn () => response('OK'),
                'member'
            );
            $this->assertSame(200, $middlewareResponse->getStatusCode(), '会員権限');
            $this->assertSame((int) $bowler->id, (int) $member->proBowler?->id, 'アカウント→選手');

            $registeredBall = RegisteredBall::create([
                'pro_bowler_id' => $bowler->id,
                'license_no' => $bowler->license_no,
                'approved_ball_id' => $approvedBall->id,
                'serial_number' => "LINKAGE-{$token}",
                'registered_at' => now()->toDateString(),
            ]);
            $usedBall = $linkageService->sync($registeredBall);
            if (! $usedBall) {
                throw new RuntimeException('本登録ボール→マイボールの同期に失敗しました。');
            }
            $this->assertSame((int) $bowler->id, (int) $usedBall->pro_bowler_id, 'マイボール所有者');

            $registrationYear = $annualRegistrationService->registrationYearForTournament($tournament);
            $revision = (int) BallAnnualRegistration::query()
                ->where('pro_bowler_id', $bowler->id)
                ->where('registration_year', $registrationYear)
                ->max('revision') + 1;
            $registration = BallAnnualRegistration::create([
                'pro_bowler_id' => $bowler->id,
                'registration_year' => $registrationYear,
                'revision' => $revision,
                'status' => BallAnnualRegistration::STATUS_APPROVED,
                'submitted_at' => now(),
                'submitted_by_user_id' => $member->id,
                'approved_at' => now(),
                'approved_by_user_id' => $member->id,
            ]);
            $registration->usedBalls()->attach($usedBall->id);
            $this->assertTrue(
                $annualRegistrationService->approvedUsedBallIds((int) $bowler->id, $registrationYear)
                    ->contains((int) $usedBall->id),
                '年度承認'
            );

            $entry = TournamentEntry::create([
                'tournament_id' => $tournament->id,
                'pro_bowler_id' => $bowler->id,
                'status' => 'entry',
            ]);
            $entry->balls()->attach($usedBall->id);
            $this->assertTrue($entry->balls()->whereKey($usedBall->id)->exists(), '大会登録');

            DB::rollBack();
            Auth::logout();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Auth::logout();
            $this->error('NG: ロールバック試験に失敗しました。'.$exception->getMessage());

            return self::FAILURE;
        }

        if ($before !== $this->mutableCounts()) {
            $this->error('NG: ロールバック後の件数が試験前と一致しません。');

            return self::FAILURE;
        }

        $this->info('OK: 選手ログイン権限→選手ID→マイボール→年度承認→大会登録を確認しました。');
        $this->info('OK: 試験データはすべてロールバックされ、DB件数は試験前と一致しています。');

        return self::SUCCESS;
    }

    private function mutableCounts(): array
    {
        return [
            'users' => DB::table('users')->count(),
            'registered_balls' => DB::table('registered_balls')->count(),
            'used_balls' => DB::table('used_balls')->count(),
            'ball_annual_registrations' => DB::table('ball_annual_registrations')->count(),
            'ball_annual_registration_items' => DB::table('ball_annual_registration_items')->count(),
            'tournament_entries' => DB::table('tournament_entries')->count(),
            'tournament_entry_balls' => DB::table('tournament_entry_balls')->count(),
        ];
    }

    private function assertSame(int $expected, int $actual, string $label): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException("{$label}が一致しません。（期待 {$expected} / 実際 {$actual}）");
        }
    }

    private function assertTrue(bool $condition, string $label): void
    {
        if (! $condition) {
            throw new RuntimeException("{$label}を確認できませんでした。");
        }
    }
}

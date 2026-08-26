<?php

use App\Models\ProBowler;
use App\Models\User;
use App\Models\UserAccountStatusLog;
use App\Services\PlayerAccountService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => User::STATUS_ACTIVE,
    ]);
    $this->bowler = ProBowler::query()->create([
        'license_no' => 'M00001219',
        'name_kanji' => '川添奨太',
        'sex' => 1,
        'email' => 'player@example.com',
        'is_active' => true,
    ]);
});

test('admin can issue a single linked account and send its setup link', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->get(route('admin.player_accounts.show', $this->bowler))
        ->assertOk()
        ->assertSee('選手アカウント管理')
        ->assertSee('未発行');

    $this->actingAs($this->admin)
        ->post(route('admin.player_accounts.issue', $this->bowler))
        ->assertRedirect();

    $account = User::query()->where('pro_bowler_id', $this->bowler->id)->firstOrFail();
    expect($account->role)->toBe('member')
        ->and($account->pro_bowler_license_no)->toBe('M00001219')
        ->and($account->account_status)->toBe(User::STATUS_ACTIVE)
        ->and($account->password_set_at)->toBeNull()
        ->and(UserAccountStatusLog::query()->where('user_id', $account->id)->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post(route('admin.player_accounts.setup_link', $this->bowler))
        ->assertRedirect();

    Notification::assertSentTo($account, ResetPassword::class);
    expect($account->refresh()->setup_link_sent_at)->not->toBeNull();
});

test('dry run and repeat issuance never create duplicate accounts', function () {
    $service = app(PlayerAccountService::class);

    $preview = $service->issue($this->bowler, dryRun: true);
    expect($preview['status'])->toBe('created')
        ->and(User::query()->where('role', 'member')->count())->toBe(0);

    $first = $service->issue($this->bowler, changedBy: $this->admin->id);
    $second = $service->issue($this->bowler, changedBy: $this->admin->id);

    expect($first['status'])->toBe('created')
        ->and($second['status'])->toBe('updated')
        ->and(User::query()->where('pro_bowler_id', $this->bowler->id)->count())->toBe(1);
});

test('repeat issuance synchronizes the latest profile email without touching admin accounts', function () {
    $service = app(PlayerAccountService::class);
    $account = $service->issue($this->bowler, changedBy: $this->admin->id)['user'];

    $this->bowler->forceFill(['email' => 'updated-player@example.com'])->save();
    $result = $service->issue($this->bowler, changedBy: $this->admin->id);

    expect($result['status'])->toBe('updated')
        ->and($account->refresh()->email)->toBe('updated-player@example.com');

    $account->forceFill(['role' => 'admin'])->save();
    $this->bowler->forceFill(['email' => 'blocked-update@example.com'])->save();
    $blocked = $service->issue($this->bowler, changedBy: $this->admin->id);

    expect($blocked['status'])->toBe('skipped')
        ->and($account->refresh()->email)->toBe('updated-player@example.com')
        ->and($account->role)->toBe('admin');
});

test('suspension revokes access while preserving the linked account and history', function () {
    $account = app(PlayerAccountService::class)->issue(
        $this->bowler,
        changedBy: $this->admin->id,
    )['user'];
    $account->forceFill(['password' => Hash::make('password')])->save();

    $this->actingAs($this->admin)
        ->post(route('admin.player_accounts.status', $this->bowler), [
            'account_status' => User::STATUS_SUSPENDED,
            'reason' => '段階導入テスト停止',
        ])
        ->assertRedirect();

    $account->refresh();
    expect($account->account_status)->toBe(User::STATUS_SUSPENDED)
        ->and($account->suspended_at)->not->toBeNull()
        ->and($account->account_status_note)->toBe('段階導入テスト停止')
        ->and(UserAccountStatusLog::query()->where('user_id', $account->id)->count())->toBe(2);

    auth()->logout();
    $this->post('/login', [
        'login' => 'M00001219',
        'password' => 'password',
    ])->assertSessionHasErrors('login');
    $this->assertGuest();

    expect(User::query()->whereKey($account->id)->exists())->toBeTrue()
        ->and($account->pro_bowler_id)->toBe($this->bowler->id);
});

test('forgot password never creates an unissued player account', function () {
    Notification::fake();
    $before = User::query()->count();

    $this->post('/forgot-password', ['email' => $this->bowler->email])
        ->assertSessionHas('status');

    expect(User::query()->count())->toBe($before)
        ->and(User::query()->where('pro_bowler_id', $this->bowler->id)->exists())->toBeFalse();
    Notification::assertNothingSent();
});

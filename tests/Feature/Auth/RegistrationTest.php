<?php

use App\Models\ProBowler;
use App\Models\User;
use App\Models\UserAccountStatusLog;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $bowler = ProBowler::query()->create([
        'license_no' => 'M00001219',
        'name_kanji' => '川添奨太',
        'sex' => 1,
        'email' => 'test@example.com',
    ]);

    $response = $this->post('/register', [
        'license_no' => 'M00001219',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('member.dashboard', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    expect($user->role)->toBe('member')
        ->and($user->pro_bowler_id)->toBe($bowler->id)
        ->and($user->pro_bowler_license_no)->toBe('M00001219')
        ->and($user->account_status)->toBe(User::STATUS_ACTIVE)
        ->and($user->password_set_at)->not->toBeNull()
        ->and(UserAccountStatusLog::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($bowler->refresh()->password_change_status)->toBe(0);
});

test('registration rejects a license and email mismatch', function () {
    ProBowler::query()->create([
        'license_no' => 'M00001219',
        'name_kanji' => '川添奨太',
        'sex' => 1,
        'email' => 'registered@example.com',
    ]);

    $response = $this->from('/register')->post('/register', [
        'license_no' => 'M00001219',
        'email' => 'different@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect('/register')->assertSessionHasErrors('license_no');
});

<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'login' => strtoupper($user->email),
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('member.dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login', absolute: false));
});

test('users can authenticate using their professional license number', function () {
    $user = User::factory()->create([
        'pro_bowler_license_no' => 'M00001219',
        'role' => 'member',
    ]);

    $response = $this->post('/login', [
        'login' => 'm00001219',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('member.dashboard', absolute: false));
});

test('suspended users cannot authenticate', function () {
    $user = User::factory()->create([
        'account_status' => User::STATUS_SUSPENDED,
        'suspended_at' => now(),
    ]);

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

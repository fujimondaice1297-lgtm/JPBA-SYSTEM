<?php

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertStatus(200)
        ->assertHeader('Pragma', 'no-cache');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('expired login form returns to a fresh login screen instead of showing 419', function () {
    $request = Request::create('/login', 'POST');
    $request->setLaravelSession(app('session')->driver());

    $response = app(ExceptionHandler::class)->render(
        $request,
        new TokenMismatchException('CSRF token mismatch.')
    );

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route('login'));
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

test('member login ignores a stale intended management url and starts at my page', function () {
    $user = User::factory()->create([
        'role' => 'member',
    ]);

    $response = $this
        ->withSession(['url.intended' => route('management.home', absolute: false)])
        ->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('member.dashboard', absolute: false));
});

test('admin login starts at management home', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('management.home', absolute: false));
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

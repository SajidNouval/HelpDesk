<?php

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Providers\RouteServiceProvider;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('staff users can authenticate using the login screen', function () {
    $this->withoutMiddleware([VerifyCsrfToken::class]);

    $user = User::factory()->staff()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('staff.dashboard'));
});

test('admin users can authenticate using the login screen', function () {
    $this->withoutMiddleware([VerifyCsrfToken::class]);

    $admin = User::factory()->admin()->create();

    $response = $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.dashboard'));
});

test('users can not authenticate with invalid password', function () {
    $this->withoutMiddleware([VerifyCsrfToken::class]);

    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $this->withoutMiddleware([VerifyCsrfToken::class]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

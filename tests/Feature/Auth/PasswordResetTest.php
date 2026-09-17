<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/admin/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/admin/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/admin/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/admin/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/admin/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/admin/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('el token de reseteo es de un solo uso (HIG-26)', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/admin/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $payload = [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->post('/admin/reset-password', $payload)->assertSessionHasNoErrors();

        // Reutilizar el mismo token se rechaza.
        $this->post('/admin/reset-password', $payload)->assertSessionHasErrors('email');

        return true;
    });
});

test('resetear la contraseña no reactiva un usuario desactivado (HIG-26)', function () {
    Notification::fake();

    $user = User::factory()->create(['is_active' => false]);

    $this->post('/admin/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/admin/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect($user->refresh()->is_active)->toBeFalse();

    $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertGuest();
});

test('el reset rechaza menos de 8 caracteres (HIG-31)', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/admin/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/admin/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'corta7',
            'password_confirmation' => 'corta7',
        ])->assertSessionHasErrors('password');

        return true;
    });

    $this->assertTrue(Hash::check('password', $user->refresh()->password));
});

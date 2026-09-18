<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/admin/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/admin/logout');

    $this->assertGuest();
    $response->assertRedirect('/admin/login');
});

test('el sexto intento fallido se bloquea con throttle (HIG-25)', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    $this->assertGuest();

    $response = $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong-password']);
    $response->assertSessionHasErrors('email');

    // El mensaje es exactamente el del throttle (no el de credenciales inválidas),
    // reconstruido con los mismos segundos que trae, independiente del idioma.
    $texto = implode(' ', session('errors')->get('email'));
    preg_match('/(\d+)/', $texto, $coincidencias);
    $segundos = (int) $coincidencias[1];
    expect($texto)->toBe(trans('auth.throttle', ['seconds' => $segundos, 'minutes' => (int) ceil($segundos / 60)]));

    // Ni la contraseña correcta entra mientras dura el bloqueo.
    $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertGuest();
});

test('el contador del throttle se limpia tras un login válido (HIG-25)', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 4; $i++) {
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticated();
    $this->post('/admin/logout');

    // Sin limpieza, estos 4 fallidos se sumarían a los 4 anteriores y el 5º bloquearía.
    for ($i = 0; $i < 4; $i++) {
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticated();
});

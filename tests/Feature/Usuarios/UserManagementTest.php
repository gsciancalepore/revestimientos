<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('guests are redirected to the login page when accessing the users area', function () {
    $this->get('/admin/usuarios')->assertRedirect('/admin/login');
});

test('only admins can view the users index', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get('/admin/usuarios')->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('only admins can view the create user page', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get('/admin/usuarios/create')->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('only admins can view the edit user page', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($actor)->get(route('usuarios.edit', $target))->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('admins can view the users index with the existing users', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    User::factory()->withRole(UserRole::Vendedor)->create(['name' => 'María Vendedora']);

    $this->actingAs($admin)
        ->get('/admin/usuarios')
        ->assertOk()
        ->assertSee('María Vendedora');
});

test('admins can view the create user page', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->get('/admin/usuarios/create')->assertOk();
});

test('admins can create a user with a role', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $response = $this->actingAs($admin)->post('/admin/usuarios', [
        'name' => 'Juan Nuevo',
        'email' => 'juan@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::Vendedor->value,
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', absolute: false));

    $user = User::where('email', 'juan@example.com')->firstOrFail();

    $this->assertSame('Juan Nuevo', $user->name);
    $this->assertTrue(Hash::check('password123', $user->password));
    $this->assertSame(UserRole::Vendedor, $user->role());
});

test('create user rejects a duplicate email', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    User::factory()->create(['email' => 'existente@example.com']);

    $this->actingAs($admin)->post('/admin/usuarios', [
        'name' => 'Juan Nuevo',
        'email' => 'existente@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasErrors('email');
});

test('create user rejects a password shorter than 8 characters', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->post('/admin/usuarios', [
        'name' => 'Juan Nuevo',
        'email' => 'juan@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasErrors('password');
});

test('create user rejects a password confirmation mismatch', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->post('/admin/usuarios', [
        'name' => 'Juan Nuevo',
        'email' => 'juan@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password456',
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasErrors('password');
});

test('create user rejects an invalid role', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->post('/admin/usuarios', [
        'name' => 'Juan Nuevo',
        'email' => 'juan@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'gerente',
    ])->assertSessionHasErrors('role');
});

test('admins can update a user name and email', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $response = $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => 'Nombre Actualizado',
        'email' => 'actualizado@example.com',
        'role' => UserRole::Vendedor->value,
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', absolute: false));

    $target->refresh();

    $this->assertSame('Nombre Actualizado', $target->name);
    $this->assertSame('actualizado@example.com', $target->email);
});

test('only admins can update users', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($actor)->patch(route('usuarios.update', $target), [
        'name' => 'Intento',
        'email' => $target->email,
        'role' => UserRole::Vendedor->value,
    ])->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('update user rejects an email used by another user', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();
    $other = User::factory()->create(['email' => 'ocupado@example.com']);

    $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => $target->name,
        'email' => $other->email,
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasErrors('email');
});

test('update user allows keeping the own email', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $response = $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Vendedor->value,
    ]);

    $response->assertSessionHasNoErrors();
});

test('admins can reset a user password from the update form', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Vendedor->value,
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
    ])->assertSessionHasNoErrors();

    $target->refresh();

    $this->assertTrue(Hash::check('nueva-clave-123', $target->password));
});

test('updating a user without a password keeps the current password', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => 'Nombre Actualizado',
        'email' => $target->email,
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasNoErrors();

    $target->refresh();

    $this->assertTrue(Hash::check('password', $target->password));
});

test('admins can deactivate a user', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $response = $this->actingAs($admin)->patch(route('usuarios.toggle-active', $target));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', absolute: false));

    $this->assertFalse($target->refresh()->is_active);
});

test('admins can reactivate a user', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create(['is_active' => false]);

    $response = $this->actingAs($admin)->patch(route('usuarios.toggle-active', $target));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('usuarios.index', absolute: false));

    $this->assertTrue($target->refresh()->is_active);
});

test('only admins can toggle the active state of a user', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($actor)->patch(route('usuarios.toggle-active', $target))->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('a deactivated user cannot log in and receives the same generic error', function () {
    $user = User::factory()->withRole(UserRole::Vendedor)->create(['is_active' => false]);

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->assertSame(trans('auth.failed'), session('errors')->first('email'));
});

test('the admin cannot deactivate himself', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $response = $this->actingAs($admin)->patch(route('usuarios.toggle-active', $admin));

    $response
        ->assertRedirect(route('usuarios.index', absolute: false))
        ->assertSessionHasErrors('active');

    $this->assertTrue($admin->refresh()->is_active);
    $this->assertDatabaseCount('audit_logs', 0);
});

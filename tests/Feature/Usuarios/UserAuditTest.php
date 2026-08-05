<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('creating a user records a user.created audit entry with role, actor, ip and user agent', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->post('/admin/usuarios', [
        'name' => 'Juan Nuevo',
        'email' => 'juan@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasNoErrors();

    $audit = AuditLog::where('action', 'user.created')->firstOrFail();
    $user = User::where('email', 'juan@example.com')->firstOrFail();

    $this->assertSame($admin->getKey(), $audit->actor_id);
    $this->assertSame(User::class, $audit->subject_type);
    $this->assertSame($user->getKey(), $audit->subject_id);
    $this->assertSame('vendedor', $audit->payload['role']);
    $this->assertSame('127.0.0.1', $audit->ip_address);
    $this->assertNotNull($audit->user_agent);
});

test('updating a user records a user.updated audit entry with only the changed attributes', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => 'Nombre Actualizado',
        'email' => $target->email,
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasNoErrors();

    $audit = AuditLog::where('action', 'user.updated')->firstOrFail();

    $this->assertSame($admin->getKey(), $audit->actor_id);
    $this->assertSame(['name'], $audit->payload['changes']);
});

test('updating a user without changes does not record an audit entry', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('audit_logs', 0);
});

test('changing a user role records a user.role_changed audit entry with previous and new role', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Deposito->value,
    ])->assertSessionHasNoErrors();

    $audit = AuditLog::where('action', 'user.role_changed')->firstOrFail();

    $this->assertSame('vendedor', $audit->payload['previous_role']);
    $this->assertSame('deposito', $audit->payload['new_role']);
    $this->assertDatabaseCount('audit_logs', 1);
});

test('updating a user without changing the role does not record a role change', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($admin)->patch(route('usuarios.update', $target), [
        'name' => 'Nombre Actualizado',
        'email' => $target->email,
        'role' => UserRole::Vendedor->value,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'user.role_changed',
    ]);
});

test('deactivating a user records a user.deactivated audit entry', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($admin)->patch(route('usuarios.toggle-active', $target));

    $audit = AuditLog::where('action', 'user.deactivated')->firstOrFail();

    $this->assertSame($admin->getKey(), $audit->actor_id);
    $this->assertSame($target->getKey(), $audit->subject_id);
});

test('reactivating a user records a user.reactivated audit entry', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = User::factory()->withRole(UserRole::Vendedor)->create(['is_active' => false]);

    $this->actingAs($admin)->patch(route('usuarios.toggle-active', $target));

    $audit = AuditLog::where('action', 'user.reactivated')->firstOrFail();

    $this->assertSame($admin->getKey(), $audit->actor_id);
    $this->assertSame($target->getKey(), $audit->subject_id);
});

test('a failed self-deactivation attempt does not record an audit entry', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->patch(route('usuarios.toggle-active', $admin));

    $this->assertDatabaseCount('audit_logs', 0);
});

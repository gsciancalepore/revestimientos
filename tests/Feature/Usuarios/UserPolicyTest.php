<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->policy = new UserPolicy;
});

function invocarPolicy(UserPolicy $policy, string $habilidad, User $actor, User $target): bool
{
    return in_array($habilidad, ['viewAny', 'create'], true)
        ? $policy->{$habilidad}($actor)
        : $policy->{$habilidad}($actor, $target);
}

test('UserPolicy autoriza solo al admin, sin pasar por HTTP ni middleware (HIG-24)', function (string $habilidad, UserRole $rol, bool $esperado) {
    $actor = User::factory()->withRole($rol)->create();
    $target = User::factory()->create();

    expect(invocarPolicy($this->policy, $habilidad, $actor, $target))->toBe($esperado);
})->with([
    'viewAny admin' => ['viewAny', UserRole::Admin, true],
    'viewAny vendedor' => ['viewAny', UserRole::Vendedor, false],
    'viewAny depósito' => ['viewAny', UserRole::Deposito, false],
    'create admin' => ['create', UserRole::Admin, true],
    'create vendedor' => ['create', UserRole::Vendedor, false],
    'create depósito' => ['create', UserRole::Deposito, false],
    'update admin' => ['update', UserRole::Admin, true],
    'update vendedor' => ['update', UserRole::Vendedor, false],
    'update depósito' => ['update', UserRole::Deposito, false],
    'toggleActive admin' => ['toggleActive', UserRole::Admin, true],
    'toggleActive vendedor' => ['toggleActive', UserRole::Vendedor, false],
    'toggleActive depósito' => ['toggleActive', UserRole::Deposito, false],
]);

test('UserPolicy niega todo al usuario sin rol (HIG-24)', function (string $habilidad) {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    expect(invocarPolicy($this->policy, $habilidad, $actor, $target))->toBeFalse();
})->with([
    'viewAny' => ['viewAny'],
    'create' => ['create'],
    'update' => ['update'],
    'toggleActive' => ['toggleActive'],
]);

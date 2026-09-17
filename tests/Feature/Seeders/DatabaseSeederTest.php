<?php

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Permission\Models\Role;

test('DatabaseSeeder crea 3 roles, 1 admin activo y 4 categorías (HIG-27)', function () {
    Artisan::call('db:seed', ['--force' => true]);

    expect(Role::count())->toBe(3);

    $admin = User::where('email', 'admin@test.com')->firstOrFail();
    expect($admin->is_active)->toBeTrue();
    expect($admin->hasRole('admin'))->toBeTrue();

    $this->assertDatabaseCount('categories', 4);
});

test('DatabaseSeeder es idempotente: correrlo dos veces no duplica nada (HIG-27)', function () {
    Artisan::call('db:seed', ['--force' => true]);
    Artisan::call('db:seed', ['--force' => true]);

    expect(Role::count())->toBe(3);
    expect(User::count())->toBe(1);
    $this->assertDatabaseCount('categories', 4);
});

test('sin ADMIN_EMAIL el seeder falla nombrando la variable (HIG-27)', function () {
    config(['admin.initial_email' => null]);

    expect(fn () => (new AdminSeeder)->run())
        ->toThrow(RuntimeException::class, 'ADMIN_EMAIL');
});

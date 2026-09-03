<?php

use App\Enums\UserRole;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\ShippingCalculator;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

function adminUser(): User
{
    return User::factory()->withRole(UserRole::Admin)->create();
}

function vendedorUser(): User
{
    return User::factory()->withRole(UserRole::Vendedor)->create();
}

test('admin puede ver listado de tarifas', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get(route('tarifas-envio.index'))->assertOk();
});

test('vendedor recibe 403 al intentar ver tarifas', function () {
    $vendedor = vendedorUser();

    $this->actingAs($vendedor)->get(route('tarifas-envio.index'))->assertForbidden();
    $this->actingAs($vendedor)->get(route('tarifas-envio.create'))->assertForbidden();
});

test('admin puede crear tarifa con cp 4 dígitos y costo', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'cp' => '1407',
        'costo_cents' => 150000,
        'activo' => true,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(ShippingRate::query()->where('cp', '1407')->exists())->toBeTrue();
});

test('cp con ceros iniciales se conserva', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'cp' => '0123',
        'costo_cents' => 50000,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(ShippingRate::query()->where('cp', '0123')->exists())->toBeTrue();
});

test('cp vacío, nulo o no enviado es 422', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'cp' => '',
        'costo_cents' => 10000,
    ])->assertSessionHasErrors('cp');

    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'costo_cents' => 10000,
    ])->assertSessionHasErrors('cp');
});

test('cp con formato inválido es 422', function () {
    $admin = adminUser();

    foreach (['ABC', '123', '12345', '0123A', '12A4'] as $cp) {
        $this->actingAs($admin)->post(route('tarifas-envio.store'), [
            'cp' => $cp,
            'costo_cents' => 10000,
        ])->assertSessionHasErrors('cp');
    }
});

test('no se puede crear segunda tarifa activa para mismo cp', function () {
    $admin = adminUser();
    ShippingRate::factory()->create(['cp' => '1407', 'activo' => true]);

    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'cp' => '1407',
        'costo_cents' => 99999,
        'activo' => true,
    ])->assertSessionHasErrors('cp');

    // pero sí se puede crear inactiva
    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'cp' => '1407',
        'costo_cents' => 99999,
        'activo' => false,
    ])->assertRedirect(route('tarifas-envio.index'));
});

test('costo negativo o no entero es 422', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'cp' => '1407',
        'costo_cents' => -100,
    ])->assertSessionHasErrors('costo_cents');

    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'cp' => '1407',
        'costo_cents' => 'abc',
    ])->assertSessionHasErrors('costo_cents');
});

test('costo 0 es válido (envío gratis)', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('tarifas-envio.store'), [
        'cp' => '1000',
        'costo_cents' => 0,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(ShippingRate::query()->where('cp', '1000')->first()->costo_cents)->toBe(0);
});

test('desactivar tarifa vuelve cp a no disponible', function () {
    $admin = adminUser();
    $tarifa = ShippingRate::factory()->create(['cp' => '1407', 'activo' => true, 'costo_cents' => 10000]);

    $this->actingAs($admin)->patch(route('tarifas-envio.update', $tarifa), [
        'cp' => '1407',
        'costo_cents' => 10000,
        'activo' => false,
    ])->assertRedirect(route('tarifas-envio.index'));

    $calculator = app(ShippingCalculator::class);
    $quote = $calculator->quote('1407');
    expect($quote->disponible)->toBeFalse();
});

test('admin puede editar tarifa', function () {
    $admin = adminUser();
    $tarifa = ShippingRate::factory()->create(['cp' => '1407', 'costo_cents' => 10000]);

    $this->actingAs($admin)->patch(route('tarifas-envio.update', $tarifa), [
        'cp' => '1408',
        'costo_cents' => 20000,
        'activo' => true,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect($tarifa->fresh()->cp)->toBe('1408');
    expect($tarifa->fresh()->costo_cents)->toBe(20000);
});

test('admin puede eliminar tarifa', function () {
    $admin = adminUser();
    $tarifa = ShippingRate::factory()->create();

    $this->actingAs($admin)->delete(route('tarifas-envio.destroy', $tarifa))->assertRedirect(route('tarifas-envio.index'));
    expect(ShippingRate::query()->where('id', $tarifa->id)->exists())->toBeFalse();
});

test('check constraint impide costo negativo a nivel DB', function () {
    expect(fn () => DB::table('shipping_rates')->insert([
        'cp' => '9999',
        'costo_cents' => -1,
        'activo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

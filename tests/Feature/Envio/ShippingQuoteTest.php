<?php

use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\ShippingCalculator;

test('cotización disponible con tarifa activa retorna costo correcto', function () {
    ShippingRate::factory()->create(['cp' => '1407', 'costo_cents' => 150000, 'activo' => true]);

    $quote = app(ShippingCalculator::class)->quote('1407');

    expect($quote->disponible)->toBeTrue();
    expect($quote->costoCents)->toBe(150000);
});

test('cp válido sin tarifa retorna no disponible sin excepción', function () {
    $quote = app(ShippingCalculator::class)->quote('9999');

    expect($quote->disponible)->toBeFalse();
    expect($quote->costoCents)->toBe(0);
});

test('cp válido con tarifa inactiva retorna no disponible', function () {
    ShippingRate::factory()->create(['cp' => '1407', 'costo_cents' => 150000, 'activo' => false]);

    $quote = app(ShippingCalculator::class)->quote('1407');

    expect($quote->disponible)->toBeFalse();
});

test('cotización conserva ceros iniciales', function () {
    ShippingRate::factory()->create(['cp' => '0123', 'costo_cents' => 50000, 'activo' => true]);

    $quote = app(ShippingCalculator::class)->quote('0123');
    expect($quote->disponible)->toBeTrue();
    expect($quote->costoCents)->toBe(50000);

    $quote2 = app(ShippingCalculator::class)->quote('123');
    expect($quote2->disponible)->toBeFalse();
});

test('cotización es pública y anónima vía carrito', function () {
    ShippingRate::factory()->create(['cp' => '1407', 'costo_cents' => 10000, 'activo' => true]);

    $this->get(route('carrito.show', ['cp' => '1407']))->assertOk()->assertSee('Envío:');
    $this->get(route('carrito.show', ['cp' => '9999']))->assertOk()->assertSee('Envío no disponible');
});

test('cp con formato inválido en carrito muestra error sin excepción', function () {
    $this->get(route('carrito.show', ['cp' => 'ABC']))->assertOk()->assertSee('El código postal debe tener 4 dígitos');
    $this->get(route('carrito.show', ['cp' => '123']))->assertOk()->assertSee('El código postal debe tener 4 dígitos');
});

test('cuando hay cotización disponible total es subtotal + shipping', function () {
    $product = Product::factory()->unitMode()->create(['precio_cents' => 10000, 'stock' => 10]);
    ShippingRate::factory()->create(['cp' => '1407', 'costo_cents' => 5000, 'activo' => true]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 2])->assertRedirect(route('carrito.show'));

    // 2 * 10000 = 20000 subtotal + 5000 shipping = 25000 total
    $this->get(route('carrito.show', ['cp' => '1407']))
        ->assertOk()
        ->assertSee('Subtotal')
        ->assertSee('$200,00')
        ->assertSee('Envío: $50,00')
        ->assertSee('Total: $250,00');
});

test('sin cotización no hay total con envío', function () {
    $product = Product::factory()->unitMode()->create(['precio_cents' => 10000, 'stock' => 10]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 1])->assertRedirect(route('carrito.show'));

    $this->get(route('carrito.show', ['cp' => '9999']))
        ->assertOk()
        ->assertSee('Envío no disponible')
        ->assertDontSee('Total:');
});

test('costo 0 se cotiza como disponible con envío gratis', function () {
    ShippingRate::factory()->create(['cp' => '1000', 'costo_cents' => 0, 'activo' => true]);

    $quote = app(ShippingCalculator::class)->quote('1000');
    expect($quote->disponible)->toBeTrue();
    expect($quote->costoCents)->toBe(0);

    $product = Product::factory()->unitMode()->create(['precio_cents' => 10000, 'stock' => 10]);
    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 1])->assertRedirect(route('carrito.show'));

    $this->get(route('carrito.show', ['cp' => '1000']))
        ->assertOk()
        ->assertSee('Envío: $0,00')
        ->assertSee('Total: $100,00');
});

test('carrito vacío con cp válido muestra envío igual', function () {
    ShippingRate::factory()->create(['cp' => '1407', 'costo_cents' => 5000, 'activo' => true]);

    $this->get(route('carrito.show', ['cp' => '1407']))->assertOk()->assertSee('Envío: $50,00');
});

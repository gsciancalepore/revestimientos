<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    Session::flush();
});

function putCart(Product $product, int $cantidad): void
{
    app(Cart::class)->putItems([$product->id => $cantidad]);
}

test('GET /checkout vacio redirige a carrito', function () {
    $this->get(route('checkout.show'))->assertRedirect(route('carrito.show'));
});

test('GET /checkout con hasUnpurchasable redirige a carrito', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 1]);
    putCart($product, 1);
    $product->update(['activo' => false]);

    $this->get(route('checkout.show'))->assertRedirect(route('carrito.show'));
});

test('GET /checkout valido muestra formulario', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 10]);
    putCart($product, 1);

    $this->get(route('checkout.show'))->assertOk()->assertSee('Checkout');
});

test('POST /checkout validacion 422 si datos invalidos', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 10]);
    putCart($product, 1);

    $this->post(route('checkout.store'), [
        'customer_name' => '',
        'customer_email' => 'no-email',
        'customer_phone' => '',
        'shipping_cp' => 'ABC',
        'payment_method' => 'invalido',
    ])->assertSessionHasErrors(['customer_name', 'customer_email', 'customer_phone', 'shipping_cp', 'payment_method']);

    expect(Order::count())->toBe(0);
});

test('POST /checkout crea pedido transferencia y limpia carrito y guarda session', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 100000, 'm2_por_caja' => '1.00', 'unidad_venta' => 'm2']);
    putCart($product, 2);

    $response = $this->post(route('checkout.store'), [
        'customer_name' => 'Juan Perez',
        'customer_email' => 'juan@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'shipping_address' => 'Calle 123',
        'payment_method' => 'transferencia',
    ]);

    $response->assertRedirect(route('checkout.success'));
    expect(Order::count())->toBe(1);
    $order = Order::first();
    expect($order->customer_email)->toBe('juan@test.com');
    expect($order->payment_method)->toBe('transferencia');
    expect($order->status->value)->toBe('pending_payment');
    expect(session('order_id'))->toBe($order->id);
    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

test('POST /checkout mercadopago tambien crea pedido', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 50000, 'unidad_venta' => 'unidad', 'm2_por_caja' => null]);
    putCart($product, 1);

    $this->post(route('checkout.store'), [
        'customer_name' => 'Ana',
        'customer_email' => 'ana@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'payment_method' => 'mercadopago',
    ])->assertRedirect(route('checkout.success'));

    expect(Order::first()->payment_method)->toBe('mercadopago');
});

test('POST /checkout shipping no disponible crea con 0', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 50000, 'unidad_venta' => 'unidad', 'm2_por_caja' => null]);
    putCart($product, 1);

    $this->post(route('checkout.store'), [
        'customer_name' => 'NoShip',
        'customer_email' => 'noship@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '9999',
        'payment_method' => 'transferencia',
    ])->assertRedirect(route('checkout.success'));

    expect(Order::first()->shipping_cost_cents)->toBe(0);
});

test('POST /checkout shipping disponible suma total', function () {
    ShippingRate::factory()->create(['cp' => '1407', 'costo_cents' => 50000, 'activo' => true]);
    $product = Product::factory()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 100000, 'm2_por_caja' => '1.00', 'unidad_venta' => 'm2']);
    putCart($product, 1);

    $this->post(route('checkout.store'), [
        'customer_name' => 'Ship',
        'customer_email' => 'ship@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'payment_method' => 'transferencia',
    ])->assertRedirect(route('checkout.success'));

    $order = Order::first();
    expect($order->shipping_cost_cents)->toBe(50000);
    expect($order->total_cents)->toBe($order->subtotal_cents + 50000);
});

test('POST /checkout stock insuficiente DomainException no crea pedido y mantiene carrito', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 2, 'precio_cents' => 10000]);
    putCart($product, 2);
    $product->update(['stock' => 1]);

    $this->post(route('checkout.store'), [
        'customer_name' => 'Fail',
        'customer_email' => 'fail@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'payment_method' => 'transferencia',
    ])->assertSessionHasErrors('checkout');

    expect(Order::count())->toBe(0);
    expect(app(Cart::class)->items())->toBe([$product->id => 2]);
});

test('GET /checkout/exito sin session redirige a carrito', function () {
    $this->get(route('checkout.success'))->assertRedirect(route('carrito.show'));
});

test('GET /checkout/exito con session muestra pedido snapshot', function () {
    $product = Product::factory()->create(['name' => 'Orig', 'precio_cents' => 100000, 'm2_por_caja' => '1.00', 'stock' => 10, 'activo' => true, 'unidad_venta' => 'm2']);
    putCart($product, 1);
    $this->post(route('checkout.store'), [
        'customer_name' => 'Snap',
        'customer_email' => 'snap@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'payment_method' => 'transferencia',
    ]);

    $order = Order::first();
    $product->update(['name' => 'Mod', 'precio_cents' => 999999]);

    $this->get(route('checkout.success'))->assertOk()->assertSee((string) $order->id)->assertSee('Orig');
});

test('POST /checkout carrito vacio redirige sin crear', function () {
    $this->post(route('checkout.store'), [
        'customer_name' => 'Vacio',
        'customer_email' => 'vacio@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'payment_method' => 'transferencia',
    ])->assertRedirect(route('carrito.show'));

    expect(Order::count())->toBe(0);
});

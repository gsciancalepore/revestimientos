<?php

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart;
use App\Services\MercadoPagoGateway;
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
    // Gateway explícito: el test no debe depender de si hay token configurado ni tocar la API real.
    $this->app->bind(MercadoPagoGateway::class, fn () => new class extends MercadoPagoGateway
    {
        public function __construct() {}

        public function paymentUrl(Order $order): string
        {
            throw new RuntimeException('Error de MercadoPago');
        }
    });

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

test('GET /checkout/exito con pedido inexistente redirige a carrito en vez de 404 (HIG-32)', function () {
    // La regla 117 eligió `find` + redirect (HIG-09): un "arreglo" a `findOrFail`
    // rompería al cliente con sesión viva cuyo pedido ya no existe.
    $this->withSession(['order_id' => 999999])->get(route('checkout.success'))
        ->assertRedirect(route('carrito.show'));
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

test('POST /checkout congela el precio de oferta en la línea (HIG-10)', function () {
    $product = Product::factory()->unitMode()->create([
        'activo' => true,
        'stock' => 10,
        'precio_cents' => 10000,
        'precio_oferta_cents' => 7500,
    ]);
    putCart($product, 2);

    $this->post(route('checkout.store'), [
        'customer_name' => 'Oferta',
        'customer_email' => 'oferta@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'payment_method' => 'transferencia',
    ])->assertRedirect(route('checkout.success'));

    $order = Order::first();
    expect($order->subtotal_cents)->toBe(15000);
    expect($order->lines->first()->precio_unitario_cents)->toBe(7500);
});

test('POST /checkout acepta dirección de 500 caracteres (HIG-19)', function () {
    $product = Product::factory()->unitMode()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 10000]);
    putCart($product, 1);

    $direccion = str_repeat('a', 500);

    $this->post(route('checkout.store'), [
        'customer_name' => 'Larga',
        'customer_email' => 'larga@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'shipping_address' => $direccion,
        'payment_method' => 'transferencia',
    ])->assertRedirect(route('checkout.success'));

    expect(Order::first()->shipping_address)->toBe($direccion);
});

test('POST /checkout conserva el cero inicial del código postal (HIG-32)', function () {
    $product = Product::factory()->unitMode()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 10000]);
    putCart($product, 1);

    $this->post(route('checkout.store'), [
        'customer_name' => 'Cero',
        'customer_email' => 'cero@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '0123',
        'payment_method' => 'transferencia',
    ])->assertRedirect(route('checkout.success'));

    expect(Order::first()->shipping_cp)->toBe('0123');
});

test('POST /checkout recorta espacios de los datos del cliente (HIG-32)', function () {
    // El `prepareForValidation` corre en cada POST (más el TrimStrings global del
    // framework): valores con espacios llegan recortados a la columna.
    $product = Product::factory()->unitMode()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 10000]);
    putCart($product, 1);

    $this->post(route('checkout.store'), [
        'customer_name' => '  Espacios  ',
        'customer_email' => '  espacios@test.com  ',
        'customer_phone' => '  1122334455  ',
        'shipping_cp' => '  1407  ',
        'shipping_address' => '  Calle 123  ',
        'payment_method' => 'transferencia',
    ])->assertRedirect(route('checkout.success'));

    $order = Order::first();
    expect($order->customer_name)->toBe('Espacios');
    expect($order->customer_email)->toBe('espacios@test.com');
    expect($order->customer_phone)->toBe('1122334455');
    expect($order->shipping_cp)->toBe('1407');
    // `shipping_address` solo la recorta el Request: la Action no la toca.
    expect($order->shipping_address)->toBe('Calle 123');
});

test('GET /checkout/exito muestra los siete campos de línea de la regla 119 (HIG-20)', function () {
    $order = Order::factory()->create(['payment_method' => 'transferencia']);
    OrderLine::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Porcelanato Gris',
        'product_codigo' => 'ILV-12345',
        'marca' => 'Weber',
        'cantidad' => 2,
        'precio_unitario_cents' => 7500,
        'subtotal_cents' => 15000,
        'specs' => ['medida' => '60x60'],
    ]);

    $this->withSession(['order_id' => $order->id])->get(route('checkout.success'))
        ->assertOk()
        ->assertSee('Porcelanato Gris')
        ->assertSee('ILV-12345')
        ->assertSee('Weber')
        ->assertSee('75,00')
        ->assertSee('60x60');
});

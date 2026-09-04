<?php

use App\Models\Order;
use App\Models\Product;
use App\Services\Cart;
use App\Services\ManualTransferGateway;
use App\Services\MercadoPagoGateway;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    Session::flush();
});

if (! function_exists('putCartMp')) {
    function putCartMp(Product $product, int $cantidad): void
    {
        app(Cart::class)->putItems([$product->id => $cantidad]);
    }
}

class FakeMercadoPagoGatewaySuccess extends MercadoPagoGateway
{
    public static int $calls = 0;

    public function __construct()
    {
        // Sin SDK: no llama al padre para no exigir token.
    }

    public function paymentUrl(Order $order): string
    {
        self::$calls++;

        $order->update([
            'mp_preference_id' => 'pref-'.$order->id,
            'mp_init_point' => 'https://mercadopago.test/checkout/pref-'.$order->id,
        ]);

        return 'https://mercadopago.test/checkout/pref-'.$order->id;
    }
}

class FakeMercadoPagoGatewayFailure extends MercadoPagoGateway
{
    public static int $calls = 0;

    public function __construct()
    {
        // Sin SDK: no llama al padre para no exigir token.
    }

    public function paymentUrl(Order $order): string
    {
        self::$calls++;

        throw new RuntimeException('Error de MercadoPago');
    }
}

function checkoutPayload(array $overrides = []): array
{
    return array_merge([
        'customer_name' => 'Ana MP',
        'customer_email' => 'anamp@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'payment_method' => 'mercadopago',
    ], $overrides);
}

test('transferencia paymentUrl es null y nunca lanza', function () {
    $order = Order::factory()->create(['payment_method' => 'transferencia']);

    expect(app(ManualTransferGateway::class)->paymentUrl($order))->toBeNull();
});

test('POST /checkout mercadopago OK redirige a init_point y persiste columnas MP', function () {
    FakeMercadoPagoGatewaySuccess::$calls = 0;
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewaySuccess);

    $product = Product::factory()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 50000, 'unidad_venta' => 'unidad', 'm2_por_caja' => null]);
    putCartMp($product, 1);

    $response = $this->post(route('checkout.store'), checkoutPayload());

    $order = Order::first();
    expect($order->payment_method)->toBe('mercadopago');
    expect($order->mp_preference_id)->toBe('pref-'.$order->id);
    expect($order->mp_init_point)->toBe('https://mercadopago.test/checkout/pref-'.$order->id);
    $response->assertRedirect('https://mercadopago.test/checkout/pref-'.$order->id);
    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

test('POST /checkout mercadopago con error de API deja PendingPayment y avisa sin 500', function () {
    FakeMercadoPagoGatewayFailure::$calls = 0;
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewayFailure);

    $product = Product::factory()->create(['activo' => true, 'stock' => 10, 'precio_cents' => 50000, 'unidad_venta' => 'unidad', 'm2_por_caja' => null]);
    putCartMp($product, 1);

    $response = $this->post(route('checkout.store'), checkoutPayload());

    $response->assertRedirect(route('checkout.success'));
    $response->assertSessionHas('payment_error');
    $order = Order::first();
    expect($order->status->value)->toBe('pending_payment');
    expect($order->mp_init_point)->toBeNull();
    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

test('GET /checkout/exito con init_point muestra boton continuar sin crear preferencia', function () {
    FakeMercadoPagoGatewaySuccess::$calls = 0;
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewaySuccess);

    $order = Order::factory()->create([
        'payment_method' => 'mercadopago',
        'mp_preference_id' => 'pref-123',
        'mp_init_point' => 'https://mercadopago.test/checkout/pref-123',
    ]);

    $this->withSession(['order_id' => $order->id])
        ->get(route('checkout.success'))
        ->assertOk()
        ->assertSee('https://mercadopago.test/checkout/pref-123');

    expect(FakeMercadoPagoGatewaySuccess::$calls)->toBe(0);
});

test('GET /checkout/exito con payment_error muestra reintentar por POST sin crear preferencia', function () {
    FakeMercadoPagoGatewayFailure::$calls = 0;
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewayFailure);

    $order = Order::factory()->create(['payment_method' => 'mercadopago']);

    $this->withSession(['order_id' => $order->id, 'payment_error' => 'No pudimos generar el link de pago'])
        ->get(route('checkout.success'))
        ->assertOk()
        ->assertSee(route('checkout.mercadopago.retry'), false);

    expect(FakeMercadoPagoGatewayFailure::$calls)->toBe(0);
});

test('POST retry mercadopago OK redirige a nuevo init_point y sobrescribe columnas', function () {
    FakeMercadoPagoGatewaySuccess::$calls = 0;
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewaySuccess);

    $order = Order::factory()->create([
        'payment_method' => 'mercadopago',
        'mp_preference_id' => 'pref-vieja',
        'mp_init_point' => 'https://mercadopago.test/checkout/pref-vieja',
    ]);

    $response = $this->withSession(['order_id' => $order->id])
        ->post(route('checkout.mercadopago.retry'));

    $response->assertRedirect('https://mercadopago.test/checkout/pref-'.$order->id);
    expect($order->fresh()->mp_preference_id)->toBe('pref-'.$order->id);
});

test('POST retry mercadopago con error vuelve a success con payment_error', function () {
    FakeMercadoPagoGatewayFailure::$calls = 0;
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewayFailure);

    $order = Order::factory()->create(['payment_method' => 'mercadopago']);

    $response = $this->withSession(['order_id' => $order->id])
        ->post(route('checkout.mercadopago.retry'));

    $response->assertRedirect(route('checkout.success'));
    $response->assertSessionHas('payment_error');
});

test('POST retry con transferencia responde 403 sin crear preferencia', function () {
    FakeMercadoPagoGatewaySuccess::$calls = 0;
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewaySuccess);

    $order = Order::factory()->create(['payment_method' => 'transferencia']);

    $this->withSession(['order_id' => $order->id])
        ->post(route('checkout.mercadopago.retry'))
        ->assertForbidden();

    expect(FakeMercadoPagoGatewaySuccess::$calls)->toBe(0);
});

test('POST retry sin session redirige a carrito', function () {
    $this->post(route('checkout.mercadopago.retry'))->assertRedirect(route('carrito.show'));
});

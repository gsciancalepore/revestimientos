<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Services\Cart;
use App\Services\ManualTransferGateway;
use App\Services\MercadoPagoGateway;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

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

class PayloadInspectorGateway extends MercadoPagoGateway
{
    /**
     * @return array<string, mixed>
     */
    public function payloadFor(Order $order): array
    {
        return $this->preferencePayload($order);
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

// HIG-08: el guard de la regla 126 valida medio de pago Y estado, pero solo la
// primera condición estaba cubierta. Hoy ningún pedido llega a `paid` (regla 128);
// cuando la fase 08.b los mueva por webhook, un guard roto dejaría que un cliente
// con la sesión viva genere una preferencia nueva sobre un pedido ya pagado.
test('POST retry sobre un pedido mercadopago ya pagado responde 403 sin crear preferencia', function () {
    FakeMercadoPagoGatewaySuccess::$calls = 0;
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewaySuccess);

    $order = Order::factory()->create([
        'payment_method' => 'mercadopago',
        'status' => OrderStatus::Paid,
    ]);

    $this->withSession(['order_id' => $order->id])
        ->post(route('checkout.mercadopago.retry'))
        ->assertForbidden();

    expect(FakeMercadoPagoGatewaySuccess::$calls)->toBe(0);
});

test('POST retry sin session redirige a carrito', function () {
    $this->post(route('checkout.mercadopago.retry'))->assertRedirect(route('carrito.show'));
});

test('auto_return se envía cuando la back_url es pública', function () {
    URL::forceRootUrl('https://revestimientos.onrender.com');

    $order = Order::factory()->create(['payment_method' => 'mercadopago']);
    OrderLine::factory()->create(['order_id' => $order->id]);
    $order->load('lines');

    $payload = (new PayloadInspectorGateway)->payloadFor($order);

    expect($payload['auto_return'])->toBe('approved');
    expect($payload['back_urls']['success'])->toContain('revestimientos.onrender.com');
});

test('auto_return se omite cuando la back_url es localhost', function () {
    URL::forceRootUrl('http://localhost:8080');

    $order = Order::factory()->create(['payment_method' => 'mercadopago']);
    OrderLine::factory()->create(['order_id' => $order->id]);
    $order->load('lines');

    $payload = (new PayloadInspectorGateway)->payloadFor($order);

    expect($payload)->not->toHaveKey('auto_return');
    expect($payload['back_urls']['success'])->toContain('localhost');
});

test('auto_return se omite cuando la back_url apunta a una IP privada', function () {
    URL::forceRootUrl('http://192.168.0.10:8080');

    $order = Order::factory()->create(['payment_method' => 'mercadopago']);
    OrderLine::factory()->create(['order_id' => $order->id]);
    $order->load('lines');

    $payload = (new PayloadInspectorGateway)->payloadFor($order);

    expect($payload)->not->toHaveKey('auto_return');
});

test('el costo de envío viaja en shipments y el payload suma el total del pedido', function () {
    $order = Order::factory()->create([
        'payment_method' => 'mercadopago',
        'subtotal_cents' => 11250000,
        'shipping_cost_cents' => 800000,
        'total_cents' => 12050000,
    ]);
    OrderLine::factory()->create([
        'order_id' => $order->id,
        'cantidad' => 6,
        'precio_unitario_cents' => 1875000,
        'subtotal_cents' => 11250000,
    ]);
    $order->load('lines');

    $payload = (new PayloadInspectorGateway)->payloadFor($order);

    expect($payload['shipments']['cost'])->toBe(8000.0);
    expect($payload['shipments']['mode'])->toBe('not_specified');

    $itemsTotal = array_sum(array_map(
        fn (array $item): float => $item['unit_price'] * $item['quantity'],
        $payload['items']
    ));

    expect($itemsTotal + $payload['shipments']['cost'])->toBe((float) bcdiv((string) $order->total_cents, '100', 2));
});

test('sin costo de envío el payload no declara shipments', function () {
    $order = Order::factory()->create([
        'payment_method' => 'mercadopago',
        'shipping_cost_cents' => 0,
    ]);
    OrderLine::factory()->create(['order_id' => $order->id]);
    $order->load('lines');

    $payload = (new PayloadInspectorGateway)->payloadFor($order);

    expect($payload)->not->toHaveKey('shipments');
});

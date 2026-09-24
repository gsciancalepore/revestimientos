<?php

use App\Actions\ConfirmPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\MercadoPagoGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Spec observabilidad-01 — catálogo de eventos v1 (OBS-05) y enmiendas a las
 * reglas 124, 150 y 151.
 */
beforeEach(function () {
    Session::flush();
    config(['services.mercadopago.webhook_secret' => SECRETO_WEBHOOK]);
});

// --- OBS-05.1: espejo de la auditoría --------------------------------------

it('crear un pedido deja una sola entrada order.created con order_id y subject_id del pedido', function () {
    $dir = canalDeContrato();
    putCartMp(Product::factory()->create(['stock' => 10]), 2);

    $this->post(route('checkout.store'), checkoutPayload(['payment_method' => 'transferencia']));

    $order = Order::sole();
    $creados = eventosDelContrato($dir, 'order.created');

    expect($creados)->toHaveCount(1)
        ->and($creados[0]['attributes']['order_id'])->toBe($order->id)
        ->and($creados[0]['attributes']['subject_id'])->toBe($order->id)
        ->and($creados[0]['attributes']['lines'])->toBe([['product_id' => $order->lines->first()->product_id, 'cantidad' => 2]]);
});

it('un pedido que falla bajo lock no deja order.created y deja checkout.rejected con motivo dominio', function () {
    $dir = canalDeContrato();
    $product = Product::factory()->create(['stock' => 5]);
    cartConPrevalidacionVieja($product, 3);
    $product->update(['stock' => 1]);

    $this->post(route('checkout.store'), checkoutPayload());

    expect(eventosDelContrato($dir, 'order.created'))->toBe([]);

    $rechazo = eventosDelContrato($dir, 'checkout.rejected')[0]['attributes'];
    expect($rechazo)->toBe(['motivo' => 'dominio', 'detalle' => 'La cantidad solicitada supera el stock disponible.']);
});

it('el espejo no se escribe si la transacción que contiene la auditoría se revierte', function () {
    $dir = canalDeContrato();
    $order = pedidoDePanel();

    try {
        DB::transaction(function () use ($order) {
            app(AuditRecorder::class)->record('order.status_changed', $order, ['previous' => 'a', 'new' => 'b']);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(eventosDelContrato($dir, 'order.status_changed'))->toBe([]);
});

it('los incidentes y el stock negativo van en warning, el resto en info', function (string $accion, array $payload, string $nivel) {
    $dir = canalDeContrato();
    $order = pedidoDePanel();

    app(AuditRecorder::class)->record($accion, str_starts_with($accion, 'webhook.') ? null : $order, $payload);

    expect(eventosDelContrato($dir, $accion)[0]['level'])->toBe($nivel);
})->with([
    'monto distinto' => ['order.payment_amount_mismatch', ['payment_id' => 'p', 'cobrado_cents' => 1, 'total_cents' => 2], 'warning'],
    'pago tras cancelar' => ['order.paid_after_cancel', ['origen' => 'mercadopago', 'payment_id' => 'p'], 'warning'],
    'stock negativo' => ['order.stock_negative', ['product_id' => 1, 'cantidad' => 2, 'stock_resultante' => -1], 'warning'],
    'firma inválida' => ['webhook.signature_invalid', ['payment_id' => 'p', 'request_id' => 'r'], 'warning'],
    'transición' => ['order.status_changed', ['previous' => 'a', 'new' => 'b'], 'info'],
]);

// --- OBS-05.2: checkout.rejected -------------------------------------------

it('GET /checkout con el carrito vacío deja checkout.rejected carrito_vacio', function () {
    $dir = canalDeContrato();

    $this->get(route('checkout.show'))->assertRedirect(route('carrito.show'));

    expect(eventosDelContrato($dir, 'checkout.rejected')[0]['attributes'])->toBe(['motivo' => 'carrito_vacio']);
});

it('GET /checkout con un producto no comprable deja checkout.rejected no_comprable', function () {
    $dir = canalDeContrato();
    putCartMp(Product::factory()->create(['stock' => 10]), 2);
    Product::query()->update(['activo' => false]);

    $this->get(route('checkout.show'))->assertRedirect(route('carrito.show'));

    expect(eventosDelContrato($dir, 'checkout.rejected')[0]['attributes'])->toBe(['motivo' => 'no_comprable']);
});

it('POST /checkout con el carrito vacío deja checkout.rejected carrito_vacio', function () {
    $dir = canalDeContrato();

    $this->post(route('checkout.store'), checkoutPayload())->assertRedirect(route('carrito.show'));

    expect(eventosDelContrato($dir, 'checkout.rejected')[0]['attributes'])->toBe(['motivo' => 'carrito_vacio']);
});

it('POST /checkout rechazado por validación lista los campos y nunca sus valores', function () {
    $dir = canalDeContrato();
    putCartMp(Product::factory()->create(['stock' => 10]), 1);

    $this->post(route('checkout.store'), checkoutPayload(['customer_email' => 'no-es-un-email-centinela', 'customer_phone' => '']))
        ->assertSessionHasErrors(['customer_email', 'customer_phone']);

    $atributos = eventosDelContrato($dir, 'checkout.rejected')[0]['attributes'];

    expect($atributos['motivo'])->toBe('validacion')
        ->and($atributos['campos'])->toEqualCanonicalizing(['customer_email', 'customer_phone'])
        ->and(textoDelDirectorio($dir))->not->toContain('no-es-un-email-centinela');
});

it('POST /checkout rechazado por el dominio lleva el texto fijo de la excepción', function () {
    $dir = canalDeContrato();
    putCartMp(Product::factory()->create(['stock' => 10]), 1);
    Product::query()->update(['activo' => false]);

    $this->post(route('checkout.store'), checkoutPayload());

    expect(eventosDelContrato($dir, 'checkout.rejected')[0]['attributes'])
        ->toBe(['motivo' => 'dominio', 'detalle' => 'El carrito contiene productos no comprables.']);
});

it('ningún mensaje de DomainException de PlaceOrderAction interpola valores', function () {
    $codigo = (string) file_get_contents(app_path('Actions/PlaceOrderAction.php'));

    preg_match_all('/new \\\\DomainException\((.*?)\);/s', $codigo, $mensajes);

    expect($mensajes[1])->not->toBeEmpty();

    foreach ($mensajes[1] as $mensaje) {
        expect($mensaje)->toMatch("/^'[^'\$]*'$/");
    }
});

// --- OBS-05.3 y OBS-05.4: derivación a pagar -------------------------------

it('un checkout por MercadoPago exitoso deja checkout.payment_started', function () {
    $dir = canalDeContrato();
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewaySuccess);
    putCartMp(Product::factory()->create(['stock' => 10]), 1);

    $this->post(route('checkout.store'), checkoutPayload());

    $order = Order::sole();
    expect(eventosDelContrato($dir, 'checkout.payment_started')[0]['attributes'])->toBe([
        'order_id' => $order->id,
        'payment_method' => 'mercadopago',
        'total_cents' => $order->total_cents,
    ]);
});

it('una preferencia fallida deja checkout.mp_preference_failed sin mensaje del SDK y no deja payment_started', function () {
    $dir = canalDeContrato();
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewayFailure);
    putCartMp(Product::factory()->create(['stock' => 10]), 1);

    $this->post(route('checkout.store'), checkoutPayload());

    $fallo = eventosDelContrato($dir, 'checkout.mp_preference_failed')[0];

    expect($fallo['attributes'])->toBe(['order_id' => Order::sole()->id])
        ->and($fallo['level'])->toBe('error')
        ->and($fallo['error']['class'])->toBe(RuntimeException::class)
        ->and($fallo['error']['message'])->toBeNull()
        ->and(eventosDelContrato($dir, 'checkout.payment_started'))->toBe([]);
});

it('el reintento de MercadoPago también deja payment_started o mp_preference_failed', function () {
    $dir = canalDeContrato();
    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewayFailure);
    putCartMp(Product::factory()->create(['stock' => 10]), 1);
    $this->post(route('checkout.store'), checkoutPayload());

    $this->app->bind(MercadoPagoGateway::class, fn () => new FakeMercadoPagoGatewaySuccess);
    $this->post(route('checkout.mercadopago.retry'));

    expect(eventosDelContrato($dir, 'checkout.mp_preference_failed'))->toHaveCount(1)
        ->and(eventosDelContrato($dir, 'checkout.payment_started'))->toHaveCount(1);
});

it('una transferencia deja un solo payment_started aunque la página de éxito se abra dos veces', function () {
    $dir = canalDeContrato();
    putCartMp(Product::factory()->create(['stock' => 10]), 1);

    $this->post(route('checkout.store'), checkoutPayload(['payment_method' => 'transferencia']));
    $this->get(route('checkout.success'));
    $this->get(route('checkout.success'));

    $iniciados = eventosDelContrato($dir, 'checkout.payment_started');
    expect($iniciados)->toHaveCount(1)
        ->and($iniciados[0]['attributes']['payment_method'])->toBe('transferencia');
});

// --- OBS-05.5 a OBS-05.7: webhook y pago ↔ pedido --------------------------

it('un pago no aprobado deja webhook.payment_not_approved con payment_id, status y order_id', function () {
    $dir = canalDeContrato();
    $order = pedidoPagable();
    bindearConsulta(['status' => 'rejected', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    notificar('pago-9', firmaValida('pago-9', 'req-1'))->assertOk();

    expect(eventosDelContrato($dir, 'webhook.payment_not_approved')[0]['attributes'])
        ->toBe(['payment_id' => 'pago-9', 'status' => 'rejected', 'order_id' => $order->id]);
});

it('un external_reference no numérico deja order_id null en el pago no aprobado', function () {
    $dir = canalDeContrato();
    bindearConsulta(['status' => 'pending', 'external_reference' => 'otra-app', 'amount_cents' => 1]);

    notificar('pago-9', firmaValida('pago-9', 'req-1'))->assertOk();

    expect(eventosDelContrato($dir, 'webhook.payment_not_approved')[0]['attributes']['order_id'])->toBeNull();
});

it('una consulta fallida deja webhook.processing_failed y responde 503', function () {
    $dir = canalDeContrato();
    bindearConsulta(null, falla: true);

    notificar('pago-9', firmaValida('pago-9', 'req-1'))->assertStatus(503);

    $fallo = eventosDelContrato($dir, 'webhook.processing_failed')[0];
    expect($fallo['attributes'])->toBe(['payment_id' => 'pago-9'])
        ->and($fallo['error']['message'])->toBeNull();
});

it('una excepción dentro de la confirmación también deja webhook.processing_failed', function () {
    $dir = canalDeContrato();
    $order = pedidoPagable();
    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);
    $this->app->bind(ConfirmPaymentAction::class, fn () => new class extends ConfirmPaymentAction
    {
        public function __construct() {}

        public function execute(Order $order, string $origen, ?string $paymentId = null): Order
        {
            throw new RuntimeException('deadlock');
        }
    });

    notificar('pago-9', firmaValida('pago-9', 'req-1'))->assertStatus(503);

    expect(eventosDelContrato($dir, 'webhook.processing_failed'))->toHaveCount(1);
});

it('order.paid por webhook lleva payment_id y order_id en el log y en audit_logs', function () {
    $dir = canalDeContrato();
    $order = pedidoPagable();
    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    notificar('pago-9', firmaValida('pago-9', 'req-1'))->assertOk();

    expect(eventosDelContrato($dir, 'order.paid')[0]['attributes'])->toMatchArray([
        'order_id' => $order->id,
        'payment_id' => 'pago-9',
        'origen' => 'mercadopago',
    ])
        ->and(AuditLog::where('action', 'order.paid')->sole()->payload)->toBe(['origen' => 'mercadopago', 'payment_id' => 'pago-9']);
});

it('order.paid_after_cancel lleva payment_id y order_id en el log y en audit_logs', function () {
    $dir = canalDeContrato();
    $order = pedidoPagable();
    $order->update(['status' => OrderStatus::Cancelled]);
    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    notificar('pago-9', firmaValida('pago-9', 'req-1'))->assertOk();

    expect(eventosDelContrato($dir, 'order.paid_after_cancel')[0]['attributes'])->toMatchArray([
        'order_id' => $order->id,
        'payment_id' => 'pago-9',
    ])
        ->and(AuditLog::where('action', 'order.paid_after_cancel')->sole()->payload)->toBe(['origen' => 'mercadopago', 'payment_id' => 'pago-9']);
});

it('una confirmación manual deja payment_id null', function () {
    $dir = canalDeContrato();
    $order = pedidoDePanel(medioDePago: 'transferencia');
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('pedidos.confirmar-pago', $order));

    expect(eventosDelContrato($dir, 'order.paid')[0]['attributes']['payment_id'])->toBeNull()
        ->and(AuditLog::where('action', 'order.paid')->sole()->payload)->toBe(['origen' => 'manual', 'payment_id' => null]);
});

it('rechaza mercadopago sin payment_id y manual con payment_id, sin tocar pedido ni stock', function (string $origen, ?string $paymentId, string $medio) {
    $order = pedidoDePanel(medioDePago: $medio);
    $stock = $order->lines->first()->product->stock;

    expect(fn () => app(ConfirmPaymentAction::class)->execute($order, $origen, $paymentId))
        ->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->lines->first()->product->fresh()->stock)->toBe($stock);
})->with([
    'mercadopago sin id' => ['mercadopago', null, 'mercadopago'],
    'mercadopago con id vacío' => ['mercadopago', '  ', 'mercadopago'],
    'manual con id' => ['manual', 'pago-9', 'transferencia'],
]);

// --- OBS-05.1 y OBS-01: mp_request_id y valores externos -------------------

it('la firma inválida lleva mp_request_id en el log y la auditoría conserva request_id', function () {
    $dir = canalDeContrato();
    bindearConsulta(null);

    notificar('pago-9', 'firma-mala', 'req-de-mp')->assertStatus(401);

    expect(eventosDelContrato($dir, 'webhook.signature_invalid')[0]['attributes'])->toMatchArray([
        'payment_id' => 'pago-9',
        'mp_request_id' => 'req-de-mp',
    ])
        ->and(AuditLog::where('action', 'webhook.signature_invalid')->sole()->payload['request_id'])->toBe('req-de-mp');
});

it('trunca a 64 caracteres los valores que llegan de afuera', function () {
    $dir = canalDeContrato();
    bindearConsulta(null);
    $largo = str_repeat('z', 300);

    $this->get(route('webhook.mercadopago', ['type' => $largo, 'id' => $largo]))->assertOk();
    notificar('pago-9', 'firma-mala', $largo)->assertStatus(401);

    $ignorado = eventosDelContrato($dir, 'webhook.ignored')[0]['attributes'];
    $firma = eventosDelContrato($dir, 'webhook.signature_invalid')[0]['attributes'];

    expect(mb_strlen($ignorado['tipo']))->toBe(64)
        ->and(mb_strlen($ignorado['payment_id']))->toBe(64)
        ->and(mb_strlen($firma['mp_request_id']))->toBe(64);
});

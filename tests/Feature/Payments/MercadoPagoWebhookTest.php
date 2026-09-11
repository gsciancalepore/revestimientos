<?php

use App\Contracts\PaymentStatusQuery;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;

/**
 * Spec 08 fase 08.b — webhook de MercadoPago (reglas 153 a 158).
 *
 * Ningún test alcanza la red: la consulta a la API se resuelve con un doble
 * bindeado en el contenedor, nunca dependiendo de que falte el token.
 */
const SECRETO_WEBHOOK = 'secreto-de-prueba';

beforeEach(function () {
    config(['services.mercadopago.webhook_secret' => SECRETO_WEBHOOK]);
});

/**
 * Doble de la consulta a la API (regla 155).
 */
class FakePaymentStatusQuery implements PaymentStatusQuery
{
    public int $consultas = 0;

    /**
     * @param  array{status: string, external_reference: ?string, amount_cents: int}|null  $pago
     */
    public function __construct(private ?array $pago, private bool $falla = false) {}

    /**
     * @return array{status: string, external_reference: ?string, amount_cents: int}|null
     */
    public function findPayment(string $paymentId): ?array
    {
        $this->consultas++;

        if ($this->falla) {
            throw new RuntimeException('MercadoPago no responde.');
        }

        return $this->pago;
    }
}

/**
 * @param  array{status: string, external_reference: ?string, amount_cents: int}|null  $pago
 */
function bindearConsulta(?array $pago, bool $falla = false): FakePaymentStatusQuery
{
    $doble = new FakePaymentStatusQuery($pago, $falla);

    app()->instance(PaymentStatusQuery::class, $doble);

    return $doble;
}

/**
 * Firma como la manda MercadoPago: `ts=<ts>,v1=<hmac>` sobre el manifiesto
 * `id:<data.id>;request-id:<x-request-id>;ts:<ts>;`.
 */
function firmaValida(string $dataId, string $requestId, string $ts = '1700000000', string $secreto = SECRETO_WEBHOOK): string
{
    $hmac = hash_hmac('sha256', "id:{$dataId};request-id:{$requestId};ts:{$ts};", $secreto);

    return "ts={$ts},v1={$hmac}";
}

/**
 * @param  array<string, mixed>|null  $body
 */
function notificar(string $dataId, ?string $firma = null, string $requestId = 'req-1', ?array $body = null): TestResponse
{
    $headers = ['x-request-id' => $requestId];

    if ($firma !== null) {
        $headers['x-signature'] = $firma;
    }

    return test()->postJson(
        route('webhook.mercadopago'),
        $body ?? ['type' => 'payment', 'data' => ['id' => $dataId]],
        $headers
    );
}

function pedidoPagable(int $stock = 10, int $cantidad = 3, int $totalCents = 30000): Order
{
    $product = Product::factory()->create(['stock' => $stock]);

    $order = pedidoConLinea($product, $cantidad);
    $order->update(['total_cents' => $totalCents]);

    return $order->fresh();
}

// --- Regla 154: validación de firma -----------------------------------------

it('rechaza con 401 una notificación sin firma, sin consultar la API', function () {
    $consulta = bindearConsulta(['status' => 'approved', 'external_reference' => '1', 'amount_cents' => 30000]);

    notificar('pago-1')->assertStatus(401);

    expect($consulta->consultas)->toBe(0);

    $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.signature_invalid']);
});

it('rechaza con 401 una firma que no corresponde al manifiesto', function () {
    $consulta = bindearConsulta(['status' => 'approved', 'external_reference' => '1', 'amount_cents' => 30000]);

    notificar('pago-1', 'ts=1700000000,v1='.str_repeat('a', 64))->assertStatus(401);

    expect($consulta->consultas)->toBe(0);
});

it('rechaza la firma de otro secreto', function () {
    bindearConsulta(null);

    notificar('pago-1', firmaValida('pago-1', 'req-1', '1700000000', 'otro-secreto'))->assertStatus(401);
});

it('rechaza la firma calculada sobre otro id de pago', function () {
    bindearConsulta(null);

    notificar('pago-1', firmaValida('pago-otro', 'req-1'))->assertStatus(401);
});

it('rechaza todo cuando el secreto no está configurado', function () {
    config(['services.mercadopago.webhook_secret' => null]);
    $consulta = bindearConsulta(['status' => 'approved', 'external_reference' => '1', 'amount_cents' => 30000]);

    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertStatus(401);

    expect($consulta->consultas)->toBe(0);
});

// --- Regla 155: filtro por tipo antes de consultar ----------------------------

it('ignora con 200 una notificación que no es de pago, sin consultar la API', function () {
    $consulta = bindearConsulta(['status' => 'approved', 'external_reference' => '1', 'amount_cents' => 30000]);

    notificar('merchant-1', firmaValida('merchant-1', 'req-1'), 'req-1', [
        'type' => 'merchant_order',
        'data' => ['id' => 'merchant-1'],
    ])->assertOk();

    expect($consulta->consultas)->toBe(0);

    $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.ignored']);
});

// --- Reglas 156 a 158: qué se hace con el pago -------------------------------

it('confirma el pedido y descuenta el stock cuando el pago está aprobado por el monto correcto', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->lines()->first()->product->fresh()->stock)->toBe(7);

    $this->assertDatabaseHas('audit_logs', ['action' => 'order.paid', 'subject_id' => $order->id]);
});

it('responde 200 sin excepción cuando el external_reference no existe', function () {
    bindearConsulta(['status' => 'approved', 'external_reference' => '99999', 'amount_cents' => 30000]);

    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertOk();

    $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.order_not_found']);
});

it('no confirma cuando el monto cobrado no coincide con el total del pedido', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 22000]);

    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->lines()->first()->product->fresh()->stock)->toBe(10);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'order.payment_amount_mismatch',
        'subject_id' => $order->id,
    ]);
});

it('no mueve el pedido con un pago que todavía no está acreditado', function (string $status) {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(['status' => $status, 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->lines()->first()->product->fresh()->stock)->toBe(10);
})->with(['pending', 'in_process', 'rejected', 'cancelled']);

it('descuenta el stock una sola vez ante notificaciones repetidas', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertOk();
    notificar('pago-1', firmaValida('pago-1', 'req-2'), 'req-2')->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->lines()->first()->product->fresh()->stock)->toBe(7)
        ->and(DB::table('audit_logs')->where('action', 'order.paid')->count())->toBe(1);
});

// --- Regla 153: qué código HTTP se devuelve ----------------------------------

it('responde no-200 cuando la consulta a la API falla, para que MercadoPago reintente', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(null, falla: true);

    $response = notificar('pago-1', firmaValida('pago-1', 'req-1'));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(500)
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('está excluido de la verificación de CSRF', function () {
    // Laravel saltea `ValidateCsrfToken` mientras corre la suite (`runningUnitTests`
    // corta antes que `inExceptArray`), así que un POST de test pasa igual sin la
    // excepción: un test de request no cubre esta regla. Lo que se afirma es la
    // pieza de configuración que la regla 153 exige, que sí desaparece si se borra.
    expect(app(ValidateCsrfToken::class)->getExcludedPaths())->toContain('webhook/mercadopago');
});

it('no exige sesión ni usuario autenticado', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    expect(auth()->check())->toBeFalse();

    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    // El actor de la auditoría queda en null: el webhook corre sin sesión y por eso
    // el origen viaja en el payload (regla 150).
    $audit = DB::table('audit_logs')->where('action', 'order.paid')->first();

    expect($audit->actor_id)->toBeNull()
        ->and(json_decode((string) $audit->payload, true)['origen'])->toBe('mercadopago');
});

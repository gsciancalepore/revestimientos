<?php

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Spec 08 fase 08.b — webhook de MercadoPago (reglas 153 a 158).
 *
 * Ningún test alcanza la red: la consulta a la API se resuelve con un doble
 * bindeado en el contenedor, nunca dependiendo de que falte el token.
 */
beforeEach(function () {
    config(['services.mercadopago.webhook_secret' => SECRETO_WEBHOOK]);
});

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

it('responde 200 cuando MercadoPago no conoce el pago', function () {
    bindearConsulta(null);

    notificar('pago-fantasma', firmaValida('pago-fantasma', 'req-1'))->assertOk();

    $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.payment_not_found']);
});

it('no confirma cuando el monto cobrado no coincide con el total del pedido', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 22000]);

    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->lines()->first()->product->fresh()->stock)->toBe(10);

    $incidente = DB::table('audit_logs')->where('action', 'order.payment_amount_mismatch')->first();
    $payload = json_decode((string) $incidente->payload, true);

    // El panel de 08.c concilia con estos dos números: invertidos, el admin leería
    // que se cobró el total y se esperaba otra cosa.
    expect($incidente->subject_id)->toBe($order->id)
        ->and($payload['cobrado_cents'])->toBe(22000)
        ->and($payload['total_cents'])->toBe(30000);
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

it('procesa el IPN viejo, que manda topic e id por la query string', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    // Sin `type` ni `data` en el cuerpo: el canal viejo manda todo por la query.
    $this->postJson(
        route('webhook.mercadopago').'?topic=payment&id=pago-1',
        [],
        ['x-request-id' => 'req-1', 'x-signature' => firmaValida('pago-1', 'req-1')]
    )->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('lee el id de la query cuando el cuerpo no lo trae', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(['status' => 'approved', 'external_reference' => (string) $order->id, 'amount_cents' => 30000]);

    // PHP convierte el punto de `data.id` en guion bajo al parsear la query string.
    $this->postJson(
        route('webhook.mercadopago').'?type=payment&data_id=pago-1',
        [],
        ['x-request-id' => 'req-1', 'x-signature' => firmaValida('pago-1', 'req-1')]
    )->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

// --- Regla 153: qué código HTTP se devuelve ----------------------------------

it('responde no-200 cuando la consulta a la API falla, para que MercadoPago reintente', function () {
    $order = pedidoPagable(stock: 10, cantidad: 3, totalCents: 30000);

    bindearConsulta(null, falla: true);

    // 503 y no un 5xx cualquiera: la regla, `arquitectura.md` y `.ai/rules` se
    // comprometen con ese código.
    notificar('pago-1', firmaValida('pago-1', 'req-1'))->assertStatus(503);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
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

// --- HIG-18: GET responde 200 sin procesar -----------------------------------

test('GET al webhook sin parámetros responde 200 sin auditar (HIG-18)', function () {
    $this->getJson(route('webhook.mercadopago'))->assertOk();

    expect(AuditLog::count())->toBe(0);
});

test('GET al webhook con parámetros de notificación se ignora con 200 y deja webhook.ignored (HIG-18)', function () {
    $this->getJson(route('webhook.mercadopago').'?topic=merchant_order_wh&data_id=123')->assertOk();

    $audit = AuditLog::where('action', 'webhook.ignored')->firstOrFail();

    expect($audit->payload['tipo'])->toBe('merchant_order_wh');
});

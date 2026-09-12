<?php

use App\Actions\ConfirmPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\ProductSaleUnit;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Services\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Reglas 143 y 150: el stock desciende al confirmarse el pago (ADR-005), en la
// misma transacción que el cambio de estado. O pasan los dos, o no pasa ninguno.

test('confirmar el pago pasa el pedido a pagado y descuenta el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    $resultado = app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    expect($resultado->status)->toBe(OrderStatus::Paid);
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    expect($product->fresh()->stock)->toBe(7);
});

test('el descuento respeta la cantidad congelada de cada linea', function () {
    $porcelanato = Product::factory()->m2Mode()->create(['stock' => 20]);
    $pastina = Product::factory()->create(['stock' => 5, 'unidad_venta' => ProductSaleUnit::Unidad]);

    $order = pedidoConLinea($porcelanato, 4);
    $order->lines()->create([
        'product_id' => $pastina->id,
        'product_name' => $pastina->name,
        'product_codigo' => $pastina->codigo,
        'unidad_venta' => $pastina->unidad_venta->value,
        'cantidad' => 2,
        'precio_unitario_cents' => 5000,
        'subtotal_cents' => 10000,
    ]);

    app(ConfirmPaymentAction::class)->execute($order->fresh(), 'mercadopago');

    expect($porcelanato->fresh()->stock)->toBe(16);
    expect($pastina->fresh()->stock)->toBe(3);
});

test('la confirmacion queda auditada con el origen', function (string $origen, string $medioDePago) {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 1);
    $order->update(['payment_method' => $medioDePago]);

    app(ConfirmPaymentAction::class)->execute($order->fresh(), $origen);

    $audit = AuditLog::where('action', 'order.paid')->where('subject_id', $order->id)->firstOrFail();

    expect($audit->payload['origen'])->toBe($origen);
})->with([
    'automatica' => ['mercadopago', 'mercadopago'],
    // La manual solo vale sobre una transferencia (regla 159).
    'manual' => ['manual', 'transferencia'],
]);

test('la confirmacion manual solo vale para pedidos de transferencia', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3); // nace con payment_method mercadopago

    // Regla 159: el admin confirma a mano lo que cobró por transferencia. Sobre un
    // pedido de MercadoPago sería marcar pagado algo que nadie cobró.
    expect(fn () => app(ConfirmPaymentAction::class)->execute($order, 'manual'))
        ->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($product->fresh()->stock)->toBe(10)
        ->and(AuditLog::where('action', 'order.paid')->where('subject_id', $order->id)->count())->toBe(0);
});

test('la confirmacion manual de una transferencia descuenta stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    $order->update(['payment_method' => 'transferencia']);

    app(ConfirmPaymentAction::class)->execute($order->fresh(), 'manual');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($product->fresh()->stock)->toBe(7);
});

test('un origen desconocido lanza DomainException sin tocar nada', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    expect(fn () => app(ConfirmPaymentAction::class)->execute($order, 'inventado'))
        ->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    expect($product->fresh()->stock)->toBe(10);
});

// Regla 152: MercadoPago reintenta las notificaciones por diseño, así que la
// doble confirmación es el caso normal, no el raro.

test('confirmar dos veces descuenta el stock una sola vez', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    $action = app(ConfirmPaymentAction::class);
    $action->execute($order, 'mercadopago');
    $segunda = $action->execute($order->fresh(), 'mercadopago');

    expect($segunda->status)->toBe(OrderStatus::Paid);
    expect($product->fresh()->stock)->toBe(7);
    expect(AuditLog::where('action', 'order.paid')->where('subject_id', $order->id)->count())->toBe(1);
});

// Regla 145: el comercio se abastece directo del fabricante, así que un pago ya
// cobrado nunca se rechaza por falta de stock.

test('un pago cobrado sin stock suficiente igual pasa a pagado y deja el stock negativo', function () {
    $product = Product::factory()->create(['stock' => 1]);
    $order = pedidoConLinea($product, 4);

    $resultado = app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    expect($resultado->status)->toBe(OrderStatus::Paid);
    expect($product->fresh()->stock)->toBe(-3);
});

test('el stock negativo queda registrado como reposicion pendiente en la auditoria', function () {
    $product = Product::factory()->create(['stock' => 1]);
    $order = pedidoConLinea($product, 4);

    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    $audit = AuditLog::where('action', 'order.stock_negative')->where('subject_id', $order->id)->firstOrFail();

    expect($audit->payload['product_id'])->toBe($product->id);
    expect($audit->payload['cantidad'])->toBe(4);
    expect($audit->payload['stock_resultante'])->toBe(-3);
});

test('con stock suficiente no se registra reposicion pendiente', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 4);

    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    expect(AuditLog::where('action', 'order.stock_negative')->count())->toBe(0);
});

// Regla 151: plata cobrada sobre un pedido dado de baja. El pedido queda trabado
// a propósito; no hay override.

test('un pago sobre un pedido cancelado no lo pasa a pagado y registra el incidente', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3, OrderStatus::Cancelled);

    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($product->fresh()->stock)->toBe(10);

    $audit = AuditLog::where('action', 'order.paid_after_cancel')->where('subject_id', $order->id)->firstOrFail();
    expect($audit->payload['origen'])->toBe('mercadopago');
});

test('un pedido despachado o entregado no vuelve a confirmarse', function (OrderStatus $status) {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3, $status);

    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    expect($order->fresh()->status)->toBe($status);
    expect($product->fresh()->stock)->toBe(10);
})->with([
    'despachado' => OrderStatus::Shipped,
    'entregado' => OrderStatus::Delivered,
]);

// Criterio de aceptación: si algo falla, ni el estado ni el stock se movieron.

// Criterio de aceptación: estado y stock viajan en la misma transacción. Se fuerza
// el fallo después del descuento con un AuditRecorder que revienta al registrar el
// pago; sin transacción, el stock quedaría descontado y el pedido sin pagar.
test('si algo falla despues del descuento, ni el estado ni el stock se mueven', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder
    {
        public function record(string $action, ?Model $subject = null, ?array $payload = null): void
        {
            if ($action === 'order.paid') {
                throw new RuntimeException('fallo simulado al auditar el pago');
            }

            parent::record($action, $subject, $payload);
        }
    });

    expect(fn () => app(ConfirmPaymentAction::class)->execute($order, 'mercadopago'))
        ->toThrow(RuntimeException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    expect($product->fresh()->stock)->toBe(10);
    expect(AuditLog::where('subject_id', $order->id)->count())->toBe(0);
});

// B1 (revisor-entrega, 2026-09-11): los tests de idempotencia de arriba pasan un
// pedido ya releído con `fresh()`, así que el guard corta por el objeto del test y
// no por la relectura de la Action. Este reproduce el escenario real del webhook:
// dos notificaciones que localizaron el pedido por `external_reference` ANTES de
// entrar, cada una con su instancia leída en `pending_payment`. Falla si la Action
// decide sobre el parámetro en vez de sobre la fila releída bajo lock.
test('dos notificaciones con el pedido leido de antemano descuentan stock una sola vez', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    $notificacionA = Order::findOrFail($order->id);
    $notificacionB = Order::findOrFail($order->id);

    $action = app(ConfirmPaymentAction::class);
    $action->execute($notificacionA, 'mercadopago');
    $action->execute($notificacionB, 'mercadopago');

    expect($product->fresh()->stock)->toBe(7);
    expect(AuditLog::where('action', 'order.paid')->where('subject_id', $order->id)->count())->toBe(1);
});

test('una notificacion con el pedido leido antes de cancelarlo no lo pasa a pagado', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    // el webhook leyó el pedido y recién después el admin lo canceló
    $notificacion = Order::findOrFail($order->id);
    $order->update(['status' => OrderStatus::Cancelled]);

    app(ConfirmPaymentAction::class)->execute($notificacion, 'mercadopago');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($product->fresh()->stock)->toBe(10);
    expect(AuditLog::where('action', 'order.paid_after_cancel')->where('subject_id', $order->id)->count())->toBe(1);
});

// I2: el orden determinístico del lock (regla 143) es lo que evita el deadlock
// contra una cancelación concurrente. No se puede probar con concurrencia real en
// esta suite, pero sí que la query lo pide.
test('el bloqueo de productos pide las filas ordenadas por id', function () {
    $a = Product::factory()->create(['stock' => 10]);
    $b = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($b, 1);
    $order->lines()->create([
        'product_id' => $a->id,
        'product_name' => $a->name,
        'product_codigo' => $a->codigo,
        'unidad_venta' => $a->unidad_venta->value,
        'cantidad' => 1,
        'precio_unitario_cents' => 5000,
        'subtotal_cents' => 5000,
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    app(ConfirmPaymentAction::class)->execute($order->fresh(), 'mercadopago');

    $lock = collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'from "products"') && str_contains($sql, 'for update'));

    expect($lock)->not->toBeNull();
    expect($lock)->toContain('order by "id" asc');
});

// Menor (revisor-entrega): dos líneas del mismo producto se agregan antes de
// descontar. Hoy el carrito no las produce; la Spec 08.2 (alta manual) sí.
test('dos lineas del mismo producto descuentan la suma de las cantidades', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    $order->lines()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_codigo' => $product->codigo,
        'unidad_venta' => $product->unidad_venta->value,
        'cantidad' => 2,
        'precio_unitario_cents' => 10000,
        'subtotal_cents' => 20000,
    ]);

    app(ConfirmPaymentAction::class)->execute($order->fresh(), 'mercadopago');

    expect($product->fresh()->stock)->toBe(5);
});

// La relectura queda cubierta por los tests de arriba, pero el `lockForUpdate` del
// pedido no: su efecto solo se observa con transacciones concurrentes, que esta
// suite no puede reproducir (`RefreshDatabase` envuelve cada test en una). Lo que
// sí se puede afirmar es que la query lo pide, que es lo que serializa una
// confirmación contra una cancelación simultánea (reglas 147 y 150).
test('la query del pedido pide for update al confirmar', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 1);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    app(ConfirmPaymentAction::class)->execute(Order::findOrFail($order->id), 'mercadopago');

    $lock = collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'from "orders"') && str_contains($sql, 'for update'));

    expect($lock)->not->toBeNull();
});

// Regla 166 en su consumidor: sin esto se puede reemplazar la llamada a
// `TransitionOrderStatusAction` por un `update(['status' => ...])` directo y la
// suite no dice nada — el pedido pasaría a `paid` sin dejar rastro del cambio de
// estado, y la regla 161 de 08.c deriva los destacados del panel de `audit_logs`.
test('la confirmacion deja la transicion auditada', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 1);

    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    $audit = AuditLog::where('action', 'order.status_changed')->where('subject_id', $order->id)->firstOrFail();

    expect($audit->payload['previous'])->toBe('pending_payment');
    expect($audit->payload['new'])->toBe('paid');
});

// El borde exacto de la regla 145: agotar el stock (llegar a 0) es normal y NO es
// reposición pendiente. Sin este test, cambiar `< 0` por `<= 0` pasa inadvertido y
// toda venta que agota una línea aparece en el panel como faltante inexistente.
test('agotar el stock exacto no se registra como reposicion pendiente', function () {
    $product = Product::factory()->create(['stock' => 4]);
    $order = pedidoConLinea($product, 4);

    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    expect($product->fresh()->stock)->toBe(0);
    expect(AuditLog::where('action', 'order.stock_negative')->count())->toBe(0);
});

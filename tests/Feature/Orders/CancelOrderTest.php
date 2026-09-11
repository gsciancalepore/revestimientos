<?php

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmPaymentAction;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Services\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Reglas 147 a 149: al cancelar un pedido ya pagado o despachado se devuelve
// exactamente la cantidad congelada en `order_lines`, nunca recalculada.

test('cancelar un pedido pagado restituye el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');
    expect($product->fresh()->stock)->toBe(7);

    $resultado = app(CancelOrderAction::class)->execute($order->fresh());

    expect($resultado->status)->toBe(OrderStatus::Cancelled);
    expect($product->fresh()->stock)->toBe(10);
});

test('cancelar un pedido despachado restituye el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 4);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');
    $order->update(['status' => OrderStatus::Shipped]);

    app(CancelOrderAction::class)->execute($order->fresh());

    expect($product->fresh()->stock)->toBe(10);
});

// Regla 148: nunca se descontó, así que no hay nada que devolver.

test('cancelar un pedido pendiente de pago no toca el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    $resultado = app(CancelOrderAction::class)->execute($order);

    expect($resultado->status)->toBe(OrderStatus::Cancelled);
    expect($product->fresh()->stock)->toBe(10);
    expect(AuditLog::where('action', 'order.stock_restored')->count())->toBe(0);
});

test('la restitucion queda auditada con las cantidades devueltas', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    app(CancelOrderAction::class)->execute($order->fresh());

    $audit = AuditLog::where('action', 'order.stock_restored')->where('subject_id', $order->id)->firstOrFail();

    expect($audit->payload['lines'])->toBe([['product_id' => $product->id, 'cantidad' => 3]]);
});

// Regla 149 y 152: cancelar dos veces no devuelve el stock dos veces.

test('cancelar dos veces no restituye dos veces', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    $action = app(CancelOrderAction::class);
    $action->execute($order->fresh());

    expect(fn () => $action->execute($order->fresh()))->toThrow(DomainException::class);

    expect($product->fresh()->stock)->toBe(10);
});

test('un pedido entregado no se cancela', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');
    $order->update(['status' => OrderStatus::Delivered]);

    expect(fn () => app(CancelOrderAction::class)->execute($order->fresh()))
        ->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
    expect($product->fresh()->stock)->toBe(7);
});

test('la restitucion devuelve la cantidad congelada aunque el producto haya cambiado', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    // el admin repuso mercadería y cambió el precio entre el pago y la cancelación
    $product->update(['stock' => 20, 'precio_cents' => 999999]);

    app(CancelOrderAction::class)->execute($order->fresh());

    expect($product->fresh()->stock)->toBe(23);
});

test('un pedido pagado con stock negativo vuelve a su valor al cancelarse', function () {
    $product = Product::factory()->create(['stock' => 1]);
    $order = pedidoConLinea($product, 4);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');
    expect($product->fresh()->stock)->toBe(-3);

    app(CancelOrderAction::class)->execute($order->fresh());

    expect($product->fresh()->stock)->toBe(1);
});

// B2 (revisor-entrega, 2026-09-11): acá el agujero pierde stock. Todos los tests
// de arriba cancelan con `$order->fresh()`, así que `$estadoPrevio` sale del objeto
// del test. Este reproduce el escenario del panel de 08.c: el admin abrió el pedido
// impago, el webhook lo pagó y descontó entre medio, y el admin cancela con lo que
// tenía cargado. Si la Action decide sobre el parámetro, la restitución no corre.
test('cancelar con un pedido leido antes del pago igual restituye el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    // el admin cargó la pantalla con el pedido todavía impago
    $pedidoEnPantalla = Order::findOrFail($order->id);

    // entre medio entró el webhook: pagó y descontó
    app(ConfirmPaymentAction::class)->execute($order->fresh(), 'mercadopago');
    expect($product->fresh()->stock)->toBe(7);

    app(CancelOrderAction::class)->execute($pedidoEnPantalla);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($product->fresh()->stock)->toBe(10);
});

// I3: simétrico del test de rollback de ConfirmPaymentAction. Sin transacción, el
// pedido queda cancelado con el stock ya devuelto y la excepción propagando:
// mercadería fantasma en el catálogo.
test('si falla la auditoria de la restitucion, ni el estado ni el stock se mueven', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder
    {
        public function record(string $action, ?Model $subject = null, ?array $payload = null): void
        {
            if ($action === 'order.stock_restored') {
                throw new RuntimeException('fallo simulado al auditar la restitución');
            }

            parent::record($action, $subject, $payload);
        }
    });

    expect(fn () => app(CancelOrderAction::class)->execute($order->fresh()))
        ->toThrow(RuntimeException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    expect($product->fresh()->stock)->toBe(7);
});

// I2: mismo orden determinístico que el descuento, por el mismo motivo.
test('la restitucion pide las filas de productos ordenadas por id', function () {
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
    app(ConfirmPaymentAction::class)->execute($order->fresh(), 'mercadopago');

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    app(CancelOrderAction::class)->execute($order->fresh());

    $lock = collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'from "products"') && str_contains($sql, 'for update'));

    expect($lock)->not->toBeNull();
    expect($lock)->toContain('order by "id" asc');
});

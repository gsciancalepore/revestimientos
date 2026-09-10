<?php

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmPaymentAction;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;

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

<?php

use App\Actions\TransitionOrderStatusAction;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;

// Regla 166: toda transición pasa por esta acción; ningún controlador escribe
// `order.status`. La acción valida contra la máquina de estados y no toca stock
// (eso es de ConfirmPaymentAction y CancelOrderAction, reglas 143 y 147).

test('una transicion valida cambia el estado y lo persiste', function (OrderStatus $desde, OrderStatus $hasta) {
    $order = Order::factory()->create(['status' => $desde]);

    $resultado = app(TransitionOrderStatusAction::class)->execute($order, $hasta);

    expect($resultado->status)->toBe($hasta);
    expect($order->fresh()->status)->toBe($hasta);
})->with([
    'pendiente a pagado' => [OrderStatus::PendingPayment, OrderStatus::Paid],
    'pendiente a cancelado' => [OrderStatus::PendingPayment, OrderStatus::Cancelled],
    'pagado a despachado' => [OrderStatus::Paid, OrderStatus::Shipped],
    'pagado a cancelado' => [OrderStatus::Paid, OrderStatus::Cancelled],
    'despachado a entregado' => [OrderStatus::Shipped, OrderStatus::Delivered],
    'despachado a cancelado' => [OrderStatus::Shipped, OrderStatus::Cancelled],
]);

test('una transicion invalida lanza DomainException y no cambia el estado', function (OrderStatus $desde, OrderStatus $hasta) {
    $order = Order::factory()->create(['status' => $desde]);

    expect(fn () => app(TransitionOrderStatusAction::class)->execute($order, $hasta))
        ->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe($desde);
})->with([
    'pendiente no salta a despachado' => [OrderStatus::PendingPayment, OrderStatus::Shipped],
    'pagado no salta a entregado' => [OrderStatus::Paid, OrderStatus::Delivered],
    'pagado no vuelve a pendiente' => [OrderStatus::Paid, OrderStatus::PendingPayment],
    'entregado es final' => [OrderStatus::Delivered, OrderStatus::Cancelled],
    'cancelado es final' => [OrderStatus::Cancelled, OrderStatus::Paid],
    'a si mismo no' => [OrderStatus::Paid, OrderStatus::Paid],
]);

test('la transicion queda auditada con el estado anterior y el nuevo', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    app(TransitionOrderStatusAction::class)->execute($order, OrderStatus::Shipped);

    $audit = AuditLog::where('action', 'order.status_changed')->where('subject_id', $order->id)->firstOrFail();

    expect($audit->payload['previous'])->toBe('paid');
    expect($audit->payload['new'])->toBe('shipped');
});

test('una transicion invalida no deja rastro en la auditoria', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

    expect(fn () => app(TransitionOrderStatusAction::class)->execute($order, OrderStatus::Paid))
        ->toThrow(DomainException::class);

    expect(AuditLog::where('subject_id', $order->id)->count())->toBe(0);
});

test('la transicion no toca el stock de los productos', function () {
    $product = Product::factory()->create(['stock' => 7]);
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);
    $order->lines()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_codigo' => $product->codigo,
        'unidad_venta' => $product->unidad_venta->value,
        'cantidad' => 3,
        'precio_unitario_cents' => 10000,
        'subtotal_cents' => 30000,
    ]);

    app(TransitionOrderStatusAction::class)->execute($order, OrderStatus::Shipped);

    expect($product->fresh()->stock)->toBe(7);
});

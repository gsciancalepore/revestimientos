<?php

use App\Enums\OrderStatus;

// Regla 166: la máquina de estados es conocimiento del estado, así que vive en el
// enum; `TransitionOrderStatusAction` la consulta y es el único que escribe status.

test('un pedido pendiente de pago puede pagarse o cancelarse', function () {
    expect(OrderStatus::PendingPayment->puedeTransicionarA(OrderStatus::Paid))->toBeTrue();
    expect(OrderStatus::PendingPayment->puedeTransicionarA(OrderStatus::Cancelled))->toBeTrue();
});

test('un pedido pagado puede despacharse o cancelarse', function () {
    expect(OrderStatus::Paid->puedeTransicionarA(OrderStatus::Shipped))->toBeTrue();
    expect(OrderStatus::Paid->puedeTransicionarA(OrderStatus::Cancelled))->toBeTrue();
});

test('un pedido despachado puede entregarse o cancelarse', function () {
    expect(OrderStatus::Shipped->puedeTransicionarA(OrderStatus::Delivered))->toBeTrue();
    expect(OrderStatus::Shipped->puedeTransicionarA(OrderStatus::Cancelled))->toBeTrue();
});

test('entregado y cancelado son estados finales', function (OrderStatus $final, OrderStatus $destino) {
    expect($final->puedeTransicionarA($destino))->toBeFalse();
})->with([
    'entregado no vuelve a despachado' => [OrderStatus::Delivered, OrderStatus::Shipped],
    'entregado no se cancela' => [OrderStatus::Delivered, OrderStatus::Cancelled],
    'cancelado no se paga' => [OrderStatus::Cancelled, OrderStatus::Paid],
    'cancelado no se despacha' => [OrderStatus::Cancelled, OrderStatus::Shipped],
]);

test('no se puede saltear el pago ni retroceder', function (OrderStatus $desde, OrderStatus $hasta) {
    expect($desde->puedeTransicionarA($hasta))->toBeFalse();
})->with([
    'pendiente no salta a despachado' => [OrderStatus::PendingPayment, OrderStatus::Shipped],
    'pendiente no salta a entregado' => [OrderStatus::PendingPayment, OrderStatus::Delivered],
    'pagado no salta a entregado' => [OrderStatus::Paid, OrderStatus::Delivered],
    'pagado no vuelve a pendiente' => [OrderStatus::Paid, OrderStatus::PendingPayment],
    'despachado no vuelve a pagado' => [OrderStatus::Shipped, OrderStatus::Paid],
]);

test('ningun estado transiciona a si mismo', function (OrderStatus $estado) {
    expect($estado->puedeTransicionarA($estado))->toBeFalse();
})->with(OrderStatus::cases());

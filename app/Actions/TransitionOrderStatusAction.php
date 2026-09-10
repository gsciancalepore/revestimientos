<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\AuditRecorder;
use DomainException;

class TransitionOrderStatusAction
{
    public function __construct(
        private AuditRecorder $recorder,
    ) {}

    /**
     * Único camino para cambiar el estado de un pedido (Spec 08, regla 166).
     *
     * Valida contra la máquina de estados del enum y audita el cambio. No toca
     * stock: el descuento vive en `ConfirmPaymentAction` (regla 143) y la
     * restitución en `CancelOrderAction` (regla 147).
     *
     * @throws DomainException
     */
    public function execute(Order $order, OrderStatus $destino): Order
    {
        $anterior = $order->status;

        if (! $anterior->puedeTransicionarA($destino)) {
            throw new DomainException(
                "No se puede pasar un pedido de {$anterior->label()} a {$destino->label()}."
            );
        }

        $order->update(['status' => $destino]);

        $this->recorder->record('order.status_changed', $order, [
            'previous' => $anterior->value,
            'new' => $destino->value,
        ]);

        return $order;
    }
}

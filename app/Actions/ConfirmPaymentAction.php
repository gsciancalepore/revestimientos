<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\AuditRecorder;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentAction
{
    private const ORIGENES = ['mercadopago', 'manual'];

    public function __construct(
        private TransitionOrderStatusAction $transition,
        private AuditRecorder $recorder,
    ) {}

    /**
     * Único camino a `paid` (Spec 08, regla 150).
     *
     * Estado y stock se mueven en la misma transacción: o pasan los dos, o no
     * pasa ninguno. La transacción abre bloqueando el pedido y relee su estado
     * adentro, porque MercadoPago reintenta las notificaciones por diseño y dos
     * concurrentes leerían `PendingPayment` las dos (regla 152).
     *
     * @throws DomainException
     */
    public function execute(Order $order, string $origen): Order
    {
        $origen = trim($origen);

        if (! in_array($origen, self::ORIGENES, true)) {
            throw new DomainException('El origen de la confirmación no es válido.');
        }

        return DB::transaction(function () use ($order, $origen): Order {
            /** @var Order $pedido */
            $pedido = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Regla 151: plata cobrada sobre un pedido dado de baja. Queda trabado
            // a propósito: no hay forma de forzarlo a `paid`.
            if ($pedido->status === OrderStatus::Cancelled) {
                $this->recorder->record('order.paid_after_cancel', $pedido, ['origen' => $origen]);

                return $pedido;
            }

            // Regla 152: `paid`, `shipped` y `delivered` ya pasaron por acá.
            // Confirmar de nuevo es un no-op silencioso, sin descontar dos veces.
            if ($pedido->status !== OrderStatus::PendingPayment) {
                return $pedido;
            }

            $this->descontarStock($pedido);

            $this->transition->execute($pedido, OrderStatus::Paid);

            $this->recorder->record('order.paid', $pedido, ['origen' => $origen]);

            return $pedido;
        });
    }

    /**
     * Descuenta el stock de cada producto del pedido (reglas 143 a 145).
     *
     * Bloquea las filas **ordenadas por `product_id`**: el orden determinístico
     * evita el deadlock entre dos transacciones que tocan los mismos productos
     * en secuencia distinta. Usa las cantidades congeladas en `order_lines`,
     * nunca recalcula desde el producto (regla 149).
     *
     * @throws DomainException
     */
    private function descontarStock(Order $order): void
    {
        /** @var array<int, int> $cantidades */
        $cantidades = [];

        foreach ($order->lines()->get() as $line) {
            $cantidades[$line->product_id] = ($cantidades[$line->product_id] ?? 0) + $line->cantidad;
        }

        ksort($cantidades);

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->whereIn('id', array_keys($cantidades))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($cantidades as $productId => $cantidad) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            // La regla 67 y la FK `restrictOnDelete` lo vuelven imposible; si aun
            // así falta, se lanza en vez de saltear en silencio una inconsistencia.
            if ($product === null) {
                throw new DomainException("El producto {$productId} del pedido {$order->id} no existe.");
            }

            $stockResultante = $product->stock - $cantidad;

            $product->update(['stock' => $stockResultante]);

            // Regla 145: el pago ya se cobró y el comercio se abastece directo del
            // fabricante, así que nunca se rechaza; queda como reposición pendiente.
            if ($stockResultante < 0) {
                $this->recorder->record('order.stock_negative', $order, [
                    'product_id' => $productId,
                    'cantidad' => $cantidad,
                    'stock_resultante' => $stockResultante,
                ]);
            }
        }
    }
}

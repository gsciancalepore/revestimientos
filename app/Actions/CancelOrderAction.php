<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\AuditRecorder;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CancelOrderAction
{
    public function __construct(
        private TransitionOrderStatusAction $transition,
        private AuditRecorder $recorder,
    ) {}

    /**
     * Cancela un pedido y restituye el stock si correspondía (Spec 08, reglas
     * 147 a 149).
     *
     * Vive aparte de `ConfirmPaymentAction` por simetría y porque es otro caso
     * de uso. La transacción bloquea el pedido primero, para que una
     * cancelación y una confirmación de pago concurrentes se serialicen.
     *
     * La devolución del dinero es un trámite fuera del sistema (regla 165).
     *
     * @throws DomainException
     */
    public function execute(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            /** @var Order $pedido */
            $pedido = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $estadoPrevio = $pedido->status;

            $this->transition->execute($pedido, OrderStatus::Cancelled);

            // Regla 148: un pedido impago nunca comprometió stock.
            if ($estadoPrevio === OrderStatus::PendingPayment) {
                return $pedido;
            }

            $this->restituirStock($pedido);

            return $pedido;
        });
    }

    /**
     * Devuelve a cada producto la cantidad congelada en `order_lines` (regla
     * 149): la conversión m²→cajas quedó fijada al crear el pedido y no se
     * recalcula. Bloquea las filas ordenadas por `product_id`, igual que el
     * descuento, para no deadlockear contra una confirmación concurrente.
     *
     * @throws DomainException
     */
    private function restituirStock(Order $order): void
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

        $restituidas = [];

        foreach ($cantidades as $productId => $cantidad) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if ($product === null) {
                throw new DomainException("El producto {$productId} del pedido {$order->id} no existe.");
            }

            $product->update(['stock' => $product->stock + $cantidad]);

            $restituidas[] = ['product_id' => $productId, 'cantidad' => $cantidad];
        }

        $this->recorder->record('order.stock_restored', $order, ['lines' => $restituidas]);
    }
}

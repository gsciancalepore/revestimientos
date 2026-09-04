<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\AuditRecorder;
use App\Services\Cart;
use App\Services\ShippingCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PlaceOrderAction
{
    public function __construct(
        private Cart $cart,
        private ShippingCalculator $shippingCalculator,
        private AuditRecorder $auditRecorder,
    ) {}

    /**
     * Crear pedido desde carrito anónimo.
     *
     * @throws \DomainException
     */
    public function execute(
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        string $shippingCp,
        ?string $shippingAddress,
        string $paymentMethod,
    ): Order {
        if ($this->cart->isEmpty()) {
            throw new \DomainException('El carrito está vacío.');
        }

        if ($this->cart->hasUnpurchasable()) {
            throw new \DomainException('El carrito contiene productos no comprables.');
        }

        $shippingCp = trim($shippingCp);
        $paymentMethod = trim($paymentMethod);

        if (! in_array($paymentMethod, ['transferencia', 'mercadopago'], true)) {
            throw new \DomainException('El medio de pago no es válido.');
        }

        $order = DB::transaction(function () use ($customerName, $customerEmail, $customerPhone, $shippingCp, $shippingAddress, $paymentMethod): Order {
            $items = $this->cart->items();

            /** @var Collection<int, Product> $products */
            $products = Product::query()->whereIn('id', array_keys($items))->lockForUpdate()->get()->keyBy('id');

            $subtotalCents = '0';
            $linesData = [];

            foreach ($items as $productId => $cantidad) {
                /** @var Product|null $product */
                $product = $products->get($productId);

                if ($product === null) {
                    throw new \DomainException('Producto no encontrado.');
                }

                if (! $product->activo) {
                    throw new \DomainException('El producto no está disponible.');
                }

                if ($cantidad > $product->stock) {
                    throw new \DomainException('La cantidad solicitada supera el stock disponible.');
                }

                $precioUnitarioCents = $product->isM2Mode()
                    ? ($product->precioCajaCents() ?? 0)
                    : $product->precio_cents;

                $subtotalLinea = (int) bcmul((string) $cantidad, (string) $precioUnitarioCents, 0);
                $subtotalCents = bcadd($subtotalCents, (string) $subtotalLinea, 0);

                $linesData[] = [
                    'product' => $product,
                    'cantidad' => $cantidad,
                    'precio_unitario_cents' => $precioUnitarioCents,
                    'subtotal_cents' => $subtotalLinea,
                ];
            }

            $quote = $this->shippingCalculator->quote($shippingCp);
            $shippingCostCents = $quote->disponible ? $quote->costoCents : 0;

            $totalCents = (int) bcadd($subtotalCents, (string) $shippingCostCents, 0);

            /** @var Order $order */
            $order = Order::create([
                'status' => OrderStatus::PendingPayment,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'shipping_cp' => $shippingCp,
                'shipping_address' => $shippingAddress,
                'shipping_cost_cents' => $shippingCostCents,
                'subtotal_cents' => (int) $subtotalCents,
                'total_cents' => $totalCents,
                'payment_method' => $paymentMethod,
            ]);

            foreach ($linesData as $line) {
                /** @var Product $product */
                $product = $line['product'];

                $order->lines()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_codigo' => $product->codigo,
                    'marca' => $product->marca,
                    'unidad_venta' => $product->unidad_venta->value,
                    'm2_por_caja' => $product->m2_por_caja,
                    'cantidad' => $line['cantidad'],
                    'precio_unitario_cents' => $line['precio_unitario_cents'],
                    'subtotal_cents' => $line['subtotal_cents'],
                    'specs' => $product->specs,
                ]);
            }

            $this->auditRecorder->record('order.created', $order, [
                'subtotal_cents' => (int) $subtotalCents,
                'shipping_cost_cents' => $shippingCostCents,
                'total_cents' => $totalCents,
                'lines' => collect($linesData)->map(fn (array $l): array => [
                    'product_id' => $l['product']->id,
                    'cantidad' => $l['cantidad'],
                ])->all(),
            ]);

            return $order;
        });

        $this->cart->clear();

        return $order;
    }
}

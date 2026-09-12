<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MercadoPago\MercadoPagoConfig;
use Tests\RedProhibida;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Ningún test alcanza servicios externos (`.ai/rules/tests.md`). Va acá y no en
 * `TestCase` porque `TestCase` solo se extiende en `Feature`: un test unitario
 * del gateway —justo donde uno pondría un test de mapeo— quedaba con el cliente
 * cURL real activo, con la rule file prometiendo lo contrario.
 */
pest()->beforeEach(function () {
    MercadoPagoConfig::setHttpClient(new RedProhibida);
})->in('Feature', 'Unit');

/**
 * Pedido con una línea sobre el producto dado, para las pruebas de la Spec 08.
 *
 * Vive acá y no en un archivo de tests porque la usan `ConfirmPaymentTest` y
 * `CancelOrderTest`: con el helper declarado en el primero, el segundo no se podía
 * correr solo, y eso rompe el procedimiento con el que este repo se defiende
 * (mutar la implementación y correr la suite filtrada por archivo).
 */
function pedidoConLinea(Product $product, int $cantidad, OrderStatus $status = OrderStatus::PendingPayment): Order
{
    $order = Order::factory()->create(['status' => $status, 'payment_method' => 'mercadopago']);

    $order->lines()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_codigo' => $product->codigo,
        'marca' => $product->marca,
        'unidad_venta' => $product->unidad_venta->value,
        'm2_por_caja' => $product->m2_por_caja,
        'cantidad' => $cantidad,
        'precio_unitario_cents' => 10000,
        'subtotal_cents' => 10000 * $cantidad,
    ]);

    return $order->fresh();
}

/**
 * Pedido listo para las pruebas del panel (Spec 08.c).
 *
 * Vive acá por el mismo motivo que `pedidoConLinea`: lo usan
 * `OrderPanelPermissionsTest` y `OrderPanelTest`, y declarado en uno de ellos el
 * otro no se puede correr solo, que es justo el procedimiento con el que este
 * repo se defiende (mutar la implementación y correr la suite filtrada).
 */
function pedidoDePanel(OrderStatus $status = OrderStatus::PendingPayment, string $medioDePago = 'mercadopago'): Order
{
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 2, $status);
    $order->update(['payment_method' => $medioDePago]);

    return $order->fresh();
}

<?php

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesSeeder;

/**
 * Spec 08 fase 08.c — panel de pedidos (reglas 159, 161, 162 y 165).
 */
beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->admin = User::factory()->withRole(UserRole::Admin)->create();
});

// --- Regla 161: filtros y búsqueda ------------------------------------------

test('el listado filtra por estado', function () {
    $pagado = pedidoDePanel(OrderStatus::Paid);
    $impago = pedidoDePanel(OrderStatus::PendingPayment);

    $this->actingAs($this->admin)
        ->get('/admin/pedidos?estado=paid')
        ->assertOk()
        ->assertSee("#{$pagado->id}")
        ->assertDontSee("#{$impago->id}");
});

test('el listado busca por email del cliente', function () {
    $buscado = pedidoDePanel();
    $buscado->update(['customer_email' => 'quien.busco@example.com']);
    $otro = pedidoDePanel();
    $otro->update(['customer_email' => 'otro@example.com']);

    $this->actingAs($this->admin)
        ->get('/admin/pedidos?q=quien.busco')
        ->assertOk()
        ->assertSee('quien.busco@example.com')
        ->assertDontSee('otro@example.com');
});

test('el listado busca por numero de pedido', function () {
    $buscado = pedidoDePanel();
    $otro = pedidoDePanel();

    $this->actingAs($this->admin)
        ->get("/admin/pedidos?q={$buscado->id}")
        ->assertOk()
        ->assertSee("#{$buscado->id}")
        ->assertDontSee("#{$otro->id}");
});

// --- Regla 161: destacados derivados ----------------------------------------

test('un pedido pagado con stock negativo aparece como reposicion pendiente, y deja de aparecer al reponer', function () {
    $product = Product::factory()->create(['stock' => 1]);
    $order = pedidoConLinea($product, 3);

    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    expect($product->fresh()->stock)->toBe(-2);

    $this->actingAs($this->admin)->get('/admin/pedidos')->assertOk()->assertSee('Reposición pendiente');

    // Se apaga solo cuando el admin repone: es la propiedad que lo hace útil.
    $product->update(['stock' => 10]);

    $this->actingAs($this->admin)->get('/admin/pedidos')->assertOk()->assertDontSee('Reposición pendiente');
});

test('un incidente de pago se destaca a partir de la auditoria', function (string $accion) {
    $order = pedidoDePanel();

    AuditLog::create([
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->id,
        'action' => $accion,
        'payload' => [],
    ]);

    $this->actingAs($this->admin)->get('/admin/pedidos')->assertOk()->assertSee('Incidente de pago');
})->with(['order.payment_amount_mismatch', 'order.paid_after_cancel']);

test('las auditorias del webhook no destacan el pedido como incidente', function (string $accion) {
    $order = pedidoDePanel();

    AuditLog::create([
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->id,
        'action' => $accion,
        'payload' => [],
    ]);

    $this->actingAs($this->admin)->get('/admin/pedidos')->assertOk()->assertDontSee('Incidente de pago');
})->with(['webhook.signature_invalid', 'webhook.ignored', 'order.stock_restored', 'order.paid']);

// --- Regla 162: la traza de auditoría es solo para admin --------------------

test('el admin ve la traza de auditoria del pedido y el vendedor no', function () {
    $order = pedidoDePanel();
    app(CancelOrderAction::class)->execute($order);

    $vendedor = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($this->admin)->get("/admin/pedidos/{$order->id}")->assertOk()->assertSee('Auditoría');
    $this->actingAs($vendedor)->get("/admin/pedidos/{$order->id}")->assertOk()->assertDontSee('Auditoría');
});

// --- Regla 159: confirmación manual -----------------------------------------

test('el admin confirma a mano una transferencia y el stock se descuenta', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 4);
    $order->update(['payment_method' => 'transferencia']);

    $this->actingAs($this->admin)
        ->post("/admin/pedidos/{$order->id}/confirmar-pago")
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($product->fresh()->stock)->toBe(6);
});

test('el boton de confirmar a mano no se ofrece para un pedido de mercadopago', function () {
    $order = pedidoDePanel(medioDePago: 'mercadopago');

    $this->actingAs($this->admin)
        ->get("/admin/pedidos/{$order->id}")
        ->assertOk()
        ->assertDontSee('Confirmar pago por transferencia');
});

test('confirmar a mano un pedido de mercadopago no lo marca pagado aunque se fuerce el POST', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 4); // mercadopago

    $this->actingAs($this->admin)
        ->post("/admin/pedidos/{$order->id}/confirmar-pago")
        ->assertSessionHasErrors('pedido');

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($product->fresh()->stock)->toBe(10);
});

// --- Regla 165: cancelaciones ------------------------------------------------

test('cancelar un pedido impago no toca stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);

    $this->actingAs($this->admin)->post("/admin/pedidos/{$order->id}/cancelar")->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(10);
});

test('cancelar un pedido pagado restituye el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 3);
    app(ConfirmPaymentAction::class)->execute($order, 'mercadopago');

    expect($product->fresh()->stock)->toBe(7);

    $this->actingAs($this->admin)->post("/admin/pedidos/{$order->id}/cancelar")->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(10);
});

test('cancelar un pedido ya entregado no se puede', function () {
    $order = pedidoDePanel(OrderStatus::Delivered);

    $this->actingAs($this->admin)
        ->post("/admin/pedidos/{$order->id}/cancelar")
        ->assertSessionHasErrors('pedido');

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

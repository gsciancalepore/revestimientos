<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesSeeder;

/**
 * Spec 08 fase 08.c — vista depósito (reglas 163 y 164).
 */
beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->deposito = User::factory()->withRole(UserRole::Deposito)->create();
});

test('la solapa por preparar muestra los pagados y no los despachados', function () {
    $pagado = pedidoDePanel(OrderStatus::Paid);
    $despachado = pedidoDePanel(OrderStatus::Shipped);

    $this->actingAs($this->deposito)
        ->get('/admin/despacho')
        ->assertOk()
        ->assertSee("#{$pagado->id}")
        ->assertDontSee("#{$despachado->id}");
});

test('la solapa despachados muestra los despachados y no los pagados', function () {
    $pagado = pedidoDePanel(OrderStatus::Paid);
    $despachado = pedidoDePanel(OrderStatus::Shipped);

    $this->actingAs($this->deposito)
        ->get('/admin/despacho?solapa=despachados')
        ->assertOk()
        ->assertSee("#{$despachado->id}")
        ->assertDontSee("#{$pagado->id}");
});

test('la vista deposito no muestra importes', function () {
    $pedido = pedidoDePanel(OrderStatus::Paid);
    $pedido->update(['total_cents' => 1234567, 'subtotal_cents' => 1200000, 'shipping_cost_cents' => 34567]);

    // El depósito arma envíos: no necesita ver plata (regla 163).
    $this->actingAs($this->deposito)
        ->get('/admin/despacho')
        ->assertOk()
        ->assertSee("#{$pedido->id}")
        ->assertDontSee('12.345,67')
        ->assertDontSee('345,67');
});

test('la vista deposito ordena por antiguedad', function () {
    $viejo = pedidoDePanel(OrderStatus::Paid);
    $nuevo = pedidoDePanel(OrderStatus::Paid);

    $html = $this->actingAs($this->deposito)->get('/admin/despacho')->assertOk()->getContent();

    expect(strpos($html, "#{$viejo->id}"))->toBeLessThan(strpos($html, "#{$nuevo->id}"));
});

test('la vista deposito muestra lo necesario para armar el envio', function () {
    $pedido = pedidoDePanel(OrderStatus::Paid);
    $pedido->update(['shipping_address' => 'Av. Siempreviva 742', 'shipping_cp' => '1425']);
    $linea = $pedido->lines()->first();

    $this->actingAs($this->deposito)
        ->get('/admin/despacho')
        ->assertOk()
        ->assertSee('Av. Siempreviva 742')
        ->assertSee('1425')
        ->assertSee($linea->product_name)
        ->assertSee($linea->product_codigo);
});

test('despachar y entregar quedan auditados', function () {
    $pedido = pedidoDePanel(OrderStatus::Paid);

    $this->actingAs($this->deposito)->post("/admin/despacho/{$pedido->id}/despachar")->assertRedirect();
    $this->actingAs($this->deposito)->post("/admin/despacho/{$pedido->id}/entregar")->assertRedirect();

    expect($pedido->fresh()->status)->toBe(OrderStatus::Delivered);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'order.status_changed',
        'subject_id' => $pedido->id,
    ]);
});

test('no se puede despachar un pedido que no esta pagado', function () {
    $pedido = pedidoDePanel(OrderStatus::PendingPayment);

    $this->actingAs($this->deposito)
        ->post("/admin/despacho/{$pedido->id}/despachar")
        ->assertSessionHasErrors('pedido');

    expect($pedido->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('el deposito no descuenta ni restituye stock al despachar', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $pedido = pedidoConLinea($product, 2, OrderStatus::Paid);

    $this->actingAs($this->deposito)->post("/admin/despacho/{$pedido->id}/despachar")->assertRedirect();

    // El stock ya bajó al confirmarse el pago (08.a): despachar no lo vuelve a tocar.
    expect($product->fresh()->stock)->toBe(5);
});

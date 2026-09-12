<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesSeeder;

/**
 * Spec 08 fase 08.c — matriz de permisos (reglas 160 a 165).
 */
beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

// --- Regla 161 y 162: listado y detalle -------------------------------------

test('un invitado va al login en todas las pantallas de pedidos', function (string $url) {
    $this->get($url)->assertRedirect('/admin/login');
})->with(fn () => ['/admin/pedidos', '/admin/despacho']);

test('admin y vendedor ven el listado de pedidos', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get('/admin/pedidos')->assertOk();
})->with([
    'admin' => UserRole::Admin,
    'vendedor' => UserRole::Vendedor,
]);

test('el deposito no accede al listado de pedidos', function () {
    $actor = User::factory()->withRole(UserRole::Deposito)->create();

    $this->actingAs($actor)->get('/admin/pedidos')->assertForbidden();
});

test('admin y vendedor ven el detalle de un pedido', function (UserRole $role) {
    $order = pedidoDePanel();
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get("/admin/pedidos/{$order->id}")->assertOk();
})->with([
    'admin' => UserRole::Admin,
    'vendedor' => UserRole::Vendedor,
]);

test('el deposito no accede al detalle de un pedido', function () {
    $order = pedidoDePanel();
    $actor = User::factory()->withRole(UserRole::Deposito)->create();

    $this->actingAs($actor)->get("/admin/pedidos/{$order->id}")->assertForbidden();
});

// --- Regla 159 y 160: confirmación manual -----------------------------------

test('solo el admin confirma un cobro a mano', function (UserRole $role) {
    $order = pedidoDePanel(medioDePago: 'transferencia');
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)
        ->post("/admin/pedidos/{$order->id}/confirmar-pago")
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

// --- Regla 165: cancelaciones ------------------------------------------------

test('solo el admin cancela pedidos', function (UserRole $role) {
    $order = pedidoDePanel();
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)
        ->post("/admin/pedidos/{$order->id}/cancelar")
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

// --- Regla 163 y 164: vista depósito ----------------------------------------

test('admin y deposito ven la vista de despacho', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get('/admin/despacho')->assertOk();
})->with([
    'admin' => UserRole::Admin,
    'deposito' => UserRole::Deposito,
]);

test('el vendedor no accede a la vista de despacho', function () {
    $actor = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($actor)->get('/admin/despacho')->assertForbidden();
});

test('admin y deposito despachan y entregan', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();
    $pagado = pedidoDePanel(OrderStatus::Paid);
    $despachado = pedidoDePanel(OrderStatus::Shipped);

    $this->actingAs($actor)->post("/admin/despacho/{$pagado->id}/despachar")->assertRedirect();
    $this->actingAs($actor)->post("/admin/despacho/{$despachado->id}/entregar")->assertRedirect();

    expect($pagado->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and($despachado->fresh()->status)->toBe(OrderStatus::Delivered);
})->with([
    'admin' => UserRole::Admin,
    'deposito' => UserRole::Deposito,
]);

test('el vendedor no despacha ni entrega', function () {
    $actor = User::factory()->withRole(UserRole::Vendedor)->create();
    $pagado = pedidoDePanel(OrderStatus::Paid);

    $this->actingAs($actor)->post("/admin/despacho/{$pagado->id}/despachar")->assertForbidden();

    expect($pagado->fresh()->status)->toBe(OrderStatus::Paid);
});

// --- Navegación: cada rol ve lo suyo ----------------------------------------

test('la navegacion del panel ofrece pedidos a admin y vendedor, y despacho a admin y deposito', function (
    UserRole $role,
    bool $vePedidos,
    bool $veDespacho,
) {
    $actor = User::factory()->withRole($role)->create();

    $html = $this->actingAs($actor)->get('/admin')->assertOk()->getContent();

    // Se afirma el enlace, no la palabra: "Pedidos" aparece en otros textos de la
    // página y un `assertSee` daría verde sin que el acceso exista.
    expect(str_contains($html, 'href="'.route('pedidos.index').'"'))->toBe($vePedidos)
        ->and(str_contains($html, 'href="'.route('despacho.index').'"'))->toBe($veDespacho);
})->with([
    'admin' => [UserRole::Admin, true, true],
    'vendedor' => [UserRole::Vendedor, true, false],
    'deposito' => [UserRole::Deposito, false, true],
]);

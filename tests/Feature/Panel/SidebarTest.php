<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('el admin ve todas las secciones del sidebar con su href exacto (HIG-28)', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee(route('usuarios.index'), false)
        ->assertSee(route('categorias.index'), false)
        ->assertSee(route('productos.index'), false)
        ->assertSee(route('tarifas-envio.index'), false)
        ->assertSee(route('pedidos.index'), false)
        ->assertSee(route('despacho.index'), false)
        ->assertSee('Ventas WhatsApp');
});

test('el vendedor solo ve Pedidos y no las secciones de admin ni Despacho (HIG-28)', function () {
    $vendedor = User::factory()->withRole(UserRole::Vendedor)->create();

    $this->actingAs($vendedor)->get('/admin')
        ->assertOk()
        ->assertSee(route('pedidos.index'), false)
        ->assertDontSee(route('usuarios.index'))
        ->assertDontSee(route('categorias.index'))
        ->assertDontSee(route('productos.index'))
        ->assertDontSee(route('tarifas-envio.index'))
        ->assertDontSee(route('despacho.index'))
        ->assertSee('Ventas WhatsApp');
});

test('el depósito solo ve Despacho y no las secciones de admin ni Pedidos (HIG-28)', function () {
    $deposito = User::factory()->withRole(UserRole::Deposito)->create();

    $this->actingAs($deposito)->get('/admin')
        ->assertOk()
        ->assertSee(route('despacho.index'), false)
        ->assertDontSee(route('pedidos.index'))
        ->assertDontSee(route('usuarios.index'))
        ->assertDontSee(route('categorias.index'))
        ->assertDontSee(route('productos.index'))
        ->assertDontSee(route('tarifas-envio.index'))
        ->assertSee('Ventas WhatsApp');
});

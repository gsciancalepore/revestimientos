<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\Cart;

test('el carrito vacío muestra mensaje y enlace al catálogo', function () {
    $this->get(route('carrito.show'))
        ->assertOk()
        ->assertSee('Tu carrito está vacío')
        ->assertSee('Ver catálogo');
});

test('cliente anónimo agrega producto modo m2 indicando m² → cajas ceil y subtotal por caja', function () {
    $product = Product::factory()->m2Mode()->create([
        'precio_cents' => 10000,
        'm2_por_caja' => '2.50',
        'stock' => 10,
        'activo' => true,
    ]);

    // 5 m² / 2.5 = 2 cajas exactas
    $this->post(route('carrito.add'), [
        'producto' => $product->slug,
        'superficie' => 5,
    ])->assertRedirect(route('carrito.show'));

    // 5.1 m² / 2.5 = ceil 3 cajas
    $product2 = Product::factory()->m2Mode()->create([
        'precio_cents' => 10000,
        'm2_por_caja' => '2.50',
        'stock' => 10,
    ]);
    $this->post(route('carrito.add'), [
        'producto' => $product2->slug,
        'superficie' => 5.1,
    ])->assertRedirect(route('carrito.show'));

    // Verificar subtotal: precio_caja = round(10000*2.5)=25000; 2 cajas=50000; 3 cajas=75000; total subtotal incluye ambas líneas
    $this->get(route('carrito.show'))
        ->assertOk()
        ->assertSee('2 cajas')
        ->assertSee('3 cajas');

    $cart = app(Cart::class);
    expect($cart->subtotal())->toBe(25000 * 2 + 25000 * 3);
});

test('cliente agrega modo m2 con 10 % desperdicio → m²_a_cubrir antes de ceil', function () {
    $product = Product::factory()->m2Mode()->create([
        'precio_cents' => 10000,
        'm2_por_caja' => '2.00',
        'stock' => 10,
    ]);

    // 4 m² sin desperdicio = 2 cajas; con 10% = 4.4 m² → ceil 3 cajas
    $this->post(route('carrito.add'), [
        'producto' => $product->slug,
        'superficie' => 4,
        'desperdicio' => true,
    ])->assertRedirect(route('carrito.show'));

    $this->get(route('carrito.show'))
        ->assertOk()
        ->assertSee('3 cajas');

    $cart = app(Cart::class);
    // precio_caja 20000 * 3 = 60000
    expect($cart->subtotal())->toBe(60000);
});

test('cliente agrega producto modo unidad por unidades sin desperdicio', function () {
    $product = Product::factory()->unitMode()->create([
        'precio_cents' => 5000,
        'stock' => 10,
    ]);

    $this->post(route('carrito.add'), [
        'producto' => $product->slug,
        'cantidad' => 3,
    ])->assertRedirect(route('carrito.show'));

    $this->get(route('carrito.show'))
        ->assertOk()
        ->assertSee('3 unidades')
        ->assertSee('$150,00');

    expect(app(Cart::class)->subtotal())->toBe(15000);
});

test('modo unidad ignora desperdicio', function () {
    $product = Product::factory()->unitMode()->create([
        'precio_cents' => 5000,
        'stock' => 10,
    ]);

    $this->post(route('carrito.add'), [
        'producto' => $product->slug,
        'cantidad' => 2,
        'desperdicio' => true,
    ])->assertRedirect(route('carrito.show'));

    expect(app(Cart::class)->subtotal())->toBe(10000);
});

test('agregar el mismo producto dos veces acumula cantidad', function () {
    $product = Product::factory()->unitMode()->create(['stock' => 10, 'precio_cents' => 1000]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 2])->assertRedirect(route('carrito.show'));
    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 3])->assertRedirect(route('carrito.show'));

    $this->get(route('carrito.show'))->assertOk()->assertSee('5 unidades');
    expect(app(Cart::class)->subtotal())->toBe(5000);
});

test('exceder stock al acumular es rechazado', function () {
    $product = Product::factory()->unitMode()->create(['stock' => 3, 'precio_cents' => 1000]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 2])->assertRedirect(route('carrito.show'));
    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 2])
        ->assertRedirect()
        ->assertSessionHasErrors('producto');

    $this->get(route('carrito.show'))->assertOk()->assertSee('2 unidades');
});

test('validación 3 cajas disponibles, agregar 4 es rechazado', function () {
    $product = Product::factory()->m2Mode()->create(['stock' => 3, 'm2_por_caja' => '1.15', 'precio_cents' => 10000]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'superficie' => 10, 'desperdicio' => false])
        ->assertRedirect()
        ->assertSessionHasErrors('producto');
    // 10 m² /1.15 = ceil 9 cajas > 3 → rechazo -> carrito vacío
    $this->get(route('carrito.show'))->assertOk()->assertSee('Tu carrito está vacío');
});

test('actualizar cantidad a valor dentro de stock es ok', function () {
    $product = Product::factory()->unitMode()->create(['stock' => 10, 'precio_cents' => 1000]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 2])->assertRedirect(route('carrito.show'));

    $this->patch(route('carrito.update', $product), ['cantidad' => 5])
        ->assertRedirect(route('carrito.show'));

    expect(app(Cart::class)->subtotal())->toBe(5000);
});

test('actualizar cantidad excediendo stock es rechazado', function () {
    $product = Product::factory()->unitMode()->create(['stock' => 3, 'precio_cents' => 1000]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 2])->assertRedirect(route('carrito.show'));

    $this->patch(route('carrito.update', $product), ['cantidad' => 4])
        ->assertRedirect()
        ->assertSessionHasErrors('cantidad');

    expect(app(Cart::class)->items()[$product->id])->toBe(2);
});

test('actualizar cantidad a 0 elimina la línea', function () {
    $product = Product::factory()->unitMode()->create(['stock' => 10]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 2])->assertRedirect(route('carrito.show'));
    $this->patch(route('carrito.update', $product), ['cantidad' => 0])->assertRedirect(route('carrito.show'));

    $this->get(route('carrito.show'))->assertOk()->assertSee('Tu carrito está vacío');
});

test('producto sin stock no se puede agregar', function () {
    $product = Product::factory()->unitMode()->create(['stock' => 0]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 1])
        ->assertRedirect()
        ->assertSessionHasErrors('producto');
});

test('producto inactivo no se puede agregar', function () {
    $product = Product::factory()->inactive()->create(['stock' => 10]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 1])
        ->assertRedirect()
        ->assertSessionHasErrors('producto');
});

test('producto en carrito que pasa a inactivo queda no comprable y bloquea', function () {
    $product = Product::factory()->unitMode()->create(['stock' => 10, 'precio_cents' => 1000]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 2])->assertRedirect(route('carrito.show'));

    $product->update(['activo' => false]);

    $this->get(route('carrito.show'))
        ->assertOk()
        ->assertSee('Producto no disponible')
        ->assertSee('no están disponibles');

    $cart = app(Cart::class);
    expect($cart->hasUnpurchasable())->toBeTrue();
    expect($cart->subtotal())->toBe(0);
});

test('producto en carrito sin stock suficiente queda no comprable', function () {
    $product = Product::factory()->unitMode()->create(['stock' => 5, 'precio_cents' => 1000]);

    $this->post(route('carrito.add'), ['producto' => $product->slug, 'cantidad' => 3])->assertRedirect(route('carrito.show'));

    $product->update(['stock' => 2]);

    $this->get(route('carrito.show'))
        ->assertOk()
        ->assertSee('Stock insuficiente')
        ->assertSee('no están disponibles');

    $cart = app(Cart::class);
    expect($cart->hasUnpurchasable())->toBeTrue();
    expect($cart->subtotal())->toBe(0);
});

test('eliminar línea y vaciar carrito funcionan', function () {
    $p1 = Product::factory()->unitMode()->create(['stock' => 10]);
    $p2 = Product::factory()->unitMode()->create(['stock' => 10]);

    $this->post(route('carrito.add'), ['producto' => $p1->slug, 'cantidad' => 1])->assertRedirect(route('carrito.show'));
    $this->post(route('carrito.add'), ['producto' => $p2->slug, 'cantidad' => 1])->assertRedirect(route('carrito.show'));

    $this->delete(route('carrito.remove', $p1))->assertRedirect(route('carrito.show'));
    $this->get(route('carrito.show'))->assertOk()->assertSee($p2->name)->assertDontSee($p1->name);

    $this->delete(route('carrito.clear'))->assertRedirect(route('carrito.show'));
    $this->get(route('carrito.show'))->assertOk()->assertSee('Tu carrito está vacío');
});

test('subtotal suma líneas en ambas unidades y no expone total ni envío', function () {
    $m2 = Product::factory()->m2Mode()->create(['precio_cents' => 10000, 'm2_por_caja' => '1.00', 'stock' => 10]);
    $unidad = Product::factory()->unitMode()->create(['precio_cents' => 5000, 'stock' => 10]);

    $this->post(route('carrito.add'), ['producto' => $m2->slug, 'superficie' => 2])->assertRedirect(route('carrito.show'));
    $this->post(route('carrito.add'), ['producto' => $unidad->slug, 'cantidad' => 2])->assertRedirect(route('carrito.show'));

    $cart = app(Cart::class);
    // m2: precio_caja 10000*1=10000 *2 cajas=20000 ; unidad 5000*2=10000 ; subtotal 30000
    expect($cart->subtotal())->toBe(30000);

    $this->get(route('carrito.show'))
        ->assertOk()
        ->assertSee('Subtotal')
        ->assertDontSee('Envío')
        ->assertDontSee('Total');
});

test('carrito se navega sin autenticación', function () {
    $this->get(route('carrito.show'))->assertOk();
});

test('carrito usa layout site y muestra categorías', function () {
    Category::factory()->create(['name' => 'Porcelanatos', 'slug' => 'porcelanatos', 'sort_order' => 1]);

    $this->get(route('carrito.show'))->assertOk()->assertSee('Porcelanatos');
});

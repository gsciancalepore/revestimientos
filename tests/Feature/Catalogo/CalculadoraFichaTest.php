<?php

use App\Models\Product;

function fichaM2(): Product
{
    return Product::factory()->m2Mode()->create([
        'stock' => 50,
        'm2_por_caja' => '1.15',
    ]);
}

test('la ficha estima las cajas en el servidor con el mismo cálculo del carrito (HIG-13)', function (string $superficie, int $esperadas) {
    $product = fichaM2();

    $this->get('/productos/'.$product->slug.'?superficie='.$superficie.'&desperdicio=1')
        ->assertOk()
        ->assertSee($esperadas.' cajas')
        ->assertDontSee('calculadoraM2');
})->with([
    'once y media con desperdicio' => ['11.50', 11],
    'doble con desperdicio' => ['23.00', 22],
    'borde del desperdicio' => ['1.05', 2],
]);

test('la ficha estima desde largo y ancho en centímetros (HIG-13)', function () {
    $product = fichaM2();

    // 200 cm × 100 cm = 2 m² + 10 % = 2,2 m² → 2 cajas de 1,15.
    $this->get('/productos/'.$product->slug.'?largo=200&ancho=100&desperdicio=1')
        ->assertOk()
        ->assertSee('2 cajas')
        ->assertDontSee('calculadoraM2');
});

test('la ficha sin parámetros no estima ni trae la calculadora duplicada (HIG-13)', function () {
    $product = fichaM2();

    $this->get('/productos/'.$product->slug)
        ->assertOk()
        ->assertDontSee('calculadoraM2');
});

test('la ficha y el carrito devuelven las mismas cajas para la misma superficie (HIG-13)', function () {
    $product = fichaM2();

    $ficha = $this->get('/productos/'.$product->slug.'?superficie=11.50&desperdicio=1')->assertOk();
    $ficha->assertSee('11 cajas');

    $this->post(route('carrito.add'), [
        'producto' => $product->slug,
        'superficie' => 11.50,
        'desperdicio' => true,
    ])->assertRedirect(route('carrito.show'));

    $this->get(route('carrito.show'))->assertOk()->assertSee('11 cajas');
});

test('la ficha sin stock muestra el badge pero no el formulario de compra (HIG-21)', function () {
    $product = Product::factory()->m2Mode()->create(['stock' => 0]);

    $this->get('/productos/'.$product->slug)
        ->assertOk()
        ->assertSee('Sin stock')
        ->assertDontSee('Agregar al carrito');
});

test('la ficha con stock muestra el formulario de compra (HIG-21)', function () {
    $product = Product::factory()->m2Mode()->create(['stock' => 5]);

    $this->get('/productos/'.$product->slug)
        ->assertOk()
        ->assertSee('Agregar al carrito');
});

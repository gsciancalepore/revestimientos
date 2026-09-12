<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesSeeder;

/**
 * Spec 08 fase 08.c — regla 146: el stock negativo es un estado interno de
 * excepción. El cliente nunca lo ve; el panel muestra el valor real, porque es
 * exactamente cuánto hay que reponerle al fabricante.
 */
beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('un producto con stock negativo figura como sin stock en la ficha publica', function () {
    $producto = Product::factory()->create(['stock' => -3, 'activo' => true]);

    $this->get("/productos/{$producto->slug}")
        ->assertOk()
        ->assertSee('Sin stock')
        // Nunca una cantidad del lado del cliente: ni negativa, ni "Quedan -3".
        ->assertDontSee('Quedan');
});

test('un producto con stock negativo no se puede agregar al carrito', function () {
    $producto = Product::factory()->create(['stock' => -3, 'activo' => true]);

    $this->post('/carrito/agregar', ['product_id' => $producto->id, 'cantidad' => 1])
        ->assertSessionHasErrors();

    expect(session('cart'))->toBeNull();
});

test('el panel muestra el stock real, negativo incluido', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    Product::factory()->create(['stock' => -3, 'name' => 'Porcelanato Repuesto']);

    $this->actingAs($admin)
        ->get('/admin/productos')
        ->assertOk()
        ->assertSee('Porcelanato Repuesto')
        ->assertSee('-3');
});

test('el formulario de edicion acepta guardar un producto con stock negativo', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $categoria = Category::factory()->create();
    $producto = Product::factory()->create(['stock' => -3, 'category_id' => $categoria->id]);

    // Con `min:0`, el admin no podía guardar NINGÚN cambio —ni el precio, ni el
    // nombre, ni desactivarlo— sin llevar el stock a >= 0 en el mismo submit.
    $this->actingAs($admin)
        ->patch("/admin/productos/{$producto->slug}", [
            'category_id' => $categoria->id,
            'name' => 'Nombre corregido',
            'slug' => $producto->slug,
            'codigo' => $producto->codigo,
            'unidad_venta' => $producto->unidad_venta->value,
            'precio_cents' => 999900,
            'm2_por_caja' => $producto->m2_por_caja,
            'stock' => -3,
            'activo' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($producto->fresh()->name)->toBe('Nombre corregido')
        ->and($producto->fresh()->stock)->toBe(-3);
});

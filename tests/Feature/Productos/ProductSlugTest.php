<?php

use App\Enums\ProductSaleUnit;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('el slug se auto-genera del nombre al crear un producto', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Porcelanato Gris',
        'codigo' => 'ILV-40001',
        'precio_cents' => 100000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 1,
        'activo' => 1,
    ])->assertSessionHasNoErrors();

    $product = Product::where('codigo', 'ILV-40001')->firstOrFail();

    $this->assertSame('porcelanato-gris', $product->slug);
});

test('una colisión de slug al crear agrega un sufijo numérico', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'Porcelanato Gris', 'slug' => 'porcelanato-gris']);

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Porcelanato Gris',
        'codigo' => 'ILV-40002',
        'precio_cents' => 100000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 1,
        'activo' => 1,
    ])->assertSessionHasNoErrors();

    $product = Product::where('codigo', 'ILV-40002')->firstOrFail();

    $this->assertSame('porcelanato-gris-2', $product->slug);
});

test('el admin puede indicar un slug explícito al crear', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Porcelanato Gris',
        'slug' => 'porcelanato-especial',
        'codigo' => 'ILV-40003',
        'precio_cents' => 100000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 1,
        'activo' => 1,
    ])->assertSessionHasNoErrors();

    $product = Product::where('codigo', 'ILV-40003')->firstOrFail();

    $this->assertSame('porcelanato-especial', $product->slug);
});

test('el slug editable no cambia al editar sin modificarlo', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $product = Product::factory()->m2Mode()->create(['name' => 'Porcelanato Gris']);

    $this->actingAs($admin)->patch(route('productos.update', $product), [
        'category_id' => $product->category_id,
        'name' => 'Porcelanato Gris',
        'slug' => 'porcelanato-gris',
        'codigo' => $product->codigo,
        'precio_cents' => $product->precio_cents,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => $product->stock,
        'activo' => 1,
    ])->assertSessionHasNoErrors();

    $this->assertSame('porcelanato-gris', $product->refresh()->slug);
});

test('el admin puede editar el slug a uno ya existente con sufijo', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'Porcelanato Gris', 'slug' => 'porcelanato-gris']);
    $product = Product::factory()->create(['name' => 'Otro', 'category_id' => $category->id]);

    $this->actingAs($admin)->patch(route('productos.update', $product), [
        'category_id' => $product->category_id,
        'name' => 'Otro',
        'slug' => 'porcelanato-gris',
        'codigo' => $product->codigo,
        'precio_cents' => $product->precio_cents,
        'unidad_venta' => $product->unidad_venta->value,
        'm2_por_caja' => $product->m2_por_caja,
        'stock' => $product->stock,
        'activo' => 1,
    ])->assertSessionHasNoErrors();

    $this->assertSame('porcelanato-gris-2', $product->refresh()->slug);
});

test('un slug con caracteres inválidos es rechazado', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Porcelanato',
        'slug' => 'Mi Slug!',
        'codigo' => 'ILV-40004',
        'precio_cents' => 100000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 1,
    ])->assertSessionHasErrors('slug');
});

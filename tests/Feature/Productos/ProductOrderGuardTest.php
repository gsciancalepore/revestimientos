<?php

use App\Actions\DeleteCategoryAction;
use App\Actions\DeleteProductAction;
use App\Actions\UpdateProductAction;
use App\Enums\ProductSaleUnit;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

/**
 * Spec 03, regla 67: un producto solo se borra si no tiene pedidos, y su
 * `unidad_venta` no puede cambiar cuando existen líneas de pedido históricas
 * (la `cantidad` congelada se leería en otra unidad).
 */
function guardProduct(ProductSaleUnit $unidad = ProductSaleUnit::M2): Product
{
    return Product::factory()->create([
        'unidad_venta' => $unidad,
        'm2_por_caja' => $unidad === ProductSaleUnit::M2 ? '1.15' : null,
    ]);
}

function guardOrderLineFor(Product $product): OrderLine
{
    return OrderLine::factory()->create(['product_id' => $product->id]);
}

function guardUpdate(Product $product, ProductSaleUnit $unidad, ?string $name = null): Product
{
    return app(UpdateProductAction::class)->execute(
        product: $product,
        categoryId: $product->category_id,
        name: $name ?? $product->name,
        codigo: $product->codigo,
        unidadVenta: $unidad,
        precioCents: $product->precio_cents,
        m2PorCaja: $unidad === ProductSaleUnit::M2 ? '1.15' : null,
        stock: $product->stock,
        activo: $product->activo,
    );
}

test('un producto sin pedidos se borra', function () {
    $product = guardProduct();

    app(DeleteProductAction::class)->execute($product);

    expect(Product::query()->find($product->id))->toBeNull();
});

test('un producto con pedidos no se borra y lanza DomainException', function () {
    $product = guardProduct();
    guardOrderLineFor($product);

    expect(fn () => app(DeleteProductAction::class)->execute($product))
        ->toThrow(DomainException::class);

    expect(Product::query()->find($product->id))->not->toBeNull();
});

test('cambiar unidad_venta en un producto con pedidos lanza DomainException y no muta', function () {
    $product = guardProduct(ProductSaleUnit::M2);
    guardOrderLineFor($product);

    expect(fn () => guardUpdate($product, ProductSaleUnit::Unidad))
        ->toThrow(DomainException::class);

    expect($product->fresh()->unidad_venta)->toBe(ProductSaleUnit::M2);
});

test('cambiar unidad_venta en un producto sin pedidos está permitido', function () {
    $product = guardProduct(ProductSaleUnit::M2);

    guardUpdate($product, ProductSaleUnit::Unidad);

    expect($product->fresh()->unidad_venta)->toBe(ProductSaleUnit::Unidad);
});

test('actualizar otros campos de un producto con pedidos está permitido', function () {
    $product = guardProduct(ProductSaleUnit::M2);
    guardOrderLineFor($product);

    guardUpdate($product, ProductSaleUnit::M2, 'Nombre actualizado');

    expect($product->fresh()->name)->toBe('Nombre actualizado');
});

test('el admin recibe un error de dominio, no un 500, al borrar un producto con pedidos', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $product = guardProduct();
    guardOrderLineFor($product);

    $this->actingAs($admin)
        ->delete(route('productos.destroy', $product))
        ->assertRedirect(route('productos.index'))
        ->assertSessionHasErrors('delete');

    expect(Product::query()->find($product->id))->not->toBeNull();
});

test('el admin recibe un error de dominio, no un 500, al cambiar unidad_venta con pedidos', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $product = guardProduct(ProductSaleUnit::M2);
    guardOrderLineFor($product);

    $this->actingAs($admin)
        ->put(route('productos.update', $product), [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'codigo' => $product->codigo,
            'unidad_venta' => ProductSaleUnit::Unidad->value,
            'precio_cents' => $product->precio_cents,
            'stock' => $product->stock,
            'activo' => true,
        ])
        ->assertRedirect(route('productos.index'))
        ->assertSessionHasErrors('unidad_venta');

    expect($product->fresh()->unidad_venta)->toBe(ProductSaleUnit::M2);
});

test('borrar una categoria sigue bloqueado por sus productos', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    expect(fn () => app(DeleteCategoryAction::class)->execute($category))
        ->toThrow(DomainException::class);
});

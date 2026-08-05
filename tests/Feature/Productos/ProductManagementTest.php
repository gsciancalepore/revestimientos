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

test('guests are redirected to the login page when accessing the products area', function () {
    $this->get('/admin/productos')->assertRedirect('/admin/login');
});

test('only admins can view the products index', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get('/admin/productos')->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('only admins can view the create product page', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get('/admin/productos/create')->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('only admins can view the edit product page', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();
    $product = Product::factory()->create();

    $this->actingAs($actor)->get(route('productos.edit', $product))->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('only admins can delete products', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();
    $product = Product::factory()->create();

    $this->actingAs($actor)->delete(route('productos.destroy', $product))->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('admins can view the products index with category names', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create(['name' => 'Porcelanatos']);
    Product::factory()->m2Mode()->create(['name' => 'Porcelanato Gris', 'category_id' => $category->id]);

    $this->actingAs($admin)
        ->get('/admin/productos')
        ->assertOk()
        ->assertSee('Porcelanato Gris')
        ->assertSee('Porcelanatos');
});

test('admins can create a product in m2 mode', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create(['slug' => 'porcelanatos']);

    $response = $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Porcelanato Gris',
        'marca' => 'Weber',
        'codigo' => 'ILV-12345',
        'precio_cents' => 2009820,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 10,
        'activo' => 1,
        'specs' => ['medida' => '60x60', 'acabado' => 'brillante'],
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('productos.index', absolute: false));

    $product = Product::where('codigo', 'ILV-12345')->firstOrFail();

    $this->assertSame(ProductSaleUnit::M2, $product->unidad_venta);
    $this->assertSame('1.15', $product->m2_por_caja);
    $this->assertSame(['medida' => '60x60', 'acabado' => 'brillante'], $product->specs);
    $this->assertSame(2311293, $product->precioCajaCents());
});

test('admins can create a product in unit mode without m2 per box', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create(['slug' => 'pastinas']);

    $response = $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Pastina Blanca',
        'marca' => 'Weber',
        'codigo' => 'ILV-54321',
        'precio_cents' => 15000,
        'unidad_venta' => ProductSaleUnit::Unidad->value,
        'stock' => 25,
        'activo' => 1,
        'specs' => ['color' => 'blanco', 'peso' => '5'],
    ]);

    $response->assertSessionHasNoErrors();

    $product = Product::where('codigo', 'ILV-54321')->firstOrFail();

    $this->assertSame(ProductSaleUnit::Unidad, $product->unidad_venta);
    $this->assertNull($product->m2_por_caja);
    $this->assertNull($product->precioCajaCents());
});

test('a product in m2 mode requires m2 per box', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Porcelanato',
        'codigo' => 'ILV-11111',
        'precio_cents' => 100000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'stock' => 1,
    ])->assertSessionHasErrors('m2_por_caja');

    $this->assertDatabaseCount('products', 0);
});

test('a duplicate product code is rejected', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    Product::factory()->create(['codigo' => 'ILV-12345']);

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => Category::factory()->create()->id,
        'name' => 'Otro',
        'codigo' => 'ILV-12345',
        'precio_cents' => 100000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 1,
    ])->assertSessionHasErrors('codigo');
});

test('specs keys are validated against the category family', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create(['slug' => 'porcelanatos']);

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Porcelanato',
        'codigo' => 'ILV-22222',
        'precio_cents' => 100000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 1,
        'specs' => ['peso' => '10'],
    ])->assertSessionHasErrors('specs');
});

test('specs keys not allowed for the family are rejected', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create(['slug' => 'adhesivos']);

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Adhesivo',
        'codigo' => 'ILV-33333',
        'precio_cents' => 25000,
        'unidad_venta' => ProductSaleUnit::Unidad->value,
        'stock' => 5,
        'specs' => ['tiempo_de_fraguado' => '24h', 'peso' => '25'],
    ])->assertSessionHasNoErrors();
});

test('admins can update a product and price changes are audited', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $product = Product::factory()->m2Mode()->create(['precio_cents' => 100000, 'stock' => 5]);

    $response = $this->actingAs($admin)->patch(route('productos.update', $product), [
        'category_id' => $product->category_id,
        'name' => $product->name,
        'codigo' => $product->codigo,
        'precio_cents' => 150000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 3,
        'activo' => 1,
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('productos.index', absolute: false));

    $product->refresh();

    $this->assertSame(150000, $product->precio_cents);
    $this->assertSame(3, $product->stock);

    $this->assertDatabaseHas('audit_logs', ['action' => 'product.price_changed', 'subject_id' => $product->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'product.stock_changed', 'subject_id' => $product->id]);
});

test('updating a product in unit mode clears m2 per box', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $product = Product::factory()->m2Mode()->create();

    $this->actingAs($admin)->patch(route('productos.update', $product), [
        'category_id' => $product->category_id,
        'name' => $product->name,
        'codigo' => $product->codigo,
        'precio_cents' => $product->precio_cents,
        'unidad_venta' => ProductSaleUnit::Unidad->value,
        'stock' => $product->stock,
        'activo' => 1,
    ])->assertSessionHasNoErrors();

    $this->assertNull($product->refresh()->m2_por_caja);
});

test('admins can deactivate a product and it is audited', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $product = Product::factory()->create();

    $this->actingAs($admin)->patch(route('productos.update', $product), [
        'category_id' => $product->category_id,
        'name' => $product->name,
        'codigo' => $product->codigo,
        'precio_cents' => $product->precio_cents,
        'unidad_venta' => $product->unidad_venta->value,
        'm2_por_caja' => $product->m2_por_caja,
        'stock' => $product->stock,
        'activo' => 0,
    ])->assertSessionHasNoErrors();

    $this->assertFalse($product->refresh()->activo);
    $this->assertDatabaseHas('audit_logs', ['action' => 'product.deactivated', 'subject_id' => $product->id]);
});

test('admins can delete a product', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($admin)->delete(route('productos.destroy', $product));

    $response
        ->assertRedirect(route('productos.index', absolute: false))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('creating a product records an audit entry', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create(['slug' => 'ceramicas']);

    $this->actingAs($admin)->post('/admin/productos', [
        'category_id' => $category->id,
        'name' => 'Cerámica',
        'codigo' => 'ILV-99999',
        'precio_cents' => 100000,
        'unidad_venta' => ProductSaleUnit::M2->value,
        'm2_por_caja' => '1.15',
        'stock' => 2,
        'activo' => 1,
    ])->assertSessionHasNoErrors();

    $product = Product::where('codigo', 'ILV-99999')->firstOrFail();

    $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'subject_id' => $product->id]);
});

test('a category with products cannot be deleted', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    $response = $this->actingAs($admin)->delete(route('categorias.destroy', $category));

    $response
        ->assertRedirect(route('categorias.index', absolute: false))
        ->assertSessionHasErrors('delete');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

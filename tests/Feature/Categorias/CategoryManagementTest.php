<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategoriesSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('guests are redirected to the login page when accessing the categories area', function () {
    $this->get('/admin/categorias')->assertRedirect('/admin/login');
});

test('only admins can view the categories index', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get('/admin/categorias')->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('only admins can view the create category page', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();

    $this->actingAs($actor)->get('/admin/categorias/create')->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('only admins can view the edit category page', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();
    $category = Category::factory()->create();

    $this->actingAs($actor)->get(route('categorias.edit', $category))->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('only admins can delete categories', function (UserRole $role) {
    $actor = User::factory()->withRole($role)->create();
    $category = Category::factory()->create();

    $this->actingAs($actor)->delete(route('categorias.destroy', $category))->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'deposito' => UserRole::Deposito,
]);

test('admins can view the categories index as a flat list', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    Category::factory()->create(['name' => 'Ceramicas']);
    Category::factory()->create(['name' => 'Porcelanatos']);

    $this->actingAs($admin)
        ->get('/admin/categorias')
        ->assertOk()
        ->assertSee('Ceramicas')
        ->assertSee('Porcelanatos');
});

test('admins can create a category with an auto-generated slug', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $response = $this->actingAs($admin)->post('/admin/categorias', [
        'name' => 'Ceramicas',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categorias.index', absolute: false));

    $category = Category::where('name', 'Ceramicas')->firstOrFail();

    $this->assertSame('ceramicas', $category->slug);
});

test('creating a category rejects a duplicate name', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    Category::factory()->create(['name' => 'Ceramicas']);

    $this->actingAs($admin)->post('/admin/categorias', [
        'name' => 'Ceramicas',
    ])->assertSessionHasErrors('name');
});

test('creating a category rejects a duplicate slug', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    Category::factory()->create(['slug' => 'ceramicas']);

    $this->actingAs($admin)->post('/admin/categorias', [
        'name' => 'Cerámicas',
        'slug' => 'ceramicas',
    ])->assertSessionHasErrors('slug');
});

test('an auto-generated slug is made unique by appending a suffix', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    Category::factory()->create(['slug' => 'ceramicas']);

    $this->actingAs($admin)->post('/admin/categorias', [
        'name' => 'Ceramicas',
    ])->assertSessionHasNoErrors();

    $category = Category::orderByDesc('id')->firstOrFail();

    $this->assertSame('ceramicas-2', $category->slug);
});

test('admins can update a category name, slug and order', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = Category::factory()->create(['name' => 'Ceramicas', 'slug' => 'ceramicas']);

    $response = $this->actingAs($admin)->patch(route('categorias.update', $target), [
        'name' => 'Porcelanatos',
        'slug' => 'porcelanatos',
        'sort_order' => 3,
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categorias.index', absolute: false));

    $target->refresh();

    $this->assertSame('Porcelanatos', $target->name);
    $this->assertSame('porcelanatos', $target->slug);
    $this->assertSame(3, $target->sort_order);
});

test('updating a category rejects a duplicate name', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = Category::factory()->create(['name' => 'Ceramicas']);
    Category::factory()->create(['name' => 'Porcelanatos']);

    $this->actingAs($admin)->patch(route('categorias.update', $target), [
        'name' => 'Porcelanatos',
    ])->assertSessionHasErrors('name');
});

test('updating a category allows keeping its own slug', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $target = Category::factory()->create(['name' => 'Ceramicas']);

    $response = $this->actingAs($admin)->patch(route('categorias.update', $target), [
        'name' => 'Ceramicas',
        'slug' => $target->slug,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertSame($target->slug, $target->refresh()->slug);
});

test('admins can delete an empty category', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($admin)->delete(route('categorias.destroy', $category));

    $response
        ->assertRedirect(route('categorias.index', absolute: false))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('the categories seeder is idempotent and builds the flat business categories', function () {
    $this->seed(CategoriesSeeder::class);

    $this->assertDatabaseCount('categories', 4);

    foreach (['porcelanatos', 'ceramicas', 'pastinas', 'adhesivos'] as $index => $slug) {
        $this->assertDatabaseHas('categories', ['slug' => $slug, 'sort_order' => $index]);
    }

    $this->seed(CategoriesSeeder::class);

    $this->assertDatabaseCount('categories', 4);
});

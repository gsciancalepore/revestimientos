<?php

use App\Models\Category;
use App\Models\Product;

test('la home muestra las categorías en orden de sort_order y los destacados con oferta', function () {
    Category::factory()->create(['name' => 'Cerámicas', 'slug' => 'ceramicas', 'sort_order' => 2]);
    Category::factory()->create(['name' => 'Porcelanatos', 'slug' => 'porcelanatos', 'sort_order' => 1]);
    Product::factory()->conOferta()->create(['name' => 'Destacado Gris']);
    Product::factory()->create(['name' => 'Sin Oferta']);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Porcelanatos', 'Cerámicas'])
        ->assertSee('Destacado Gris')
        ->assertDontSee('Sin Oferta');
});

test('el catálogo solo publica productos activos', function () {
    Product::factory()->create(['name' => 'Activo Visible']);
    Product::factory()->inactive()->create(['name' => 'Inactivo Oculto']);

    $this->get('/catalogo')
        ->assertOk()
        ->assertSee('Activo Visible')
        ->assertDontSee('Inactivo Oculto');
});

test('la ficha de un producto inactivo responde 404', function () {
    $product = Product::factory()->inactive()->create(['name' => 'Inactivo Oculto']);

    $this->get('/productos/'.$product->slug)->assertNotFound();
});

test('la ficha de un slug inexistente responde 404', function () {
    $this->get('/productos/slug-que-no-existe')->assertNotFound();
});

test('la ficha se accede por slug y muestra nombre, marca, specs, precio y stock', function () {
    $category = Category::factory()->create(['name' => 'Porcelanatos', 'slug' => 'porcelanatos']);
    $product = Product::factory()->m2Mode()->conOferta()->create([
        'name' => 'Porcelanato Gris',
        'marca' => 'Weber',
        'category_id' => $category->id,
        'stock' => 12,
        'descripcion' => 'Porcelanato pulido',
        'specs' => ['medida' => '60x60', 'acabado' => 'brillante'],
    ]);

    $this->get('/productos/'.$product->slug)
        ->assertOk()
        ->assertSee('Porcelanato Gris')
        ->assertSee('Weber')
        ->assertSee('Porcelanatos')
        ->assertSee('Porcelanato pulido')
        ->assertSee('medida')
        ->assertSee('60x60')
        ->assertSee('Quedan 12 cajas');
});

test('la ficha muestra el precio de oferta con descuento', function () {
    $product = Product::factory()->conOferta()->create(['name' => 'En oferta']);

    $this->get('/productos/'.$product->slug)
        ->assertOk()
        ->assertSee('25 % OFF');
});

test('un producto sin stock se muestra con el badge Sin stock', function () {
    $product = Product::factory()->create(['name' => 'Agotado', 'stock' => 0]);

    $this->get('/productos/'.$product->slug)
        ->assertOk()
        ->assertSee('Sin stock');

    $this->get('/catalogo')
        ->assertOk()
        ->assertSee('Agotado')
        ->assertSee('Sin stock');
});

test('la calculadora de m² aparece solo en productos modo m²', function () {
    $porM2 = Product::factory()->m2Mode()->create(['name' => 'Por m²']);
    $porUnidad = Product::factory()->unitMode()->create(['name' => 'Por unidad']);

    $this->get('/productos/'.$porM2->slug)->assertOk()->assertSee('Calculadora');
    $this->get('/productos/'.$porUnidad->slug)->assertOk()->assertDontSee('Calculadora');
});

test('el listado filtra por categoría mediante la URL con slug', function () {
    $porcelanatos = Category::factory()->create(['slug' => 'porcelanatos']);
    $ceramicas = Category::factory()->create(['slug' => 'ceramicas']);
    Product::factory()->create(['name' => 'Porcelanato A', 'category_id' => $porcelanatos->id]);
    Product::factory()->create(['name' => 'Cerámica B', 'category_id' => $ceramicas->id]);

    $this->get('/categorias/porcelanatos')
        ->assertOk()
        ->assertSee('Porcelanato A')
        ->assertDontSee('Cerámica B');
});

test('una categoría sin productos activos muestra un listado vacío sin 404', function () {
    $category = Category::factory()->create(['slug' => 'pastinas']);

    $this->get('/categorias/pastinas')
        ->assertOk()
        ->assertSee('No se encontraron productos.');
});

test('el listado de ofertas solo muestra productos con oferta activa', function () {
    Product::factory()->conOferta()->create(['name' => 'En oferta']);
    Product::factory()->create(['name' => 'Precio normal']);

    $this->get('/ofertas')
        ->assertOk()
        ->assertSee('En oferta')
        ->assertDontSee('Precio normal');
});

test('un precio de oferta mayor o igual al de lista no se considera oferta', function () {
    $product = Product::factory()->create(['name' => 'Precio raro', 'precio_oferta_cents' => 250000, 'precio_cents' => 200000]);

    $this->get('/ofertas')->assertOk()->assertDontSee('Precio raro');

    $this->get('/productos/'.$product->slug)
        ->assertOk()
        ->assertSee('Precio raro')
        ->assertDontSee('% OFF');
});

test('la búsqueda hace coincidencia parcial en nombre, código y marca', function () {
    Product::factory()->create(['name' => 'Piso de Mármol', 'codigo' => 'ILV-00001', 'marca' => 'Roca']);

    $this->get('/catalogo?q=mÁrmol')->assertOk()->assertSee('Piso de Mármol');
    $this->get('/catalogo?q=ILV-00001')->assertOk()->assertSee('Piso de Mármol');
    $this->get('/catalogo?q=roca')->assertOk()->assertSee('Piso de Mármol');
    $this->get('/catalogo?q=inexistente')->assertOk()->assertDontSee('Piso de Mármol');
});

test('los filtros de marca, oferta y specs son combinables', function () {
    $category = Category::factory()->create(['slug' => 'porcelanatos']);
    Product::factory()->m2Mode()->conOferta()->create([
        'name' => 'Gris combinado',
        'category_id' => $category->id,
        'marca' => 'Roca',
        'specs' => ['medida' => '60x60'],
    ]);
    Product::factory()->m2Mode()->create([
        'name' => 'Beige otro',
        'category_id' => $category->id,
        'marca' => 'Roca',
        'specs' => ['medida' => '60x60'],
    ]);

    $this->get('/categorias/porcelanatos?marca=Roca&oferta=1&specs[medida]=60x60')
        ->assertOk()
        ->assertSee('Gris combinado')
        ->assertDontSee('Beige otro');
});

test('los filtros de specs solo se ofrecen dentro de una categoría', function () {
    $category = Category::factory()->create(['slug' => 'porcelanatos']);
    Product::factory()->create([
        'name' => 'Con medida',
        'category_id' => $category->id,
        'specs' => ['medida' => '60x60'],
    ]);

    $this->get('/catalogo')->assertOk()->assertDontSee('Atributos');
    $this->get('/categorias/porcelanatos')->assertOk()->assertSee('Atributos');
});

test('el listado pagina de a 12 productos', function () {
    Product::factory()->count(13)->create();

    $this->get('/catalogo')->assertOk()->assertSee('13 productos');
});

test('el catálogo se navega sin autenticación', function () {
    Product::factory()->create(['name' => 'Público']);

    $this->get('/')->assertOk();
    $this->get('/catalogo')->assertOk()->assertSee('Público');
    $this->get('/ofertas')->assertOk();
});

<?php

use App\Models\Category;
use App\Models\Product;

test('la home muestra las categorías en orden de sort_order y los destacados con oferta', function () {
    Category::factory()->create(['name' => 'Cerámicas', 'slug' => 'ceramicas', 'sort_order' => 2]);
    Category::factory()->create(['name' => 'Porcelanatos', 'slug' => 'porcelanatos', 'sort_order' => 1]);
    Product::factory()->conOferta()->create(['name' => 'Destacado Gris']);
    Product::factory()->create(['name' => 'Sin Oferta']);

    // Desde la regla 167 (Spec 04, sincronía 2026-09-20) "Sin Oferta" sí
    // aparece en la home, en la sección "Productos" (productos recientes sin
    // exigir oferta) — antes de esa regla no se veía en ningún lado de la
    // home. Que "Destacados" siga exigiendo oferta activa (regla 72) se
    // prueba aparte, en el test siguiente, sin depender de la sección nueva.
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Porcelanatos', 'Cerámicas'])
        ->assertSee('Destacado Gris')
        ->assertSee('Sin Oferta');
});

test('la sección de destacados sigue exigiendo oferta activa, independiente de la sección de productos (regla 72, no la toca la regla 167)', function () {
    // Con oferta pero fuera de los 8 más recientes: si aparece en la home,
    // solo puede ser porque "Destacados" lo muestra por mérito propio, no
    // porque la sección "Productos" (regla 167, limit 8) lo alcance.
    Product::factory()->conOferta()->create([
        'name' => 'Con Oferta Vieja',
        'created_at' => now()->subDays(30),
    ]);

    // Sin oferta y también fuera de los 8 más recientes: si esto aparece en
    // algún lado, "Destacados" dejó de exigir oferta activa.
    Product::factory()->create([
        'name' => 'Sin Oferta Vieja',
        'created_at' => now()->subDays(31),
    ]);

    // Ocho productos recientes que desplazan a los dos de arriba fuera del
    // límite de "Productos" (regla 167).
    Product::factory()->count(8)->create(['created_at' => now()]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Con Oferta Vieja')
        ->assertDontSee('Sin Oferta Vieja');
});

test('la home muestra productos activos recientes aunque no tengan oferta (regla 167)', function () {
    Product::factory()->create(['name' => 'Único Cargado']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Único Cargado');
});

test('la home no muestra productos inactivos en la sección de productos recientes (regla 167)', function () {
    Product::factory()->inactive()->create(['name' => 'Inactivo No Debe Verse']);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Inactivo No Debe Verse');
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

test('la ficha de producto usa layout site y muestra la barra de categorías (identidad visual pública, regla 4)', function () {
    $categoriaDelProducto = Category::factory()->create(['name' => 'Porcelanatos', 'slug' => 'porcelanatos', 'sort_order' => 1]);
    Category::factory()->create(['name' => 'Cerámicas', 'slug' => 'ceramicas', 'sort_order' => 2]);
    $product = Product::factory()->create(['category_id' => $categoriaDelProducto->id]);

    // "Cerámicas" no tiene relación con el producto: solo puede aparecer si el
    // layout recibe :categorias y renderiza la barra de navegación completa,
    // no el breadcrumb (que solo muestra la categoría propia del producto).
    $this->get('/productos/'.$product->slug)
        ->assertOk()
        ->assertSee('Cerámicas');
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

test('el listado pagina de a 12 tarjetas (HIG-29)', function () {
    foreach (range(1, 13) as $i) {
        Product::factory()->create(['name' => sprintf('Prod %02d', $i)]);
    }

    // El "13 productos" sale del total del conjunto, no del tamaño de página:
    // lo que prueba la paginación es cuántas tarjetas se renderizan.
    $pagina1 = $this->get('/catalogo')->assertOk()->assertSee('13 productos')->getContent();
    expect(substr_count($pagina1, 'group flex flex-col overflow-hidden'))->toBe(12);

    $pagina2 = $this->get('/catalogo?page=2')->assertOk()->getContent();
    expect(substr_count($pagina2, 'group flex flex-col overflow-hidden'))->toBe(1);
});

test('el listado ordena por nombre y no por inserción (HIG-29)', function () {
    Product::factory()->create(['name' => 'Zeta Final']);
    Product::factory()->create(['name' => 'Alfa Primera']);
    Product::factory()->create(['name' => 'Media Intermedia']);

    // El orden alfabético difiere del orden de inserción a propósito: sin el
    // `orderBy('name')`, `paginate()` devolvería las filas ordenadas por id.
    $this->get('/catalogo')->assertOk()
        ->assertSeeInOrder(['Alfa Primera', 'Media Intermedia', 'Zeta Final']);
});

test('el catálogo se navega sin autenticación', function () {
    Product::factory()->create(['name' => 'Público']);

    $this->get('/')->assertOk();
    $this->get('/catalogo')->assertOk()->assertSee('Público');
    $this->get('/ofertas')->assertOk();
});

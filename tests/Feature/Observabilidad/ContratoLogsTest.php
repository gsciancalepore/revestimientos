<?php

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Spec observabilidad-01 — contrato de logs v1: forma de la línea (OBS-01),
 * canal de entrega (OBS-02), correlación (OBS-03), resumen por request
 * (OBS-04), redacción (OBS-06) y tolerancia a fallos de escritura (OBS-08).
 */

// --- OBS-01, OBS-02, OBS-03: forma, archivo y correlación ------------------

it('escribe líneas que validan contra el contrato, en el archivo del día UTC y con el request_id de la respuesta', function () {
    $dir = canalDeContrato();

    $respuesta = $this->get('/catalogo')->assertOk();

    $requestId = $respuesta->headers->get('X-Request-Id');
    expect(Str::isUuid($requestId))->toBeTrue();

    expect(glob($dir.'/app-*.jsonl'))->toBe([$dir.'/app-'.now('UTC')->format('Y-m-d').'.jsonl']);

    $lineas = lineasDelContrato($dir);
    expect($lineas)->not->toBeEmpty();

    foreach ($lineas as $linea) {
        expect($linea['request_id'])->toBe($requestId)
            ->and($linea['schema_version'])->toBe('1')
            ->and($linea['service'])->toBe('revestimientos');
    }
});

it('nunca adopta un X-Request-Id entrante', function () {
    canalDeContrato();

    $requestId = $this->get('/catalogo', ['X-Request-Id' => 'falso'])->headers->get('X-Request-Id');

    expect($requestId)->not->toBe('falso')
        ->and(Str::isUuid($requestId))->toBeTrue();
});

it('dos requests del mismo proceso tienen request_id distintos y no comparten contexto', function () {
    $dir = canalDeContrato();

    $primero = $this->get('/catalogo')->headers->get('X-Request-Id');
    $segundo = $this->get('/')->headers->get('X-Request-Id');

    expect($primero)->not->toBe($segundo);

    $resumenes = eventosDelContrato($dir, 'http.request');
    expect(array_column($resumenes, 'request_id'))->toBe([$primero, $segundo]);
});

// --- OBS-04: resumen por request -------------------------------------------

it('resume cada request con método, ruta, estado y duración, sin el query string', function () {
    $dir = canalDeContrato();

    $this->get('/catalogo?categoria=x')->assertOk();

    $resumen = eventosDelContrato($dir, 'http.request')[0];

    expect($resumen['attributes'])->toMatchArray([
        'method' => 'GET',
        'route' => 'catalogo.index',
        'status' => 200,
    ])
        ->and($resumen['attributes']['duration_ms'])->toBeInt()
        ->and($resumen['attributes'])->not->toHaveKey('user_id')
        ->and(textoDelDirectorio($dir))->not->toContain('categoria=x');
});

it('el resumen lleva user_id solo con un usuario interno autenticado', function () {
    $dir = canalDeContrato();
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->get('/admin/pedidos')->assertOk();

    expect(eventosDelContrato($dir, 'http.request')[0]['attributes']['user_id'])->toBe($admin->id);
});

// --- OBS-06 y OBS-05.8: redacción y líneas ajenas al catálogo --------------

it('redacta los datos personales por clave exacta en el canal app, a cualquier profundidad', function () {
    $dir = canalDeContrato();

    Log::info('x', [
        'customer_email' => 'centinela@ejemplo.test',
        'datos' => ['phone' => '5491100000000'],
        'product_name' => 'P',
    ]);

    $linea = eventosDelContrato($dir, 'app.log')[0];

    expect($linea['attributes']['message'])->toBe('x')
        ->and($linea['attributes']['context'])->toBe([
            'customer_email' => '[redactado]',
            'datos' => ['phone' => '[redactado]'],
            'product_name' => 'P',
        ])
        ->and($linea['level'])->toBe('info');
});

it('redacta también en el canal single que usa staging', function () {
    $dir = sys_get_temp_dir().'/contrato-logs-'.Str::uuid();
    mkdir($dir);
    config(['logging.channels.single.path' => $dir.'/laravel.log']);
    app('log')->forgetChannel('single');

    Log::channel('single')->info('x', [
        'customer_email' => 'centinela@ejemplo.test',
        'datos' => ['phone' => '5491100000000'],
    ]);

    expect(textoDelDirectorio($dir))
        ->toContain('[redactado]')
        ->not->toContain('centinela@ejemplo.test')
        ->not->toContain('5491100000000');
});

it('trunca el texto de una línea ajena al catálogo', function () {
    $dir = canalDeContrato();

    Log::warning(str_repeat('a', 400));

    expect(mb_strlen(eventosDelContrato($dir, 'app.log')[0]['attributes']['message']))->toBe(256);
});

it('traduce los niveles de Monolog a los cinco del contrato', function () {
    $dir = canalDeContrato();

    Log::notice('n');
    Log::alert('a');
    Log::emergency('e');

    expect(array_column(eventosDelContrato($dir, 'app.log'), 'level'))->toBe(['info', 'critical', 'critical']);
});

// --- OBS-08: el log nunca rompe el negocio ---------------------------------

it('si no se puede escribir el log, el checkout responde igual y crea el pedido una sola vez', function () {
    canalDeContrato('/proc/sin-permiso/logs');
    $product = Product::factory()->create(['stock' => 10]);
    putCartMp($product, 1);

    $this->post(route('checkout.store'), checkoutPayload(['payment_method' => 'transferencia']))
        ->assertRedirect(route('checkout.success'));

    expect(Order::count())->toBe(1);
});

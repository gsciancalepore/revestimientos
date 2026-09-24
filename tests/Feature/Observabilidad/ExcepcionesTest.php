<?php

use App\Actions\ConfirmPaymentAction;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Spec observabilidad-01 — excepciones sin texto peligroso (OBS-07) y sin
 * datos personales en ningún canal (OBS-06).
 */
const CENTINELAS = ['Nombre Centinela', 'centinela@ejemplo.test', '5491100000000', '9999', 'Calle Centinela 123'];

beforeEach(function () {
    Session::flush();
});

function checkoutConCentinelas(): void
{
    putCartMp(Product::factory()->create(['stock' => 10]), 1);

    test()->post(route('checkout.store'), checkoutPayload([
        'payment_method' => 'transferencia',
        'customer_name' => 'Nombre Centinela',
        'customer_email' => 'centinela@ejemplo.test',
        'customer_phone' => '5491100000000',
        'shipping_cp' => '9999',
        'shipping_address' => 'Calle Centinela 123',
    ]));
}

/**
 * Hace fallar el INSERT del pedido con los datos del cliente en el SQL, que
 * es como una `QueryException` filtraba datos personales al log (B1).
 */
function forzarQueryExceptionConCentinelas(): void
{
    Order::creating(function (Order $order) {
        DB::table('orders')->insert([
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
        ]);
    });
}

it('un checkout completo no deja ningún dato personal en el log', function () {
    $dir = canalDeContrato();

    checkoutConCentinelas();

    expect(Order::count())->toBe(1);

    foreach (CENTINELAS as $centinela) {
        expect(textoDelDirectorio($dir))->not->toContain($centinela);
    }
});

it('una QueryException con datos personales deja una sola entrada app.exception con sqlstate, sin mensaje y sin argumentos', function () {
    $dir = canalDeContrato();
    forzarQueryExceptionConCentinelas();

    checkoutConCentinelas();

    $lineas = lineasDelContrato($dir);
    $excepciones = array_values(array_filter($lineas, fn (array $l): bool => $l['event'] === 'app.exception'));

    expect($excepciones)->toHaveCount(1)
        ->and(array_filter($lineas, fn (array $l): bool => $l['level'] === 'error'))->toHaveCount(1);

    $error = $excepciones[0]['error'];
    expect($error['message'])->toBeNull()
        ->and($error['sqlstate'])->toBe('23502')
        ->and($error['trace'])->not->toBeEmpty();

    foreach ($error['trace'] as $marco) {
        expect($marco)->toMatch('/^[^()]*:\d+$/');
    }

    foreach (CENTINELAS as $centinela) {
        expect(textoDelDirectorio($dir))->not->toContain($centinela);
    }
});

it('el canal single de staging tampoco recibe los datos personales de la excepción', function () {
    $dir = sys_get_temp_dir().'/contrato-logs-'.Str::uuid();
    mkdir($dir);
    config(['logging.default' => 'stack', 'logging.channels.stack.channels' => ['single'], 'logging.channels.single.path' => $dir.'/laravel.log']);
    app('log')->forgetChannel('stack');
    app('log')->forgetChannel('single');
    forzarQueryExceptionConCentinelas();

    checkoutConCentinelas();

    expect(textoDelDirectorio($dir))->toContain('app.exception');

    foreach (CENTINELAS as $centinela) {
        expect(textoDelDirectorio($dir))->not->toContain($centinela);
    }
});

it('conserva el mensaje solo de las excepciones propias de la app', function () {
    $dir = canalDeContrato();
    Route::get('/_obs/libreria', fn () => throw new RuntimeException('texto de una librería'));
    Route::get('/_obs/propia', fn () => app(ConfirmPaymentAction::class)->execute(pedidoDePanel(), 'origen-invalido'));

    $this->get('/_obs/libreria')->assertStatus(500);
    $this->get('/_obs/propia')->assertStatus(500);

    $errores = array_column(eventosDelContrato($dir, 'app.exception'), 'error');

    expect($errores[0])->toMatchArray(['class' => RuntimeException::class, 'message' => null])
        ->and($errores[1])->toMatchArray(['class' => DomainException::class, 'message' => 'El origen de la confirmación no es válido.']);
});

it('una DomainException que no se lanzó desde app/ no conserva su mensaje', function () {
    $dir = canalDeContrato();
    Route::get('/_obs/dominio-ajeno', fn () => throw new DomainException('texto libre de una librería'));

    $this->get('/_obs/dominio-ajeno')->assertStatus(500);

    expect(eventosDelContrato($dir, 'app.exception')[0]['error'])->toMatchArray(['class' => DomainException::class, 'message' => null]);
});

it('una PDOException suelta tampoco conserva su mensaje y deja el sqlstate', function () {
    $dir = canalDeContrato();
    Route::get('/_obs/pdo', fn () => throw new PDOException('insert con centinela@ejemplo.test'));

    $this->get('/_obs/pdo')->assertStatus(500);

    $error = eventosDelContrato($dir, 'app.exception')[0]['error'];
    expect($error['message'])->toBeNull()
        ->and($error['sqlstate'])->not->toBeNull()
        ->and(textoDelDirectorio($dir))->not->toContain('centinela@ejemplo.test');
});

it('app.exception lleva el request_id del request que falló', function () {
    $dir = canalDeContrato();
    Route::get('/_obs/falla', fn () => throw new RuntimeException('x'));

    $requestId = $this->get('/_obs/falla')->headers->get('X-Request-Id');

    expect(eventosDelContrato($dir, 'app.exception')[0]['request_id'])->toBe($requestId);
});

it('un 404 y un 403 no generan app.exception', function () {
    $dir = canalDeContrato();
    Route::get('/_obs/prohibido', fn () => abort(403));

    $this->get('/no-existe-esta-ruta')->assertNotFound();
    $this->get('/_obs/prohibido')->assertForbidden();

    expect(eventosDelContrato($dir, 'app.exception'))->toBe([])
        ->and(array_column(array_column(eventosDelContrato($dir, 'http.request'), 'attributes'), 'status'))->toBe([404, 403]);
});

it('las trazas no llevan argumentos porque zend.exception_ignore_args está activo', function () {
    expect(ini_get('zend.exception_ignore_args'))->toBe('1');
});

it('el php.ini que usan las imágenes local y de staging fija zend.exception_ignore_args', function () {
    // CI no carga este archivo (usa `ini-values`), así que el test de arriba no
    // protege a staging: `docker/koyeb/Dockerfile` copia exactamente este php.ini.
    $ini = parse_ini_file(base_path('docker/php/php.ini'));

    expect($ini['zend.exception_ignore_args'] ?? null)->toBe('1');
});

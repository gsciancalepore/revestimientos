<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart;
use Illuminate\Testing\TestResponse;

/**
 * Spec observabilidad-02 — ampliación del contrato de logs v1: directorio
 * propio (OBS-10), retención (OBS-11), `checkout.returned` (OBS-12),
 * `session_ref` (OBS-13), precio unitario en `order.created` (OBS-14) y
 * conocimiento del dominio en las descripciones del schema (OBS-15).
 */

/**
 * Evalúa `config/logging.php` como lo haría un arranque sin esas variables en
 * el entorno, sin depender del `.env` de quien corre la suite.
 *
 * @param  list<string>  $variables
 * @return array<string, mixed>
 */
function configDeLoggingSin(array $variables): array
{
    $guardadas = [];

    foreach ($variables as $variable) {
        $guardadas[$variable] = [$_ENV[$variable] ?? null, $_SERVER[$variable] ?? null, getenv($variable)];
        unset($_ENV[$variable], $_SERVER[$variable]);
        putenv($variable);
    }

    try {
        return require base_path('config/logging.php');
    } finally {
        foreach ($guardadas as $variable => [$env, $server, $getenv]) {
            if ($env !== null) {
                $_ENV[$variable] = $env;
            }
            if ($server !== null) {
                $_SERVER[$variable] = $server;
            }
            if ($getenv !== false) {
                putenv($variable.'='.$getenv);
            }
        }
    }
}

// --- OBS-10: directorio propio del contrato --------------------------------

it('el canal del contrato escribe en storage/logs/contrato/', function () {
    $config = configDeLoggingSin([]);

    expect($config['channels']['app_file']['path'])->toBe(storage_path('logs/contrato/app.jsonl'));
});

it('el directorio del contrato se versiona vacío y el gitignore padre no lo excluye', function () {
    $padre = (string) file_get_contents(base_path('storage/logs/.gitignore'));
    $propio = base_path('storage/logs/contrato/.gitignore');

    expect(preg_split('/\R/', trim($padre)))->toContain('!contrato/')
        ->and(file_exists($propio))->toBeTrue()
        ->and(preg_split('/\R/', trim((string) file_get_contents($propio))))->toBe(['*', '!.gitignore']);
});

// --- OBS-11: retención del canal del contrato ------------------------------

it('sin LOG_CONTRATO_DAYS el contrato conserva 90 días y laravel.log sigue en 14', function () {
    $config = configDeLoggingSin(['LOG_CONTRATO_DAYS', 'LOG_DAILY_DAYS']);

    expect($config['channels']['app_file']['days'])->toBe(90)
        ->and($config['channels']['daily']['days'])->toBe(14);
});

it('LOG_CONTRATO_DAYS cambia solo la retención del contrato', function () {
    $_ENV['LOG_CONTRATO_DAYS'] = $_SERVER['LOG_CONTRATO_DAYS'] = '30';

    try {
        $config = configDeLoggingSin(['LOG_DAILY_DAYS']);
    } finally {
        unset($_ENV['LOG_CONTRATO_DAYS'], $_SERVER['LOG_CONTRATO_DAYS']);
    }

    expect((int) $config['channels']['app_file']['days'])->toBe(30)
        ->and($config['channels']['daily']['days'])->toBe(14);
});

it('.env.example declara LOG_CONTRATO_DAYS=90 y no declara LOG_DAILY_DAYS', function () {
    $ejemplo = (string) file_get_contents(base_path('.env.example'));

    expect($ejemplo)->toMatch('/^LOG_CONTRATO_DAYS=90$/m')
        ->not->toMatch('/^LOG_DAILY_DAYS=/m');
});

// --- OBS-14: precio unitario en las líneas de order.created -----------------

it('order.created lleva el precio congelado de cada línea, por caja en modo M2, en el log y en audit_logs', function () {
    $dir = canalDeContrato();
    $porUnidad = Product::factory()->unitMode()->create(['stock' => 10, 'precio_cents' => 12345]);
    $porM2 = Product::factory()->m2Mode()->create(['stock' => 10, 'precio_cents' => 20000]);
    app(Cart::class)->putItems([$porUnidad->id => 2, $porM2->id => 3]);

    $this->post(route('checkout.store'), checkoutPayload(['payment_method' => 'transferencia']));

    $order = Order::sole();
    $esperadas = $order->lines->map(fn ($linea): array => [
        'product_id' => $linea->product_id,
        'cantidad' => $linea->cantidad,
        'precio_unitario_cents' => $linea->precio_unitario_cents,
    ])->all();

    expect(collect($esperadas)->pluck('precio_unitario_cents', 'product_id')->all())
        ->toBe([$porUnidad->id => 12345, $porM2->id => 23000]);

    expect(eventosDelContrato($dir, 'order.created')[0]['attributes']['lines'])->toBe($esperadas);

    $auditoria = AuditLog::where('action', 'order.created')->where('subject_id', $order->id)->sole();
    expect($auditoria->payload['lines'])->toBe($esperadas);
});

// --- OBS-12: checkout.returned ---------------------------------------------

/**
 * @param  array<string, mixed>  $query
 * @param  array<string, mixed>  $sesion
 * @return array{0: TestResponse, 1: list<array<string, mixed>>}
 */
function volverDeMercadoPago(string $dir, array $query, array $sesion = []): array
{
    $respuesta = test()->withSession($sesion)->get(route('checkout.success').'?'.http_build_query($query));

    return [$respuesta, eventosDelContrato($dir, 'checkout.returned')];
}

it('el retorno desde MercadoPago deja una línea checkout.returned con el pedido de la sesión si coincide', function () {
    $dir = canalDeContrato();
    $order = pedidoDePanel();

    [, $retornos] = volverDeMercadoPago($dir, [
        'collection_status' => 'approved',
        'payment_id' => '123',
        'external_reference' => (string) $order->id,
    ], ['order_id' => $order->id]);

    expect($retornos)->toHaveCount(1)
        ->and($retornos[0]['level'])->toBe('info')
        ->and($retornos[0]['attributes'])->toBe([
            'order_id' => $order->id,
            'status' => 'approved',
            'payment_id' => '123',
            'external_reference' => (string) $order->id,
        ]);
});

it('si la sesión tiene otro pedido que external_reference, order_id es null', function () {
    $dir = canalDeContrato();
    $anterior = pedidoDePanel();
    $ultimo = pedidoDePanel();

    [, $retornos] = volverDeMercadoPago($dir, [
        'collection_status' => 'approved',
        'external_reference' => (string) $anterior->id,
    ], ['order_id' => $ultimo->id]);

    expect($retornos[0]['attributes']['order_id'])->toBeNull()
        ->and($retornos[0]['attributes']['external_reference'])->toBe((string) $anterior->id);
});

it('la coincidencia con external_reference es estricta: 07 no es 7', function () {
    $dir = canalDeContrato();
    $order = pedidoDePanel();

    [, $retornos] = volverDeMercadoPago($dir, [
        'collection_status' => 'approved',
        'external_reference' => '0'.$order->id,
    ], ['order_id' => $order->id]);

    expect($retornos[0]['attributes']['order_id'])->toBeNull();
});

it('sin pedido en sesión el retorno se registra con order_id null y redirige al carrito', function () {
    $dir = canalDeContrato();

    [$respuesta, $retornos] = volverDeMercadoPago($dir, ['collection_status' => 'approved', 'external_reference' => '7']);

    $respuesta->assertRedirect(route('carrito.show'));
    expect($retornos)->toHaveCount(1)
        ->and($retornos[0]['attributes']['order_id'])->toBeNull();
});

it('volver sin pagar normaliza el "null" literal y conserva el pedido', function () {
    $dir = canalDeContrato();
    $order = pedidoDePanel();

    [, $retornos] = volverDeMercadoPago($dir, [
        'collection_status' => 'null',
        'payment_id' => 'null',
        'external_reference' => (string) $order->id,
    ], ['order_id' => $order->id]);

    expect($retornos[0]['attributes'])->toBe([
        'order_id' => $order->id,
        'status' => null,
        'payment_id' => null,
        'external_reference' => (string) $order->id,
    ]);
});

it('un parámetro que llega como arreglo o vacío se escribe null y la línea valida contra el schema', function () {
    $dir = canalDeContrato();

    [, $retornos] = volverDeMercadoPago($dir, [
        'collection_status' => ['x' => str_repeat('a', 300)],
        'payment_id' => '',
        'external_reference' => ['7'],
    ]);

    expect($retornos[0]['attributes'])->toBe([
        'order_id' => null,
        'status' => null,
        'payment_id' => null,
        'external_reference' => null,
    ]);
});

it('sin collection_status no hay checkout.returned', function () {
    $dir = canalDeContrato();
    $order = pedidoDePanel();

    volverDeMercadoPago($dir, ['payment_id' => '123'], ['order_id' => $order->id]);
    volverDeMercadoPago($dir, [], ['order_id' => $order->id]);

    expect(eventosDelContrato($dir, 'checkout.returned'))->toBe([]);
});

it('los valores del retorno se truncan a 64 caracteres', function () {
    $dir = canalDeContrato();
    $largo = str_repeat('x', 300);

    [, $retornos] = volverDeMercadoPago($dir, [
        'collection_status' => $largo,
        'payment_id' => $largo,
        'external_reference' => $largo,
    ]);

    expect($retornos[0]['attributes']['status'])->toBe(str_repeat('x', 64))
        ->and($retornos[0]['attributes']['payment_id'])->toBe(str_repeat('x', 64))
        ->and($retornos[0]['attributes']['external_reference'])->toBe(str_repeat('x', 64));
});

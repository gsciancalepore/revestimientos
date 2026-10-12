<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart;
use Illuminate\Support\Str;
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
    $previos = [$_ENV['LOG_CONTRATO_DAYS'] ?? null, $_SERVER['LOG_CONTRATO_DAYS'] ?? null];
    $_ENV['LOG_CONTRATO_DAYS'] = $_SERVER['LOG_CONTRATO_DAYS'] = '30';

    try {
        $config = configDeLoggingSin(['LOG_DAILY_DAYS']);
    } finally {
        // Se repone lo que había: un `.env` creado desde `.env.example` ya trae la variable.
        [$env, $server] = $previos;
        unset($_ENV['LOG_CONTRATO_DAYS'], $_SERVER['LOG_CONTRATO_DAYS']);
        if ($env !== null) {
            $_ENV['LOG_CONTRATO_DAYS'] = $env;
        }
        if ($server !== null) {
            $_SERVER['LOG_CONTRATO_DAYS'] = $server;
        }
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

it('el "null" literal se normaliza en los tres parámetros, external_reference incluido', function () {
    $dir = canalDeContrato();
    $order = pedidoDePanel();

    [, $retornos] = volverDeMercadoPago($dir, [
        'collection_status' => 'null',
        'payment_id' => 'null',
        'external_reference' => 'null',
    ], ['order_id' => $order->id]);

    expect($retornos[0]['attributes'])->toBe([
        'order_id' => null,
        'status' => null,
        'payment_id' => null,
        'external_reference' => null,
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

// --- OBS-13: session_ref en http.request -----------------------------------

function sessionRefEsperado(string $sessionId): string
{
    return substr(hash_hmac('sha256', $sessionId, (string) config('app.key')), 0, 16);
}

it('http.request de un request con sesión lleva session_ref, el HMAC del id de sesión con APP_KEY', function () {
    $dir = canalDeContrato();

    $this->get(route('carrito.show'))->assertOk();

    $resumen = eventosDelContrato($dir, 'http.request')[0]['attributes'];
    expect($resumen['session_ref'])->toBe(sessionRefEsperado(app('session')->getId()))
        ->and($resumen['session_ref'])->toMatch('/^[0-9a-f]{16}$/');
});

it('dos requests de la misma sesión comparten session_ref y otra sesión tiene otro', function () {
    $dir = canalDeContrato();

    // Cada request de test sin cookie abre una sesión nueva: la "misma sesión"
    // es la que vuelve con su cookie, como haría el navegador.
    $this->get(route('carrito.show'));
    $cookie = config('session.cookie');
    $this->withCookie($cookie, app('session')->getId())->get(route('carrito.show'));
    $this->withCookie($cookie, Str::random(40))->get(route('carrito.show'));

    $refs = array_map(fn (array $linea): string => $linea['attributes']['session_ref'], eventosDelContrato($dir, 'http.request'));

    expect($refs)->toHaveCount(3)
        ->and($refs[0])->toBe($refs[1])
        ->and($refs[2])->not->toBe($refs[0]);
});

it('el id de sesión crudo no aparece en ninguna línea', function () {
    $dir = canalDeContrato();

    $this->get(route('carrito.show'));

    expect(textoDelDirectorio($dir))->not->toContain(app('session')->getId());
});

it('un request sin sesión no lleva session_ref', function () {
    $dir = canalDeContrato();

    $this->get('/up')->assertOk();

    expect(eventosDelContrato($dir, 'http.request')[0]['attributes'])->not->toHaveKey('session_ref');
});

// --- OBS-15: conocimiento del dominio en las descripciones ------------------

/**
 * @return array<string, string>
 */
function descripcionesDelContrato(): array
{
    $schema = json_decode((string) file_get_contents(base_path('docs/observabilidad/log-schema.v1.json')), true);

    return array_map(fn (array $evento): string => $evento['description'], $schema['x-events']);
}

it('las descripciones explican el comportamiento del dominio y citan su origen', function () {
    $descripciones = descripcionesDelContrato();

    expect($descripciones['order.status_changed'])->toContain('regla 165', 'ADR-012', 'no vence')
        ->and($descripciones['order.created'])->toContain('ADR-005', 'precio_unitario_cents')
        ->and($descripciones['order.stock_restored'])->toContain('regla 165', 'shipped', 'fuera del sistema')
        ->and($descripciones['checkout.payment_started'])->toContain('checkout.returned');
});

it('las descripciones declaran que checkout.returned no confirma pagos y que session_ref no es la cookie', function () {
    $descripciones = descripcionesDelContrato();

    expect($descripciones['checkout.returned'])->toContain('NO confirma ningún pago', 'no prueba un abandono')
        ->and($descripciones['http.request'])->toContain('`session_ref`', 'NO es la cookie');
});

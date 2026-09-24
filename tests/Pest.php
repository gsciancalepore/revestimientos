<?php

use App\Contracts\PaymentStatusQuery;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart;
use App\Services\MercadoPagoGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use MercadoPago\MercadoPagoConfig;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Tests\RedProhibida;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Ningún test alcanza servicios externos (`.ai/rules/tests.md`). Va acá y no en
 * `TestCase` porque `TestCase` solo se extiende en `Feature`: un test unitario
 * del gateway —justo donde uno pondría un test de mapeo— quedaba con el cliente
 * cURL real activo, con la rule file prometiendo lo contrario.
 */
pest()->beforeEach(function () {
    MercadoPagoConfig::setHttpClient(new RedProhibida);
})->in('Feature', 'Unit');

/**
 * Pedido con una línea sobre el producto dado, para las pruebas de la Spec 08.
 *
 * Vive acá y no en un archivo de tests porque la usan `ConfirmPaymentTest` y
 * `CancelOrderTest`: con el helper declarado en el primero, el segundo no se podía
 * correr solo, y eso rompe el procedimiento con el que este repo se defiende
 * (mutar la implementación y correr la suite filtrada por archivo).
 */
function pedidoConLinea(Product $product, int $cantidad, OrderStatus $status = OrderStatus::PendingPayment): Order
{
    $order = Order::factory()->create(['status' => $status, 'payment_method' => 'mercadopago']);

    $order->lines()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_codigo' => $product->codigo,
        'marca' => $product->marca,
        'unidad_venta' => $product->unidad_venta->value,
        'm2_por_caja' => $product->m2_por_caja,
        'cantidad' => $cantidad,
        'precio_unitario_cents' => 10000,
        'subtotal_cents' => 10000 * $cantidad,
    ]);

    return $order->fresh();
}

/**
 * Pedido listo para las pruebas del panel (Spec 08.c).
 *
 * Vive acá por el mismo motivo que `pedidoConLinea`: lo usan
 * `OrderPanelPermissionsTest` y `OrderPanelTest`, y declarado en uno de ellos el
 * otro no se puede correr solo, que es justo el procedimiento con el que este
 * repo se defiende (mutar la implementación y correr la suite filtrada).
 */
function pedidoDePanel(OrderStatus $status = OrderStatus::PendingPayment, string $medioDePago = 'mercadopago'): Order
{
    $product = Product::factory()->create(['stock' => 10]);
    $order = pedidoConLinea($product, 2, $status);
    $order->update(['payment_method' => $medioDePago]);

    return $order->fresh();
}

/*
 * Dobles y helpers de MercadoPago (checkout 07.4 y webhook 08.b). Viven acá
 * porque los usan también los tests del contrato de logs (spec
 * observabilidad-01): declarados en su archivo original, los otros no podían
 * correr solos (`.ai/rules/tests.md`).
 */

function putCartMp(Product $product, int $cantidad): void
{
    app(Cart::class)->putItems([$product->id => $cantidad]);
}

class FakeMercadoPagoGatewaySuccess extends MercadoPagoGateway
{
    public static int $calls = 0;

    public function __construct()
    {
        // Sin SDK: no llama al padre para no exigir token.
    }

    public function paymentUrl(Order $order): string
    {
        self::$calls++;

        $order->update([
            'mp_preference_id' => 'pref-'.$order->id,
            'mp_init_point' => 'https://mercadopago.test/checkout/pref-'.$order->id,
        ]);

        return 'https://mercadopago.test/checkout/pref-'.$order->id;
    }
}

class PayloadInspectorGateway extends MercadoPagoGateway
{
    /**
     * @return array<string, mixed>
     */
    public function payloadFor(Order $order): array
    {
        return $this->preferencePayload($order);
    }
}

class FakeMercadoPagoGatewayFailure extends MercadoPagoGateway
{
    public static int $calls = 0;

    public function __construct()
    {
        // Sin SDK: no llama al padre para no exigir token.
    }

    public function paymentUrl(Order $order): string
    {
        self::$calls++;

        throw new RuntimeException('Error de MercadoPago');
    }
}

function checkoutPayload(array $overrides = []): array
{
    return array_merge([
        'customer_name' => 'Ana MP',
        'customer_email' => 'anamp@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'payment_method' => 'mercadopago',
    ], $overrides);
}

const SECRETO_WEBHOOK = 'secreto-de-prueba';

/**
 * Doble de la consulta a la API (regla 155).
 */
class FakePaymentStatusQuery implements PaymentStatusQuery
{
    public int $consultas = 0;

    /**
     * @param  array{status: string, external_reference: ?string, amount_cents: int}|null  $pago
     */
    public function __construct(private ?array $pago, private bool $falla = false) {}

    /**
     * @return array{status: string, external_reference: ?string, amount_cents: int}|null
     */
    public function findPayment(string $paymentId): ?array
    {
        $this->consultas++;

        if ($this->falla) {
            throw new RuntimeException('MercadoPago no responde.');
        }

        return $this->pago;
    }
}

/**
 * @param  array{status: string, external_reference: ?string, amount_cents: int}|null  $pago
 */
function bindearConsulta(?array $pago, bool $falla = false): FakePaymentStatusQuery
{
    $doble = new FakePaymentStatusQuery($pago, $falla);

    app()->instance(PaymentStatusQuery::class, $doble);

    return $doble;
}

/**
 * Firma como la manda MercadoPago: `ts=<ts>,v1=<hmac>` sobre el manifiesto
 * `id:<data.id>;request-id:<x-request-id>;ts:<ts>;`.
 */
function firmaValida(string $dataId, string $requestId, string $ts = '1700000000', string $secreto = SECRETO_WEBHOOK): string
{
    $hmac = hash_hmac('sha256', "id:{$dataId};request-id:{$requestId};ts:{$ts};", $secreto);

    return "ts={$ts},v1={$hmac}";
}

/**
 * @param  array<string, mixed>|null  $body
 */
function notificar(string $dataId, ?string $firma = null, string $requestId = 'req-1', ?array $body = null): TestResponse
{
    $headers = ['x-request-id' => $requestId];

    if ($firma !== null) {
        $headers['x-signature'] = $firma;
    }

    return test()->postJson(
        route('webhook.mercadopago'),
        $body ?? ['type' => 'payment', 'data' => ['id' => $dataId]],
        $headers
    );
}

function pedidoPagable(int $stock = 10, int $cantidad = 3, int $totalCents = 30000): Order
{
    $product = Product::factory()->create(['stock' => $stock]);

    $order = pedidoConLinea($product, $cantidad);
    $order->update(['total_cents' => $totalCents]);

    return $order->fresh();
}

// HIG-07: la revalidación bajo `lockForUpdate` (regla 109) no tenía cobertura.
// La prevalidación `hasUnpurchasable()` intercepta cualquier escenario armado
// desde el carrito, así que hay que simular la carrera real: el carrito leyó el
// stock antes de que otro pedido lo consumiera y su prevalidación quedó vieja.
// Este doble reproduce esa ventana; sin él no se llega nunca al lock.
function cartConPrevalidacionVieja(Product $product, int $cantidad): Cart
{
    $cart = new class extends Cart
    {
        public function hasUnpurchasable(): bool
        {
            return false;
        }
    };

    $cart->putItems([$product->id => $cantidad]);
    app()->instance(Cart::class, $cart);

    return $cart;
}

/*
 * Contrato de logs v1 (spec observabilidad-01). Los tests del contrato
 * construyen el canal `app` desde `config/logging.php` real y solo cambian la
 * ruta a un directorio temporal (criterio 1): así un canal mal configurado
 * rompe estos tests en lugar de caer en silencio al logger de emergencia.
 */
function canalDeContrato(?string $ruta = null): string
{
    $dir = $ruta ?? sys_get_temp_dir().'/contrato-logs-'.Str::uuid();

    if ($ruta === null) {
        mkdir($dir);
    }

    config([
        'logging.default' => 'app',
        'logging.channels.app_file.path' => $dir.'/app.jsonl',
    ]);

    app('log')->forgetChannel('app');
    app('log')->forgetChannel('app_file');

    return $dir;
}

/**
 * Lee todas las líneas del canal `app` y valida cada una contra
 * `docs/observabilidad/log-schema.v1.json`: una línea fuera de contrato hace
 * fallar el test que la produjo.
 *
 * @return list<array<string, mixed>>
 */
function lineasDelContrato(string $dir): array
{
    $validator = new Validator;
    $validator->resolver()->registerFile(
        'https://revestimientos/observabilidad/log-schema.v1.json',
        base_path('docs/observabilidad/log-schema.v1.json'),
    );

    $lineas = [];

    foreach (glob($dir.'/app-*.jsonl') ?: [] as $archivo) {
        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
            $resultado = $validator->validate(json_decode($linea), 'https://revestimientos/observabilidad/log-schema.v1.json');

            if (! $resultado->isValid()) {
                throw new RuntimeException("Línea fuera del contrato: {$linea}\n".json_encode((new ErrorFormatter)->format($resultado->error()), JSON_UNESCAPED_UNICODE));
            }

            $lineas[] = json_decode($linea, true);
        }
    }

    return $lineas;
}

/**
 * @return list<array<string, mixed>>
 */
function eventosDelContrato(string $dir, string $event): array
{
    return array_values(array_filter(lineasDelContrato($dir), fn (array $linea): bool => $linea['event'] === $event));
}

/**
 * Todo el texto escrito en el directorio, para buscar centinelas de datos personales.
 */
function textoDelDirectorio(string $dir): string
{
    return implode("\n", array_map(fn (string $archivo): string => (string) file_get_contents($archivo), glob($dir.'/*') ?: []));
}

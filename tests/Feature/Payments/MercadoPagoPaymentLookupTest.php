<?php

use App\Services\MercadoPagoGateway;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPResponse;
use MercadoPago\Resources\Payment;

/**
 * Spec 08 fase 08.b — el adaptador real de la regla 155.
 *
 * `MercadoPagoWebhookTest` prueba el puerto con un doble, así que nada ejercita
 * al único adaptador que lo implementa: acá se cubre el mapeo `Payment → array`,
 * que es donde vive la conversión pesos→centavos contra la que la regla 157
 * compara el monto. Sin red: la consulta al SDK se sobrescribe.
 */
class GatewayConPagoFalso extends MercadoPagoGateway
{
    public ?Payment $pago = null;

    public ?MPApiException $error = null;

    public function __construct() {}

    protected function consultarPago(int $paymentId): Payment
    {
        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->pago ?? new Payment;
    }
}

function pagoDelSdk(?string $status, ?string $externalReference, ?float $monto): Payment
{
    $pago = new Payment;
    $pago->status = $status;
    $pago->external_reference = $externalReference;
    $pago->transaction_amount = $monto;

    return $pago;
}

function gatewayCon(?Payment $pago = null, ?MPApiException $error = null): GatewayConPagoFalso
{
    config(['services.mercadopago.access_token' => 'TEST-token-de-prueba']);

    $gateway = new GatewayConPagoFalso;
    $gateway->pago = $pago;
    $gateway->error = $error;

    return $gateway;
}

function errorDeApi(int $status): MPApiException
{
    return new MPApiException('error', new MPResponse($status, []));
}

it('traduce el pago de la API a la forma que consume el webhook', function () {
    $gateway = gatewayCon(pagoDelSdk('approved', '42', 1234.56));

    expect($gateway->findPayment('pago-1'))->toBe([
        'status' => 'approved',
        'external_reference' => '42',
        'amount_cents' => 123456,
    ]);
});

it('convierte el monto a centavos sin perder plata por el float', function (float $pesos, int $cents) {
    $gateway = gatewayCon(pagoDelSdk('approved', '42', $pesos));

    expect($gateway->findPayment('pago-1')['amount_cents'])->toBe($cents);
})->with([
    [300.00, 30000],
    [300.55, 30055],
    [0.01, 1],
    [120500.90, 12050090],
    [1234.56, 123456],
]);

it('devuelve null cuando MercadoPago no conoce el pago', function () {
    $gateway = gatewayCon(error: errorDeApi(404));

    expect($gateway->findPayment('pago-inexistente'))->toBeNull();
});

it('propaga el fallo transitorio de la API en vez de tragarlo', function (int $status) {
    $gateway = gatewayCon(error: errorDeApi($status));

    expect(fn () => $gateway->findPayment('pago-1'))->toThrow(MPApiException::class);
})->with([500, 502, 503, 401]);

it('devuelve null si el pago viene sin estado', function () {
    $gateway = gatewayCon(pagoDelSdk(null, '42', 100.0));

    expect($gateway->findPayment('pago-1'))->toBeNull();
});

it('exige el access token configurado', function () {
    config(['services.mercadopago.access_token' => '']);

    expect(fn () => (new GatewayConPagoFalso)->findPayment('pago-1'))
        ->toThrow(RuntimeException::class);
});

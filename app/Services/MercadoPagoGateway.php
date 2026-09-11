<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusQuery;
use App\Models\Order;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Payment;
use RuntimeException;

class MercadoPagoGateway implements PaymentGateway, PaymentStatusQuery
{
    public function __construct(
        private ?PreferenceClient $preferences = null,
        private ?PaymentClient $payments = null,
    ) {}

    public function name(): string
    {
        return 'mercadopago';
    }

    /**
     * Estado real del pago contra la API (Spec 08, regla 155).
     *
     * La consulta vive acá y no en el controlador porque el gateway encapsula el
     * SDK por completo (regla 122 y ADR-006). `PaymentClient` es `final` y no se
     * puede mockear, así que la costura para los tests es el puerto
     * `PaymentStatusQuery`, que se bindea en el contenedor.
     *
     * @return array{status: string, external_reference: ?string, amount_cents: int}|null
     */
    public function findPayment(string $paymentId): ?array
    {
        $this->configurarSdk();

        try {
            $payment = $this->consultarPago((int) $paymentId);
        } catch (MPApiException $e) {
            // Un pago que MercadoPago no conoce es "no hay nada que hacer" (regla
            // 156). Cualquier otro código es un fallo del que no se puede concluir
            // nada: se propaga para que el webhook responda no-200 y MP reintente.
            if ($e->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }

        return $this->mapearPago($payment);
    }

    /**
     * Costura sin red para los tests: `PaymentClient` es `final` y no se puede
     * mockear, así que lo que se sobrescribe es esta llamada.
     */
    protected function consultarPago(int $paymentId): Payment
    {
        return ($this->payments ?? new PaymentClient)->get($paymentId);
    }

    /**
     * Traduce el recurso del SDK a la forma que consume el webhook.
     *
     * Acá vive la conversión pesos→centavos contra la que la regla 157 compara el
     * monto: si se rompe, ningún pago se confirma nunca y todos caen en
     * `order.payment_amount_mismatch`. `number_format` antes de `bcmul` evita que
     * la representación binaria del float se arrastre a los centavos.
     *
     * @return array{status: string, external_reference: ?string, amount_cents: int}|null
     */
    protected function mapearPago(Payment $payment): ?array
    {
        if (! is_string($payment->status)) {
            return null;
        }

        return [
            'status' => $payment->status,
            'external_reference' => $payment->external_reference,
            // El dominio está en centavos (ADR-003); el SDK devuelve un float en pesos.
            'amount_cents' => (int) bcmul(number_format((float) $payment->transaction_amount, 2, '.', ''), '100'),
        ];
    }

    public function paymentUrl(Order $order): string
    {
        $this->configurarSdk();

        $preference = ($this->preferences ?? new PreferenceClient)->create($this->preferencePayload($order));

        if (! is_string($preference->init_point) || $preference->init_point === '') {
            throw new RuntimeException('MercadoPago no devolvió init_point.');
        }

        $order->update([
            'mp_preference_id' => $preference->id,
            'mp_init_point' => $preference->init_point,
        ]);

        return $preference->init_point;
    }

    private function configurarSdk(): void
    {
        $accessToken = (string) config('services.mercadopago.access_token');

        if ($accessToken === '') {
            throw new RuntimeException('Falta configurar MERCADOPAGO_ACCESS_TOKEN.');
        }

        MercadoPagoConfig::setAccessToken($accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    protected function preferencePayload(Order $order): array
    {
        $successUrl = route('checkout.success');

        $items = $order->lines->map(fn ($line): array => [
            'title' => $line->product_name,
            'quantity' => $line->cantidad,
            // Borde SDK: unit_price exige float; el dominio sigue en centavos (ADR-003).
            'unit_price' => (float) bcdiv((string) $line->precio_unitario_cents, '100', 2),
            'currency_id' => 'ARS',
        ])->all();

        $payload = [
            'items' => $items,
            'external_reference' => (string) $order->id,
            'back_urls' => [
                'success' => $successUrl,
                'failure' => $successUrl,
                'pending' => $successUrl,
            ],
        ];

        // El envío no es un producto: viaja en `shipments` para que MercadoPago lo
        // discrimine y lo sume al total. Sin esto el cliente pagaría solo el subtotal.
        if ($order->shipping_cost_cents > 0) {
            $payload['shipments'] = [
                'mode' => 'not_specified',
                // Borde SDK: cost exige float; el dominio sigue en centavos (ADR-003).
                'cost' => (float) bcdiv((string) $order->shipping_cost_cents, '100', 2),
            ];
        }

        // MercadoPago rechaza `auto_return` (400 invalid_auto_return) si la back_url
        // no es alcanzable desde internet, como en desarrollo local sin túnel.
        if (self::backUrlEsPublica($successUrl)) {
            $payload['auto_return'] = 'approved';
        }

        return $payload;
    }

    /**
     * Una back_url es pública cuando MercadoPago puede alcanzarla desde internet:
     * descarta localhost, dominios de desarrollo y rangos IP privados o reservados.
     */
    private static function backUrlEsPublica(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        foreach (['.localhost', '.local', '.test'] as $sufijo) {
            if (str_ends_with($host, $sufijo)) {
                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }
}

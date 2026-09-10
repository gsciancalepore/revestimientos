<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;

class MercadoPagoGateway implements PaymentGateway
{
    public function __construct(private ?PreferenceClient $preferences = null) {}

    public function name(): string
    {
        return 'mercadopago';
    }

    public function paymentUrl(Order $order): string
    {
        $accessToken = (string) config('services.mercadopago.access_token');

        if ($accessToken === '') {
            throw new RuntimeException('Falta configurar MERCADOPAGO_ACCESS_TOKEN.');
        }

        MercadoPagoConfig::setAccessToken($accessToken);

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

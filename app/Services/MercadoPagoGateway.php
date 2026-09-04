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

        $successUrl = route('checkout.success');

        $items = $order->lines->map(fn ($line): array => [
            'title' => $line->product_name,
            'quantity' => $line->cantidad,
            // Borde SDK: unit_price exige float; el dominio sigue en centavos (ADR-003).
            'unit_price' => (float) bcdiv((string) $line->precio_unitario_cents, '100', 2),
            'currency_id' => 'ARS',
        ])->all();

        $preference = ($this->preferences ?? new PreferenceClient)->create([
            'items' => $items,
            'external_reference' => (string) $order->id,
            'back_urls' => [
                'success' => $successUrl,
                'failure' => $successUrl,
                'pending' => $successUrl,
            ],
            'auto_return' => 'approved',
        ]);

        if (! is_string($preference->init_point) || $preference->init_point === '') {
            throw new RuntimeException('MercadoPago no devolvió init_point.');
        }

        $order->update([
            'mp_preference_id' => $preference->id,
            'mp_init_point' => $preference->init_point,
        ]);

        return $preference->init_point;
    }
}

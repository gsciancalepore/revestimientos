<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGateway
{
    public function name(): string;

    /**
     * URL de pago externa o null si el medio no tiene (transferencia).
     * El fallo de un gateway con URL es excepción, nunca null.
     */
    public function paymentUrl(Order $order): ?string;
}

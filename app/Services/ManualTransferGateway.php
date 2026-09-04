<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;

class ManualTransferGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'transferencia';
    }

    public function paymentUrl(Order $order): ?string
    {
        return null;
    }
}

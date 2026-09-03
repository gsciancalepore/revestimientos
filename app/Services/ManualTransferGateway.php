<?php

namespace App\Services;

use App\Contracts\PaymentGateway;

class ManualTransferGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'transferencia';
    }
}

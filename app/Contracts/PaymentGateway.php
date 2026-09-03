<?php

namespace App\Contracts;

interface PaymentGateway
{
    public function name(): string;
}

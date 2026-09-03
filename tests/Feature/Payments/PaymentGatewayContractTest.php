<?php

use App\Contracts\PaymentGateway;
use App\Services\ManualTransferGateway;

test('ManualTransferGateway implementa PaymentGateway y name es transferencia', function () {
    $gateway = app(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(PaymentGateway::class);
    expect($gateway)->toBeInstanceOf(ManualTransferGateway::class);
    expect($gateway->name())->toBe('transferencia');
});

test('PaymentGateway resuelve a ManualTransferGateway via binding', function () {
    $gateway = app()->make(PaymentGateway::class);

    expect($gateway->name())->toBe('transferencia');
});

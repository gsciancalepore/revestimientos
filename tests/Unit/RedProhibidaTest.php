<?php

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPRequest;
use Tests\RedProhibida;

/**
 * El cerrojo que impide que la suite alcance la API de MercadoPago se podía
 * borrar entero sin que nada avisara, y `.ai/rules/tests.md` promete que existe.
 *
 * Vive en `Unit` a propósito: es la suite que no extiende `Tests\TestCase` y que
 * por eso quedaba descubierta.
 */
it('deja instalado el cerrojo que prohíbe salir a la red', function () {
    expect(MercadoPagoConfig::getHttpClient())->toBeInstanceOf(RedProhibida::class);
});

it('corta con un error que dice qué doble falta', function () {
    expect(fn () => MercadoPagoConfig::getHttpClient()->send(new MPRequest('/v1/payments/1', 'GET')))
        ->toThrow(RuntimeException::class, 'Un test intentó llamar a la API de MercadoPago');
});

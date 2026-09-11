<?php

namespace Tests;

use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;
use RuntimeException;

/**
 * Cliente HTTP que hace imposible que un test alcance la API de MercadoPago.
 *
 * El SDK resuelve su transporte por `MercadoPagoConfig::getHttpClient()`, que
 * construye un cliente cURL real si nadie le puso otro. Neutralizar las
 * credenciales en `phpunit.xml` no alcanza: con un token cualquiera el SDK
 * igual sale a internet y solo falla del otro lado (pasó el 2026-09-11, con un
 * `MPAuthenticationException` 401 que delató la llamada real).
 *
 * Cualquier salida a la red durante la suite muere acá, con el mensaje que dice
 * qué doble faltó poner.
 */
class RedProhibida implements MPHttpClient
{
    public function send(MPRequest $request): MPResponse
    {
        throw new RuntimeException(
            'Un test intentó llamar a la API de MercadoPago ('.$request->getUri().'). '
            .'Ningún test alcanza servicios externos: falta un doble del puerto o de la consulta al SDK.'
        );
    }
}

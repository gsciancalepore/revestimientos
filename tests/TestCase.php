<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use MercadoPago\MercadoPagoConfig;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ningún test alcanza servicios externos (`.ai/rules/tests.md`). Las
        // credenciales vacías de `phpunit.xml` no bastan: el SDK sale a la red
        // igual y falla recién del otro lado.
        MercadoPagoConfig::setHttpClient(new RedProhibida);
    }
}

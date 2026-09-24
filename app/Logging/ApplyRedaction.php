<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * `tap` que registra la redacción de datos personales (OBS-06) en un canal.
 * Laravel solo lee `processors` en el driver `monolog`; el `tap` funciona en
 * todos, por eso `config/logging.php` lo declara en cada canal.
 */
class ApplyRedaction
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new RedactPersonalData);
    }
}

<?php

namespace App\Logging;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Red de seguridad de OBS-06: reemplaza el valor de las claves de datos
 * personales en `context` y `extra`, a cualquier profundidad, antes de que
 * cualquier formateador escriba la línea. Corre en todos los canales.
 *
 * La comparación es exacta y sin distinguir mayúsculas, nunca por subcadena:
 * `product_name` o el `codigo_postal` de una tarifa no son datos personales.
 * No protege texto libre; de eso se ocupan OBS-05.8 y OBS-07.
 */
class RedactPersonalData implements ProcessorInterface
{
    public const REDACTADO = '[redactado]';

    public const CLAVES = [
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'shipping_cp',
        'email',
        'phone',
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'authorization',
        'x-signature',
        'cookie',
        'ip',
        'ip_address',
        'user_agent',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redactar($record->context),
            extra: $this->redactar($record->extra),
        );
    }

    /**
     * @param  array<mixed>  $datos
     * @return array<mixed>
     */
    private function redactar(array $datos): array
    {
        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && in_array(strtolower($clave), self::CLAVES, true)) {
                $datos[$clave] = self::REDACTADO;

                continue;
            }

            // Un modelo o un DTO en el contexto se serializa después, en el
            // formateador: sin convertirlo acá, sus claves pasarían sin revisar.
            if ($valor instanceof Arrayable) {
                $valor = $valor->toArray();
            } elseif ($valor instanceof JsonSerializable) {
                $valor = $valor->jsonSerialize();
            }

            if (is_array($valor)) {
                $datos[$clave] = $this->redactar($valor);
            }
        }

        return $datos;
    }
}

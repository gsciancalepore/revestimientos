<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Único punto de emisión de los eventos del catálogo del contrato de logs v1
 * (`docs/observabilidad/log-schema.v1.json`). Todo `Log::` nuevo en `app/`
 * pasa por acá con un evento declarado en el schema (`.ai/rules/`).
 */
class EventLog
{
    /**
     * Valores que llegan de afuera (del request o de MercadoPago): se truncan
     * porque terminan en el prompt de un modelo del lado consumidor (OBS-01).
     */
    public const CLAVES_EXTERNAS = ['payment_id', 'tipo', 'mp_request_id', 'external_reference', 'status'];

    public const MAX_EXTERNO = 64;

    /**
     * Espejos de la auditoría que van en `warning` (OBS-05.1); el resto, `info`.
     */
    public const AUDITORIA_WARNING = [
        'order.payment_amount_mismatch',
        'order.paid_after_cancel',
        'order.stock_negative',
        'webhook.signature_invalid',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function record(string $event, array $attributes = [], string $level = 'info', ?Throwable $error = null): void
    {
        foreach (self::CLAVES_EXTERNAS as $clave) {
            if (isset($attributes[$clave]) && is_string($attributes[$clave])) {
                $attributes[$clave] = mb_substr($attributes[$clave], 0, self::MAX_EXTERNO);
            }
        }

        Log::log($level, $event, [
            ContractFormatter::MARCA => true,
            'attributes' => $attributes,
            'error' => $error === null ? null : ErrorSerializer::from($error),
        ]);
    }

    public static function nivelDeAuditoria(string $accion): string
    {
        return in_array($accion, self::AUDITORIA_WARNING, true) ? 'warning' : 'info';
    }
}

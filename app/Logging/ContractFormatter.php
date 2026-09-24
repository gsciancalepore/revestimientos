<?php

namespace App\Logging;

use DateTimeZone;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Escribe cada entrada con la forma exacta del contrato de logs v1 (OBS-01):
 * un objeto JSON por línea, `timestamp` en UTC, `request_id` llevado desde el
 * `extra` del `Context` a la raíz, y `attributes`/`error` del evento.
 *
 * Lo que no emitió `EventLog` es una línea ajena al catálogo y sale como
 * `app.log` (OBS-05.8), con el texto truncado y el contexto ya redactado.
 */
class ContractFormatter extends NormalizerFormatter
{
    public const MARCA = '_contrato';

    public const MAX_TEXTO = 256;

    public function format(LogRecord $record): string
    {
        $esEvento = ($record->context[self::MARCA] ?? false) === true;

        [$event, $attributes, $error] = $esEvento
            ? [$record->message, $record->context['attributes'] ?? [], $record->context['error'] ?? null]
            : $this->lineaAjena($record);

        $linea = [
            'schema_version' => '1',
            'timestamp' => $record->datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'level' => self::nivel($record->level),
            'event' => $event,
            'service' => 'revestimientos',
            'environment' => (string) config('app.env'),
            'request_id' => $record->extra['request_id'] ?? null,
            'attributes' => $attributes === [] ? new \stdClass : $attributes,
            'error' => $error,
        ];

        return json_encode($linea, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
    }

    /**
     * @param  array<LogRecord>  $records
     */
    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }

    public static function nivel(Level $level): string
    {
        return match ($level) {
            Level::Debug => 'debug',
            Level::Info, Level::Notice => 'info',
            Level::Warning => 'warning',
            Level::Error => 'error',
            Level::Critical, Level::Alert, Level::Emergency => 'critical',
        };
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>|null}
     */
    private function lineaAjena(LogRecord $record): array
    {
        $contexto = $record->context;
        $error = null;

        if (($contexto['exception'] ?? null) instanceof Throwable) {
            $error = ErrorSerializer::from($contexto['exception']);
            unset($contexto['exception']);
        }

        $attributes = ['message' => mb_substr($record->message, 0, self::MAX_TEXTO)];

        if ($contexto !== []) {
            $attributes['context'] = $this->truncar($this->normalize($contexto));
        }

        return ['app.log', $attributes, $error];
    }

    private function truncar(mixed $valor): mixed
    {
        if (is_string($valor)) {
            return mb_substr($valor, 0, self::MAX_TEXTO);
        }

        if (is_array($valor)) {
            return array_map($this->truncar(...), $valor);
        }

        return $valor;
    }
}

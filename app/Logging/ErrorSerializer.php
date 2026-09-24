<?php

namespace App\Logging;

use DomainException;
use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

/**
 * Objeto `error` del contrato (OBS-07): clase, código, mensaje, SQLSTATE y una
 * traza `archivo:línea` sin argumentos.
 *
 * El mensaje se conserva solo en las excepciones propias de la app; el de una
 * librería es texto libre que termina en el prompt de un modelo de terceros
 * (decisión del dueño, 2026-09-24). El de base de datos trae el SQL con los
 * valores, así que de esas queda solo el SQLSTATE.
 */
class ErrorSerializer
{
    private const MAX_MARCOS = 30;

    /**
     * @return array{class: class-string, code: int|string, message: string|null, sqlstate: string|null, trace: list<string>}
     */
    public static function from(Throwable $e): array
    {
        $esDeBase = $e instanceof QueryException || $e instanceof PDOException;

        return [
            'class' => $e::class,
            'code' => $e->getCode(),
            'message' => ! $esDeBase && self::esPropia($e) ? $e->getMessage() : null,
            'sqlstate' => $esDeBase ? (string) $e->getCode() : null,
            'trace' => self::traza($e),
        ];
    }

    private static function esPropia(Throwable $e): bool
    {
        if (str_starts_with($e::class, 'App\\')) {
            return true;
        }

        return $e instanceof DomainException && str_starts_with($e->getFile(), app_path());
    }

    /**
     * @return list<string>
     */
    private static function traza(Throwable $e): array
    {
        $marcos = [self::marco($e->getFile(), $e->getLine())];

        foreach ($e->getTrace() as $paso) {
            if (isset($paso['file'], $paso['line']) && ! str_contains($paso['file'], '(')) {
                $marcos[] = self::marco($paso['file'], $paso['line']);
            }
        }

        return array_slice($marcos, 0, self::MAX_MARCOS);
    }

    private static function marco(string $archivo, int $linea): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return (str_starts_with($archivo, $base) ? substr($archivo, strlen($base)) : $archivo).':'.$linea;
    }
}

<?php

namespace App\Services;

/**
 * Parsea y valida el CSV de tarifas (snapshot `codigo_postal,precio_envio`).
 *
 * No persiste nada: devuelve filas válidas y errores con nº de línea.
 * El CP se trata siempre como string; el precio se valida como entero
 * en pesos y se convierte a centavos con aritmética entera nativa.
 */
class ShippingRatesCsvParser
{
    public const HEADER = 'codigo_postal,precio_envio';

    /**
     * Precio máximo en pesos tal que `× 100` entra en bigint.
     */
    public const MAX_PRECIO_PESOS = 92233720368547758;

    /**
     * @return array{rows: list<array{line: int, cp: string, precio: int, costo_cents: int}>, errors: list<string>}
     */
    public function parse(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);

        while (count($lines) > 0 && end($lines) === '') {
            array_pop($lines);
        }

        if ($lines === []) {
            return ['rows' => [], 'errors' => ['El archivo está vacío.']];
        }

        $header = trim((string) array_shift($lines));

        if ($header !== self::HEADER) {
            return ['rows' => [], 'errors' => ['La cabecera debe ser exactamente "codigo_postal,precio_envio".']];
        }

        if ($lines === []) {
            return ['rows' => [], 'errors' => ['El archivo no contiene filas de datos.']];
        }

        /** @var list<array{line: int, cp: string, precio: int, costo_cents: int}> $rows */
        $rows = [];
        /** @var list<string> $errors */
        $errors = [];
        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 2;

            if (trim($line) === '') {
                $errors[] = "Línea {$lineNumber}: fila vacía.";

                continue;
            }

            $fields = str_getcsv($line, ',', '"', '');

            if (count($fields) !== 2) {
                $errors[] = "Línea {$lineNumber}: debe tener 2 columnas (codigo_postal,precio_envio).";

                continue;
            }

            $cp = trim((string) $fields[0]);
            $precioStr = trim((string) $fields[1]);

            $cpValido = true;

            if (! preg_match('/^[0-9]{4}$/', $cp)) {
                $cpValido = false;
                $errors[] = "Línea {$lineNumber}: el código postal debe tener 4 dígitos.";
            } elseif (isset($seen[$cp])) {
                $cpValido = false;
                $errors[] = "Línea {$lineNumber}: el código postal {$cp} está duplicado.";
            } else {
                $seen[$cp] = true;
            }

            $precio = null;

            if (! preg_match('/^[0-9]+$/', $precioStr)) {
                $errors[] = "Línea {$lineNumber}: el precio debe ser un entero mayor o igual a 0.";
            } else {
                $precio = (int) $precioStr;

                if ($precio > self::MAX_PRECIO_PESOS) {
                    $precio = null;
                    $errors[] = "Línea {$lineNumber}: el precio excede el máximo permitido.";
                }
            }

            if (! $cpValido || $precio === null) {
                continue;
            }

            $rows[] = [
                'line' => $lineNumber,
                'cp' => $cp,
                'precio' => $precio,
                'costo_cents' => $precio * 100,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}

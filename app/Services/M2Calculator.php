<?php

namespace App\Services;

use InvalidArgumentException;

class M2Calculator
{
    /**
     * Superficie en m² a partir de largo y ancho en centímetros (ADR-003).
     *
     * @param  numeric-string  $largoCm
     * @param  numeric-string  $anchoCm
     */
    public function m2DesdeDimensiones(string $largoCm, string $anchoCm): string
    {
        $this->assertPositivo($largoCm, 'El largo en cm debe ser mayor a cero.');
        $this->assertPositivo($anchoCm, 'El ancho en cm debe ser mayor a cero.');

        return bcdiv(bcmul($largoCm, $anchoCm, 2), '10000', 2);
    }

    /**
     * Aplica el porcentaje de desperdicio (por defecto 10 %, regla 12).
     *
     * @param  numeric-string  $m2
     * @param  numeric-string  $porcentaje
     */
    public function aplicarDesperdicio(string $m2, string $porcentaje = '10'): string
    {
        $this->assertPositivo($m2, 'Los m² deben ser mayores a cero.');

        if (bccomp($porcentaje, '0', 2) < 0) {
            throw new InvalidArgumentException('El porcentaje de desperdicio no puede ser negativo.');
        }

        $factor = bcadd('100', $porcentaje, 2);

        return bcdiv(bcmul($m2, $factor, 4), '100', 2);
    }

    /**
     * Cajas necesarias redondeando hacia arriba (regla 9).
     *
     * @param  numeric-string  $m2
     * @param  numeric-string  $m2PorCaja
     */
    public function cajasNecesarias(string $m2, string $m2PorCaja): int
    {
        $this->assertPositivo($m2, 'Los m² deben ser mayores a cero.');
        $this->assertPositivo($m2PorCaja, 'Los m² por caja deben ser mayores a cero.');

        return (int) ceil((float) bcdiv($m2, $m2PorCaja, 6));
    }

    /**
     * @param  numeric-string  $valor
     */
    private function assertPositivo(string $valor, string $mensaje): void
    {
        if (bccomp($valor, '0', 2) <= 0) {
            throw new InvalidArgumentException($mensaje);
        }
    }
}

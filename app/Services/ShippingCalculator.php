<?php

namespace App\Services;

class ShippingQuote
{
    /**
     * @param  int  $costoCents  Costo en centavos (solo válido si disponible=true)
     * @param  bool  $disponible  Si existe tarifa activa para el CP
     */
    public function __construct(
        public readonly int $costoCents,
        public readonly bool $disponible,
    ) {}
}

interface ShippingCalculator
{
    public function quote(string $cp): ShippingQuote;
}

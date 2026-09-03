<?php

namespace App\Services;

use App\Models\ShippingRate;

class ManualShippingCalculator implements ShippingCalculator
{
    public function quote(string $cp): ShippingQuote
    {
        $cp = trim($cp);

        $rate = ShippingRate::query()->where('cp', $cp)->activo()->first();

        if ($rate === null) {
            return new ShippingQuote(costoCents: 0, disponible: false);
        }

        return new ShippingQuote(costoCents: $rate->costo_cents, disponible: true);
    }
}

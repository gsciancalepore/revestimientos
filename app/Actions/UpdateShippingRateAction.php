<?php

namespace App\Actions;

use App\Models\ShippingRate;

class UpdateShippingRateAction
{
    public function execute(ShippingRate $shippingRate, string $cp, int $costoCents, bool $activo = true): ShippingRate
    {
        $shippingRate->update([
            'cp' => trim($cp),
            'costo_cents' => $costoCents,
            'activo' => $activo,
        ]);

        return $shippingRate;
    }
}

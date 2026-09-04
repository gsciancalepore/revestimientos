<?php

namespace App\Actions;

use App\Models\ShippingRate;

class CreateShippingRateAction
{
    public function execute(string $cp, int $costoCents, bool $activo = true): ShippingRate
    {
        return ShippingRate::query()->create([
            'cp' => trim($cp),
            'costo_cents' => $costoCents,
            'activo' => $activo,
        ]);
    }
}

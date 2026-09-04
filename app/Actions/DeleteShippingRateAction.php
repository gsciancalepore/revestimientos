<?php

namespace App\Actions;

use App\Models\ShippingRate;

class DeleteShippingRateAction
{
    public function execute(ShippingRate $shippingRate): void
    {
        $shippingRate->delete();
    }
}

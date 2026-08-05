<?php

namespace App\Actions;

use App\Models\Product;

class DeleteProductAction
{
    public function execute(Product $product): void
    {
        // Spec 03, regla 67: si el producto tuviera pedidos no se borra (se
        // desactiva). Se activa cuando exista la tabla `orders` (Spec 05).
        $product->delete();
    }
}

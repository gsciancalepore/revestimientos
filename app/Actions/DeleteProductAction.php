<?php

namespace App\Actions;

use App\Models\Product;
use DomainException;

class DeleteProductAction
{
    public function execute(Product $product): void
    {
        // Spec 03, regla 67: un producto con historial de pedidos no se borra;
        // en su lugar se desactiva (`activo = false`) desde el formulario.
        if ($product->tienePedidos()) {
            throw new DomainException('No se puede borrar un producto con pedidos; desactivalo en su lugar (Spec 03, regla 67).');
        }

        $product->delete();
    }
}

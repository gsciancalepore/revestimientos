<?php

namespace App\Actions;

use App\Enums\ProductSaleUnit;
use App\Models\Product;
use App\Services\AuditRecorder;

class CreateProductAction
{
    public function __construct(private AuditRecorder $recorder) {}

    /**
     * @param  array<string, mixed>|null  $imagenes
     * @param  array<string, mixed>|null  $specs
     */
    public function execute(
        int $categoryId,
        string $name,
        string $codigo,
        ProductSaleUnit $unidadVenta,
        int $precioCents,
        ?string $marca = null,
        ?string $descripcion = null,
        ?int $precioOfertaCents = null,
        ?string $m2PorCaja = null,
        int $stock = 0,
        bool $activo = true,
        ?array $imagenes = null,
        ?array $specs = null,
    ): Product {
        $product = Product::create([
            'category_id' => $categoryId,
            'name' => $name,
            'marca' => $marca,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'precio_cents' => $precioCents,
            'precio_oferta_cents' => $precioOfertaCents,
            'unidad_venta' => $unidadVenta,
            'm2_por_caja' => $unidadVenta === ProductSaleUnit::M2 ? $m2PorCaja : null,
            'stock' => $stock,
            'activo' => $activo,
            'imagenes' => $imagenes,
            'specs' => $specs,
        ]);

        $this->recorder->record('product.created', $product, [
            'unidad_venta' => $unidadVenta->value,
        ]);

        return $product;
    }
}

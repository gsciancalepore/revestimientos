<?php

namespace App\Actions;

use App\Enums\ProductSaleUnit;
use App\Models\Product;
use App\Services\AuditRecorder;
use App\Services\ProductSlugGenerator;

class UpdateProductAction
{
    public function __construct(
        private AuditRecorder $recorder,
        private ProductSlugGenerator $slugGenerator,
    ) {}

    /**
     * @param  array<string, mixed>|null  $imagenes
     * @param  array<string, mixed>|null  $specs
     */
    public function execute(
        Product $product,
        int $categoryId,
        string $name,
        string $codigo,
        ProductSaleUnit $unidadVenta,
        int $precioCents,
        ?string $slug = null,
        ?string $marca = null,
        ?string $descripcion = null,
        ?int $precioOfertaCents = null,
        ?string $m2PorCaja = null,
        int $stock = 0,
        bool $activo = true,
        ?array $imagenes = null,
        ?array $specs = null,
    ): Product {
        // Spec 03, regla 67: si el producto tuviera pedidos, se bloquea el
        // cambio de unidad_venta. Se activa cuando exista la tabla `orders`
        // (Spec 05).
        $product->fill([
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $this->slugGenerator->uniqueFor($name, $slug, $product->id),
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

        $changes = $product->getDirty();

        $product->save();

        if (isset($changes['precio_cents'])) {
            $this->recorder->record('product.price_changed', $product, [
                'previous' => $product->getOriginal('precio_cents'),
                'new' => $product->precio_cents,
            ]);
        }

        if (isset($changes['stock'])) {
            $this->recorder->record('product.stock_changed', $product, [
                'previous' => $product->getOriginal('stock'),
                'new' => $product->stock,
            ]);
        }

        if (isset($changes['activo']) && ! $product->activo) {
            $this->recorder->record('product.deactivated', $product);
        }

        return $product;
    }
}

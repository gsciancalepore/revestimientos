<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class Cart
{
    public const SESSION_KEY = 'cart';

    /**
     * Obtener items crudos de sesión: [product_id => cantidad].
     *
     * @return array<int, int>
     */
    public function items(): array
    {
        /** @var array<int, int> $items */
        $items = Session::get(self::SESSION_KEY, []);

        return $items;
    }

    /**
     * @param  array<int, int>  $items
     */
    public function putItems(array $items): void
    {
        Session::put(self::SESSION_KEY, $items);
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    public function count(): int
    {
        return count($this->items());
    }

    /**
     * Líneas enriquecidas con producto, precio y condición comprable.
     *
     * Condición derivada al leer (Spec 05, regla 92):
     * comprable = activo && cantidad <= stock.
     *
     * @return Collection<int, array{product: Product, cantidad: int, precioUnitario: int, subtotal: int, comprable: bool}>
     */
    public function lines(): Collection
    {
        $items = $this->items();

        if ($items === []) {
            return collect();
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()->whereIn('id', array_keys($items))->with('category')->get()->keyBy('id');

        return collect($items)->map(function (int $cantidad, int $productId) use ($products): ?array {
            $product = $products->get($productId);

            if ($product === null) {
                return null;
            }

            $precioUnitario = $product->isM2Mode() ? ($product->precioCajaCents() ?? 0) : $product->precio_cents;
            $comprable = $product->activo && $cantidad <= $product->stock && $cantidad >= 1;

            return [
                'product' => $product,
                'cantidad' => $cantidad,
                'precioUnitario' => $precioUnitario,
                'subtotal' => $precioUnitario * $cantidad,
                'comprable' => $comprable,
            ];
        })->filter()->values();
    }

    /**
     * Subtotal = Σ subtotal_línea (solo líneas comprables).
     * Si una línea es no comprable se excluye del subtotal.
     */
    public function subtotal(): int
    {
        return $this->lines()->filter(fn (array $line): bool => $line['comprable'])->sum(fn (array $line): int => $line['subtotal']);
    }

    public function hasUnpurchasable(): bool
    {
        return $this->lines()->contains(fn (array $line): bool => $line['comprable'] === false);
    }

    /**
     * Agregar producto acumulando cantidad (regla 89).
     *
     * @throws \DomainException
     */
    public function add(Product $product, int $cantidad): void
    {
        if ($cantidad < 1) {
            throw new \DomainException('La cantidad debe ser al menos 1.');
        }

        if (! $product->activo) {
            throw new \DomainException('El producto no está disponible.');
        }

        if ($product->stock < 1) {
            throw new \DomainException('El producto no tiene stock disponible.');
        }

        $items = $this->items();
        $existente = $items[$product->id] ?? 0;
        $nueva = $existente + $cantidad;

        if ($nueva > $product->stock) {
            throw new \DomainException('La cantidad solicitada supera el stock disponible.');
        }

        $items[$product->id] = $nueva;
        $this->putItems($items);
    }

    /**
     * Actualizar cantidad reemplazando (regla 90). Cantidad 0 elimina.
     *
     * @throws \DomainException
     */
    public function update(Product $product, int $cantidad): void
    {
        $items = $this->items();

        if (! array_key_exists($product->id, $items)) {
            throw new \DomainException('El producto no está en el carrito.');
        }

        if ($cantidad < 1) {
            unset($items[$product->id]);
            $this->putItems($items);

            return;
        }

        if (! $product->activo) {
            throw new \DomainException('El producto no está disponible.');
        }

        if ($cantidad > $product->stock) {
            throw new \DomainException('La cantidad solicitada supera el stock disponible.');
        }

        $items[$product->id] = $cantidad;
        $this->putItems($items);
    }

    public function remove(Product $product): void
    {
        $items = $this->items();
        unset($items[$product->id]);
        $this->putItems($items);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}

<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductSlugGenerator
{
    /**
     * Build a slug unique across the whole catalog (Spec 04, regla 71).
     *
     * Vacío = auto-generar desde el nombre; si colisiona se agrega sufijo
     * `-2`, `-3`... Un slug provisto por el admin que colisiona se rechaza en
     * la validación del Form Request (la unicidad queda en manos del admin).
     */
    public function uniqueFor(string $name, ?string $slug = null, ?int $exceptId = null): string
    {
        $base = $slug !== null && trim($slug) !== ''
            ? Str::slug($slug)
            : Str::slug($name);

        if ($base === '') {
            $base = 'producto';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->productWithSlug($candidate, $exceptId)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return Builder<Product>
     */
    private function productWithSlug(string $slug, ?int $exceptId): Builder
    {
        $query = Product::query()->where('slug', $slug);

        return $exceptId === null ? $query : $query->where('id', '!=', $exceptId);
    }
}

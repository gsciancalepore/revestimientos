<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategorySlugGenerator
{
    /**
     * Build a slug unique across the whole catalog.
     *
     * Categories are flat (Spec 02 revision, 2026-08-05): name and slug are
     * unique globally, not among siblings.
     */
    public function uniqueFor(string $name, ?string $slug = null, ?int $exceptId = null): string
    {
        $base = $slug !== null && trim($slug) !== ''
            ? Str::slug($slug)
            : Str::slug($name);

        if ($base === '') {
            $base = 'categoria';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->categoryWithSlug($candidate, $exceptId)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return Builder<Category>
     */
    private function categoryWithSlug(string $slug, ?int $exceptId): Builder
    {
        $query = Category::query()->where('slug', $slug);

        return $exceptId === null ? $query : $query->where('id', '!=', $exceptId);
    }
}

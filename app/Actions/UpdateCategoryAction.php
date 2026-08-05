<?php

namespace App\Actions;

use App\Models\Category;
use App\Services\CategorySlugGenerator;

class UpdateCategoryAction
{
    public function __construct(private CategorySlugGenerator $slugGenerator) {}

    public function execute(Category $category, string $name, ?string $slug = null, int $sortOrder = 0): Category
    {
        $category->fill([
            'name' => $name,
            'slug' => $this->slugGenerator->uniqueFor($name, $slug, $category->id),
            'sort_order' => $sortOrder,
        ])->save();

        return $category;
    }
}

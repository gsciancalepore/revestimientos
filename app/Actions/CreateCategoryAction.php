<?php

namespace App\Actions;

use App\Models\Category;
use App\Services\CategorySlugGenerator;

class CreateCategoryAction
{
    public function __construct(private CategorySlugGenerator $slugGenerator) {}

    public function execute(string $name, ?string $slug = null, int $sortOrder = 0): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $this->slugGenerator->uniqueFor($name, $slug),
            'sort_order' => $sortOrder,
        ]);
    }
}

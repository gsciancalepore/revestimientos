<?php

namespace App\Actions;

use App\Models\Category;
use DomainException;

class DeleteCategoryAction
{
    public function execute(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new DomainException('No se puede borrar una categoría con productos (Spec 02, regla 53).');
        }

        $category->delete();
    }
}

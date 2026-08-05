<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    /**
     * Seed the base category structure of the business (Spec 02, revisada:
     * categorías planas).
     *
     * @var list<string>
     */
    private const CATEGORIES = [
        'Porcelanatos',
        'Cerámicas',
        'Pastinas',
        'Adhesivos',
    ];

    /**
     * Seed the base categories of the business.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $name) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index],
            );
        }
    }
}

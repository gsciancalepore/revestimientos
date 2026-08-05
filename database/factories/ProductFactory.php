<?php

namespace Database\Factories;

use App\Enums\ProductSaleUnit;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'marca' => fake()->company(),
            'codigo' => 'ILV-'.fake()->unique()->numerify('#####'),
            'descripcion' => fake()->sentence(),
            'precio_cents' => fake()->numberBetween(500, 2000000),
            'precio_oferta_cents' => null,
            'unidad_venta' => ProductSaleUnit::M2,
            'm2_por_caja' => '1.15',
            'stock' => fake()->numberBetween(0, 50),
            'activo' => true,
            'imagenes' => null,
            'specs' => null,
        ];
    }

    public function m2Mode(): static
    {
        return $this->state(fn (array $attributes) => [
            'unidad_venta' => ProductSaleUnit::M2,
            'm2_por_caja' => '1.15',
        ]);
    }

    public function unitMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'unidad_venta' => ProductSaleUnit::Unidad,
            'm2_por_caja' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}

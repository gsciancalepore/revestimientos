<?php

namespace Database\Factories;

use App\Enums\ProductSaleUnit;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'product_codigo' => 'ILV-'.fake()->unique()->numerify('#####'),
            'marca' => fake()->company(),
            'unidad_venta' => ProductSaleUnit::M2->value,
            'm2_por_caja' => '1.15',
            'cantidad' => fake()->numberBetween(1, 10),
            'precio_unitario_cents' => fake()->numberBetween(50000, 200000),
            'subtotal_cents' => fake()->numberBetween(50000, 500000),
            'specs' => null,
        ];
    }

    public function unitMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'unidad_venta' => ProductSaleUnit::Unidad->value,
            'm2_por_caja' => null,
        ]);
    }

    public function m2Mode(): static
    {
        return $this->state(fn (array $attributes) => [
            'unidad_venta' => ProductSaleUnit::M2->value,
            'm2_por_caja' => '1.15',
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\ShippingRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingRate>
 */
class ShippingRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cp' => fake()->unique()->numerify('####'),
            'costo_cents' => fake()->numberBetween(50000, 300000),
            'activo' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }
}

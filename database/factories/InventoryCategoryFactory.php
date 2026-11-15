<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCategory>
 */
class InventoryCategoryFactory extends Factory
{
    protected $model = InventoryCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->bothify('CAT-###')),
            'classification' => fake()->randomElement(['durable', 'consumable']),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }

    public function durable(): static
    {
        return $this->state(fn () => ['classification' => 'durable']);
    }

    public function consumable(): static
    {
        return $this->state(fn () => ['classification' => 'consumable']);
    }
}

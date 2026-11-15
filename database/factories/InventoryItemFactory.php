<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'inventory_category_id' => InventoryCategory::factory(),
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('INV-####')),
            'unit_of_measure' => fake()->randomElement(['Pieces', 'Boxes', 'Packs', 'Bottles', 'Sets']),
            'min_stock_threshold' => fake()->numberBetween(5, 25),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}

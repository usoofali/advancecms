<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'inventory_location_id' => InventoryLocation::factory(),
            'quantity' => fake()->numberBetween(0, 100),
            'min_threshold_override' => null,
            'last_counted_at' => now(),
            'last_counted_by' => null,
        ];
    }
}

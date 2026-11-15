<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    protected $model = InventoryLocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->bothify('LOC-###')),
            'location_type' => fake()->randomElement(['lab_clinic', 'store_warehouse', 'office_hall', 'workshop']),
            'building_or_block' => 'Block '.strtoupper(fake()->randomLetter()),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}

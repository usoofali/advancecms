<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => $this->faker->name().' Dept',
            'faculty' => $this->faker->word().' Faculty',
            'description' => $this->faker->word().' description',
            'status' => 'active',
        ];
    }
}

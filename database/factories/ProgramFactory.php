<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => $this->faker->name().' Program',
            'duration_years' => $this->faker->numberBetween(2, 4),
            'award_type' => $this->faker->randomElement(['diploma', 'degree', 'certificate']),
            'status' => 'active',
        ];
    }
}

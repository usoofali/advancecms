<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '20'.$this->faker->numberBetween(24, 30).'/'.'20'.$this->faker->numberBetween(31, 40),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'status' => 'active',
        ];
    }
}

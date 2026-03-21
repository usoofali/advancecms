<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'matric_number' => strtoupper($this->faker->unique()->bothify('??/???/####/###')),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'date_of_birth' => $this->faker->date('Y-m-d', '-18 years'),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'admission_year' => $this->faker->numberBetween(2018, 2024),
            'entry_level' => 100,
            'status' => 'active',
        ];
    }
}

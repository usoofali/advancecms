<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'name' => $this->faker->randomElement(['first', 'second']),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
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
            'course_code' => strtoupper($this->faker->bothify('???###')),
            'title' => $this->faker->name().' Course',
            'credit_unit' => $this->faker->numberBetween(1, 4),
            'level' => $this->faker->randomElement([100, 200, 300, 400]),
            'semester' => $this->faker->randomElement([1, 2]),
            'status' => 'active',
        ];
    }
}

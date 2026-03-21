<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Result>
 */
class ResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'semester_id' => Semester::factory(),
            'ca_score' => $this->faker->numberBetween(0, 30),
            'exam_score' => $this->faker->numberBetween(0, 70),
            'total_score' => 0,
            'grade' => '?',
            'grade_point' => 0.0,
            'remark' => 'pending',
        ];
    }
}

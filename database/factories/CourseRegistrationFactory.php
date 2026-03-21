<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseRegistration>
 */
class CourseRegistrationFactory extends Factory
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
            'status' => 'registered',
        ];
    }
}

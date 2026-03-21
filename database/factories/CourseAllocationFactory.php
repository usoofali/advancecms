<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\Institution;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseAllocation>
 */
class CourseAllocationFactory extends Factory
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
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'semester_id' => Semester::factory(),
        ];
    }
}

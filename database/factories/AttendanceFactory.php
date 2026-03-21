<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\CourseAllocation;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
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
            'course_allocation_id' => CourseAllocation::factory(),
            'date' => $this->faker->date(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'total_present' => 30,
            'total_absent' => 5,
            'status' => 'submitted',
            'is_combined_child' => false,
            'combined_group_id' => null,
        ];
    }
}

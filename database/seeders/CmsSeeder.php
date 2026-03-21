<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $institutions = Institution::factory()->count(2)->create();

        foreach ($institutions as $institution) {
            $departments = Department::factory()->count(3)->create(['institution_id' => $institution->id]);

            foreach ($departments as $department) {
                $programs = Program::factory()->count(2)->create(['department_id' => $department->id]);

                foreach ($programs as $program) {
                    Student::factory()->count(10)->create(['program_id' => $program->id]);
                    Course::factory()->count(5)->create(['department_id' => $department->id]);
                }
            }
        }

        $session = AcademicSession::factory()->create(['status' => 'active']);
        Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
        Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'second']);
    }
}

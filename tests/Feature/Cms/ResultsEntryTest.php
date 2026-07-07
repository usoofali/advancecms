<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function seedEntryFixture(): array
{
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);
    $session = AcademicSession::factory()->create();
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'first',
    ]);
    $courseA = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'CSC101',
    ]);
    $courseB = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'MTH101',
    ]);
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'email' => null,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_id' => $courseA->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'level' => 100,
        'status' => 'registered',
        'is_carryover' => false,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_id' => $courseB->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'level' => 100,
        'status' => 'registered',
        'is_carryover' => false,
    ]);

    Result::query()->create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'course_id' => $courseA->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'ca_score' => 25,
        'exam_score' => 60,
        'total_score' => 85,
        'grade' => 'A',
        'grade_point' => 5.0,
        'remark' => 'pass',
    ]);

    Result::query()->create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'course_id' => $courseB->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'ca_score' => 15,
        'exam_score' => 45,
        'total_score' => 60,
        'grade' => 'B',
        'grade_point' => 4.0,
        'remark' => 'pass',
    ]);

    return [
        'institution' => $institution,
        'session' => $session,
        'semester' => $semester,
        'courseA' => $courseA,
        'courseB' => $courseB,
        'student' => $student,
    ];
}

it('loads scores and increments score version when switching courses', function (): void {
    $f = seedEntryFixture();
    $user = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    $test = Livewire::test('pages::cms.results.entry')
        ->set('session_id', (string) $f['session']->id)
        ->set('semester_id', (string) $f['semester']->id)
        ->set('course_id', (string) $f['courseA']->id);

    $test->assertSet('scores.'.$f['student']->id.'.ca', 25.0)
        ->assertSet('scores.'.$f['student']->id.'.exam', 60.0)
        ->assertSet('scoreVersion', 1);

    // Switch to Course B
    $test->set('course_id', (string) $f['courseB']->id)
        ->assertSet('scores.'.$f['student']->id.'.ca', 15.0)
        ->assertSet('scores.'.$f['student']->id.'.exam', 45.0)
        ->assertSet('scoreVersion', 2);
});

it('clears scores when course selection is set to null string', function (): void {
    $f = seedEntryFixture();
    $user = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::cms.results.entry')
        ->set('session_id', (string) $f['session']->id)
        ->set('semester_id', (string) $f['semester']->id)
        ->set('course_id', (string) $f['courseA']->id)
        ->assertSet('scores.'.$f['student']->id.'.ca', 25.0)
        ->set('course_id', 'null')
        ->assertSet('scores', []);
});

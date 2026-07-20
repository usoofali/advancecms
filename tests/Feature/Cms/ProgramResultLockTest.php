<?php

use App\Livewire\Cms\Dashboards\StudentDashboard;
use App\Models\AcademicSession;
use App\Models\Course;
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

function seedResultLockFixture(): array
{
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
        'results_locked' => false,
    ]);
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'first',
    ]);
    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'CSC101',
        'credit_unit' => 3,
    ]);
    $user = User::factory()->for($institution)->create();
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'email' => $user->email,
        'photo_path' => null,
    ]);

    Result::query()->create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'ca_score' => 25,
        'exam_score' => 55,
        'total_score' => 80,
        'grade' => 'A',
        'grade_point' => 4.0,
        'remark' => 'pass',
    ]);

    return [
        'institution' => $institution,
        'department' => $department,
        'program' => $program,
        'session' => $session,
        'semester' => $semester,
        'course' => $course,
        'user' => $user,
        'student' => $student,
    ];
}

it('allows admin to toggle results_locked via index livewire action', function (): void {
    $f = seedResultLockFixture();
    $admin = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($admin);

    expect($f['program']->fresh()->results_locked)->toBeFalse();

    Livewire::test('pages::cms.programs.index')
        ->call('toggleResultLock', $f['program']->id);

    expect($f['program']->fresh()->results_locked)->toBeTrue();
});

it('hides results on portal when student program has results locked', function (): void {
    $f = seedResultLockFixture();
    $f['program']->update(['results_locked' => true]);

    $this->actingAs($f['user']);

    Livewire::test('pages::cms.results.portal')
        ->set('filterSession', (string) $f['session']->id)
        ->set('filterSemester', (string) $f['semester']->id)
        ->assertSee(__('Results Being Processed'), false)
        ->assertDontSee('CSC101', false);
});

it('shows results to staff on portal when student program has results locked', function (): void {
    $f = seedResultLockFixture();
    $f['program']->update(['results_locked' => true]);

    $staff = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($staff);

    Livewire::withQueryParams(['student' => $f['student']->id])
        ->test('pages::cms.results.portal')
        ->set('filterSession', (string) $f['session']->id)
        ->set('filterSemester', (string) $f['semester']->id)
        ->assertSee(__('Student Access Locked:'), false)
        ->assertSee('CSC101', false);
});

it('hides gpa and scores on student dashboard when program results locked', function (): void {
    $f = seedResultLockFixture();
    $f['program']->update(['results_locked' => true]);

    $this->actingAs($f['user']);

    Livewire::test(StudentDashboard::class)
        ->assertSee(__('Processing...'), false)
        ->assertSee(__('Results Being Processed'), false);
});

it('displays no photo placeholder on portal print view when student has no photo', function (): void {
    $f = seedResultLockFixture();

    $this->actingAs($f['user']);

    Livewire::test('pages::cms.results.portal')
        ->set('filterSession', (string) $f['session']->id)
        ->set('filterSemester', (string) $f['semester']->id)
        ->assertSee(__('No Photo'), false)
        ->assertSee($f['student']->full_name, false);
});

it('displays student photo url on portal print view when available', function (): void {
    $f = seedResultLockFixture();
    $f['student']->update(['photo_path' => 'students/photos/passport.jpg']);

    $this->actingAs($f['user']);

    Livewire::test('pages::cms.results.portal')
        ->set('filterSession', (string) $f['session']->id)
        ->set('filterSemester', (string) $f['semester']->id)
        ->assertSee('/storage/students/photos/passport.jpg', false);
});

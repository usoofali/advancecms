<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('preserves HOD department ID when active filters change', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $session = AcademicSession::factory()->create(['status' => 'active']);

    $hodRole = Role::where('role_name', 'Head of Department (HOD)')->first();

    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$hodRole->role_id]);

    // Scoped role mapping (polymorphic pivot)
    $user->assignScopedRole('Head of Department (HOD)', $department);

    $this->actingAs($user);

    Livewire::test('pages::cms.courses.allocations')
        ->assertOk()
        ->assertSet('isHod', true)
        ->assertSet('department_id', $department->id)
        ->set('session_id', $session->id)
        ->assertSet('department_id', $department->id); // Must still be the HOD's department!
});

it('filters courses select by selected semester and resets course_id when semester changes', function (): void {
    $institution = Institution::factory()->create();
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $firstSemester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
    $secondSemester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'second']);

    $course1 = Course::factory()->for($institution)->create(['semester' => 1, 'course_code' => 'CSC101']);
    $course2 = Course::factory()->for($institution)->create(['semester' => 2, 'course_code' => 'CSC102']);

    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$superRole->role_id]);

    $this->actingAs($user);

    Livewire::test('pages::cms.courses.allocations')
        ->assertOk()
        ->set('session_id', $session->id)
        ->set('semester_id', $firstSemester->id)
        ->assertViewHas('courses', function ($courses) use ($course1, $course2) {
            return $courses->contains($course1) && ! $courses->contains($course2);
        })
        ->set('course_id', $course1->id)
        ->assertSet('course_id', $course1->id)
        ->set('semester_id', $secondSemester->id)
        ->assertSet('course_id', 'null')
        ->assertViewHas('courses', function ($courses) use ($course1, $course2) {
            return ! $courses->contains($course1) && $courses->contains($course2);
        });
});

it('renders the course allocations print report with selected filter criteria', function (): void {
    $institution = Institution::factory()->create();
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
    $department = Department::factory()->for($institution)->create();

    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$superRole->role_id]);

    $this->actingAs($user);

    Livewire::withQueryParams([
        'session_id' => $session->id,
        'semester_id' => $semester->id,
        'department_id' => $department->id,
    ])->test('pages::cms.courses.print-allocations')
        ->assertOk()
        ->assertSee('Course Allocation Report');
});

it('displays program acronym without department name in course allocations table', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create(['name' => 'Hidden Department Name']);
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'name' => 'Full Program Name',
        'acronym' => 'ND-CS',
    ]);
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
    $course = Course::factory()->for($institution)->create([
        'department_id' => $department->id,
        'program_id' => $program->id,
        'course_code' => 'COM101',
    ]);

    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create(['institution_id' => $institution->id]);
    $user->roles()->sync([$superRole->role_id]);

    CourseAllocation::create([
        'institution_id' => $institution->id,
        'user_id' => $user->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::cms.courses.allocations')
        ->assertOk()
        ->assertSee('COM101')
        ->assertSee('ND-CS');

    $tbody = str($component->html())->after('<tbody');
    expect((string) $tbody)->not->toContain('Hidden Department Name');
    expect((string) $tbody)->not->toContain('Full Program Name');
});

<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Timetable;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('renders timetable manager for super admin', function (): void {
    $institution = Institution::factory()->create();
    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create(['institution_id' => $institution->id]);
    $user->roles()->sync([$superRole->role_id]);

    $this->actingAs($user);

    Livewire::test('pages::cms.timetables.index')
        ->assertOk();
});

it('allows creating a timetable slot with polymorphic course allocation', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($department)->create();
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
    $course = Course::factory()->for($institution)->create(['department_id' => $department->id, 'program_id' => $program->id]);
    $lecturer = User::factory()->create(['institution_id' => $institution->id]);

    $allocation = CourseAllocation::create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'user_id' => $lecturer->id,
    ]);

    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create(['institution_id' => $institution->id]);
    $user->roles()->sync([$superRole->role_id]);

    $this->actingAs($user);

    Livewire::test('pages::cms.timetables.index')
        ->set('session_id', $session->id)
        ->set('semester_id', $semester->id)
        ->set('department_id', $department->id)
        ->set('program_id', $program->id)
        ->set('level', '100')
        ->set('allocation_mode', 'allocation')
        ->set('selected_allocation_id', $allocation->id)
        ->set('day_of_week', 'Monday')
        ->set('period_number', 1)
        ->set('start_time', '08:00')
        ->set('end_time', '10:00')
        ->call('saveSlot');

    expect(Timetable::count())->toBe(1);

    $timetable = Timetable::first();
    expect($timetable->allocatable_type)->toBe(CourseAllocation::class);
    expect($timetable->allocatable_id)->toBe($allocation->id);
    expect($timetable->course_id)->toBe($course->id);
    expect($timetable->user_id)->toBe($lecturer->id);
    expect($timetable->day_of_week)->toBe('Monday');
    expect($timetable->period_number)->toBe(1);
});

it('renders my timetable for authorized user', function (): void {
    $institution = Institution::factory()->create();
    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create(['institution_id' => $institution->id]);
    $user->roles()->sync([$superRole->role_id]);

    $this->actingAs($user);

    Livewire::test('pages::cms.timetables.my-timetable')
        ->assertOk();
});

it('renders printable timetable view', function (): void {
    $institution = Institution::factory()->create();
    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create(['institution_id' => $institution->id]);
    $user->roles()->sync([$superRole->role_id]);

    $this->actingAs($user);

    Livewire::test('pages::cms.timetables.print-timetable')
        ->assertOk();
});

it('allows a student to render printable personal timetable view', function (): void {
    $institution = Institution::factory()->create();
    $studentRole = Role::where('role_name', 'Student')->first();
    $student = User::factory()->create(['institution_id' => $institution->id]);
    $student->roles()->sync([$studentRole->role_id]);

    $this->actingAs($student);

    $this->get(route('cms.timetables.print', ['personal' => 1]))
        ->assertOk();
});

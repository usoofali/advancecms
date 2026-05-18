<?php

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function createLecturerFixture(Institution $institution, string $email, string $firstName = 'John', string $lastName = 'Doe'): User
{
    $role = Role::where('role_name', 'Lecturer')->first();

    $user = User::factory()->create([
        'email' => $email,
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$role->role_id]);

    DB::table('staff')->insert([
        'institution_id' => $institution->id,
        'role_id' => $role->role_id,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => '1234567890',
        'designation' => 'Lecturer',
        'attendance_allowance' => 1500.0,
        'staff_number' => 'STF/'.now()->year.'/'.strtoupper(Str::random(6)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function createAdminFixture(Institution $institution, string $email): User
{
    $role = Role::where('role_name', 'Institutional Admin')->first();

    $user = User::factory()->create([
        'email' => $email,
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$role->role_id]);

    DB::table('staff')->insert([
        'institution_id' => $institution->id,
        'role_id' => $role->role_id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '0987654321',
        'designation' => 'Admin',
        'staff_number' => 'STF/'.now()->year.'/'.strtoupper(Str::random(6)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function createAttendanceFixture(Institution $institution, User $lecturer, string $date = '2026-05-18'): Attendance
{
    $department = Department::factory()->for($institution)->create();
    $course = Course::factory()->create(['department_id' => $department->id]);
    $session = AcademicSession::factory()->create(['name' => 'Session '.Str::random(8)]);
    $semester = Semester::factory()->create(['name' => 'first']);

    $allocation = CourseAllocation::create([
        'institution_id' => $institution->id,
        'user_id' => $lecturer->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
    ]);

    $attendance = Attendance::create([
        'institution_id' => $institution->id,
        'course_allocation_id' => $allocation->id,
        'date' => $date,
        'status' => 'submitted',
        'is_combined_child' => false,
        'created_by' => $lecturer->id,
    ]);

    $student = Student::factory()->create(['institution_id' => $institution->id]);
    AttendanceRecord::create([
        'attendance_id' => $attendance->id,
        'student_id' => $student->id,
        'is_present' => true,
    ]);

    return $attendance;
}

it('authorizes only users with attendance.view_history to view My Attendance History', function (): void {
    $institution = Institution::factory()->create();

    $student = User::factory()->withRole('Student')->create(['institution_id' => $institution->id]);
    $lecturer = createLecturerFixture($institution, 'lecturer@example.com');

    $this->actingAs($student);
    Livewire::test('pages::cms.attendance.history')
        ->assertForbidden();

    $this->actingAs($lecturer);
    Livewire::test('pages::cms.attendance.history')
        ->assertOk();
});

it('scopes My Attendance History strictly to own user allocations', function (): void {
    $institution = Institution::factory()->create();
    $lecturerA = createLecturerFixture($institution, 'lecturerA@example.com', 'Lecturer', 'A');
    $lecturerB = createLecturerFixture($institution, 'lecturerB@example.com', 'Lecturer', 'B');

    createAttendanceFixture($institution, $lecturerA, '2026-05-18');
    createAttendanceFixture($institution, $lecturerB, '2026-05-18');

    $this->actingAs($lecturerA);

    $stats = Livewire::test('pages::cms.attendance.history')
        ->set('month', '5')
        ->set('year', '2026')
        ->instance()
        ->monthly_stats;

    expect($stats['contacts'])->toBe(1)
        ->and($stats['amount'])->toEqual(1500.0);
});

it('authorizes only Admins/HODs to view Manage Attendance page', function (): void {
    $institution = Institution::factory()->create();
    $lecturer = createLecturerFixture($institution, 'lecturer@example.com');
    $admin = createAdminFixture($institution, 'admin@example.com');

    $this->actingAs($lecturer);
    Livewire::test('pages::cms.attendance.manage')
        ->assertForbidden();

    $this->actingAs($admin);
    Livewire::test('pages::cms.attendance.manage')
        ->assertOk();
});

it('aggregates statistics across all lecturers for institutional admin', function (): void {
    $institution = Institution::factory()->create();
    $lecturerA = createLecturerFixture($institution, 'lecturerA@example.com', 'Lecturer', 'A');
    $lecturerB = createLecturerFixture($institution, 'lecturerB@example.com', 'Lecturer', 'B');

    createAttendanceFixture($institution, $lecturerA, '2026-05-18');
    createAttendanceFixture($institution, $lecturerB, '2026-05-18');

    $admin = createAdminFixture($institution, 'admin@example.com');
    $this->actingAs($admin);

    $stats = Livewire::test('pages::cms.attendance.manage')
        ->set('month', '5')
        ->set('year', '2026')
        ->instance()
        ->monthly_stats;

    expect($stats['contacts'])->toBe(2)
        ->and($stats['amount'])->toEqual(3000.0)
        ->and($stats['students_marked'])->toBe(2);
});

it('allows auditing specific session participant roll inside modal', function (): void {
    $institution = Institution::factory()->create();
    $lecturer = createLecturerFixture($institution, 'lecturer@example.com');
    $attendance = createAttendanceFixture($institution, $lecturer, '2026-05-18');

    $admin = createAdminFixture($institution, 'admin@example.com');
    $this->actingAs($admin);

    $component = Livewire::test('pages::cms.attendance.manage')
        ->call('showStudentList', $attendance->id);

    expect($component->get('selectedAttendanceId'))->toBe($attendance->id)
        ->and(count($component->get('modalStudents')))->toBe(1);
});

<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\GradingSystem;
use App\Models\Institution;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Result;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\BatchCourseRegistrationService;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

/**
 * @return array{
 *     institution: Institution,
 *     department: Department,
 *     program: Program,
 *     gradingSystem: GradingSystem,
 *     prevSession1: AcademicSession,
 *     prevSession2: AcademicSession,
 *     targetSession: AcademicSession,
 *     prevSemester1: Semester,
 *     prevSemester2: Semester,
 *     targetSemester: Semester,
 *     course100: Course,
 *     course100SecondSem: Course,
 *     course200: Course,
 *     student100: Student,
 *     student200: Student,
 * }
 */
function seedBatchRegistrationFixture(): array
{
    $institution = Institution::factory()->create();

    // Custom department grading system where pass threshold is 45+ (0-44 is F / fail)
    $gradingSystem = GradingSystem::create([
        'institution_id' => $institution->id,
        'name' => 'Dept 5-Point Scale',
        'scale' => [
            ['grade' => 'A', 'min' => 70, 'point' => 5.0],
            ['grade' => 'B', 'min' => 60, 'point' => 4.0],
            ['grade' => 'C', 'min' => 50, 'point' => 3.0],
            ['grade' => 'D', 'min' => 45, 'point' => 2.0],
            ['grade' => 'F', 'min' => 0, 'point' => 0.0],
        ],
    ]);

    $department = Department::factory()->for($institution)->create([
        'grading_system_id' => $gradingSystem->id,
    ]);

    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
    ]);

    $prevSession1 = AcademicSession::factory()->create([
        'name' => '2023/2024',
        'start_date' => '2023-09-01',
        'end_date' => '2024-07-01',
    ]);

    $prevSession2 = AcademicSession::factory()->create([
        'name' => '2024/2025',
        'start_date' => '2024-09-01',
        'end_date' => '2025-07-01',
    ]);

    $targetSession = AcademicSession::factory()->create([
        'name' => '2025/2026',
        'start_date' => '2025-09-01',
        'end_date' => '2026-07-01',
    ]);

    $prevSemester1 = Semester::factory()->create([
        'academic_session_id' => $prevSession1->id,
        'name' => 'first',
    ]);

    $prevSemester2 = Semester::factory()->create([
        'academic_session_id' => $prevSession2->id,
        'name' => 'first',
    ]);

    $targetSemester = Semester::factory()->create([
        'academic_session_id' => $targetSession->id,
        'name' => 'first',
    ]);

    $course100 = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'CSC101',
    ]);

    $course100SecondSem = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 2,
        'course_code' => 'CSC102',
    ]);

    $course200 = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 200,
        'semester' => 1,
        'course_code' => 'CSC201',
    ]);

    // 100-level student in 2025/2026
    $student100 = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'entry_level' => 100,
        'admission_year' => 2025,
        'status' => 'active',
        'email' => null,
    ]);

    // 200-level student in 2025/2026 (admitted in 2024 at 100L)
    $student200 = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'entry_level' => 100,
        'admission_year' => 2024,
        'status' => 'active',
        'email' => null,
    ]);

    return compact(
        'institution',
        'department',
        'program',
        'gradingSystem',
        'prevSession1',
        'prevSession2',
        'targetSession',
        'prevSemester1',
        'prevSemester2',
        'targetSemester',
        'course100',
        'course100SecondSem',
        'course200',
        'student100',
        'student200'
    );
}

it('registers level-specific courses for mixed-level students in the selected semester', function (): void {
    $f = seedBatchRegistrationFixture();
    $service = app(BatchCourseRegistrationService::class);

    $metrics = $service->registerChunk(
        studentIds: [$f['student100']->id, $f['student200']->id],
        institutionId: $f['institution']->id,
        sessionId: $f['targetSession']->id,
        semesterId: $f['targetSemester']->id,
    );

    expect($metrics)->toBe([
        'students_processed' => 2,
        'level_created' => 2,
        'carryover_created' => 0,
        'skipped' => 0,
    ]);

    expect(CourseRegistration::query()
        ->where('student_id', $f['student100']->id)
        ->where('academic_session_id', $f['targetSession']->id)
        ->where('semester_id', $f['targetSemester']->id)
        ->pluck('course_id')
        ->all()
    )->toBe([$f['course100']->id]);

    expect(CourseRegistration::query()
        ->where('student_id', $f['student200']->id)
        ->where('academic_session_id', $f['targetSession']->id)
        ->where('semester_id', $f['targetSemester']->id)
        ->pluck('course_id')
        ->all()
    )->toBe([$f['course200']->id]);
});

it('detects carryovers using the department grading system and excludes courses passed later', function (): void {
    $f = seedBatchRegistrationFixture();

    $courseFailedStill = Course::factory()->create([
        'institution_id' => $f['institution']->id,
        'department_id' => $f['department']->id,
        'program_id' => $f['program']->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'MTH101',
    ]);

    $coursePassedLater = Course::factory()->create([
        'institution_id' => $f['institution']->id,
        'department_id' => $f['department']->id,
        'program_id' => $f['program']->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'PHY101',
    ]);

    // student200 failed MTH101 (score 42 < 45 dept pass threshold) in 2023/2024, even if stored remark was stale
    Result::query()->create([
        'institution_id' => $f['institution']->id,
        'student_id' => $f['student200']->id,
        'course_id' => $courseFailedStill->id,
        'academic_session_id' => $f['prevSession1']->id,
        'semester_id' => $f['prevSemester1']->id,
        'ca_score' => 12,
        'exam_score' => 30,
        'total_score' => 42,
        'grade' => 'E',
        'grade_point' => 1.0,
        'remark' => 'pass',
    ]);

    // student200 failed PHY101 in 2023/2024 (score 30) but passed it in 2024/2025 (score 52)
    Result::query()->create([
        'institution_id' => $f['institution']->id,
        'student_id' => $f['student200']->id,
        'course_id' => $coursePassedLater->id,
        'academic_session_id' => $f['prevSession1']->id,
        'semester_id' => $f['prevSemester1']->id,
        'ca_score' => 10,
        'exam_score' => 20,
        'total_score' => 30,
        'grade' => 'F',
        'grade_point' => 0.0,
        'remark' => 'fail',
    ]);

    Result::query()->create([
        'institution_id' => $f['institution']->id,
        'student_id' => $f['student200']->id,
        'course_id' => $coursePassedLater->id,
        'academic_session_id' => $f['prevSession2']->id,
        'semester_id' => $f['prevSemester2']->id,
        'ca_score' => 20,
        'exam_score' => 32,
        'total_score' => 52,
        'grade' => 'C',
        'grade_point' => 3.0,
        'remark' => 'pass',
    ]);

    $service = app(BatchCourseRegistrationService::class);
    $metrics = $service->registerChunk(
        studentIds: [$f['student200']->id],
        institutionId: $f['institution']->id,
        sessionId: $f['targetSession']->id,
        semesterId: $f['targetSemester']->id,
    );

    expect($metrics)->toBe([
        'students_processed' => 1,
        'level_created' => 1,
        'carryover_created' => 1,
        'skipped' => 0,
    ]);

    $carryoverReg = CourseRegistration::query()
        ->where('student_id', $f['student200']->id)
        ->where('course_id', $courseFailedStill->id)
        ->where('academic_session_id', $f['targetSession']->id)
        ->where('semester_id', $f['targetSemester']->id)
        ->first();

    expect($carryoverReg)->not->toBeNull()
        ->and($carryoverReg->is_carryover)->toBeTrue();

    expect(CourseRegistration::query()
        ->where('student_id', $f['student200']->id)
        ->where('course_id', $coursePassedLater->id)
        ->where('academic_session_id', $f['targetSession']->id)
        ->exists()
    )->toBeFalse();
});

it('skips already-registered courses without creating duplicates', function (): void {
    $f = seedBatchRegistrationFixture();

    CourseRegistration::create([
        'student_id' => $f['student100']->id,
        'course_id' => $f['course100']->id,
        'academic_session_id' => $f['targetSession']->id,
        'semester_id' => $f['targetSemester']->id,
        'level' => 100,
        'status' => 'registered',
        'is_carryover' => false,
    ]);

    $service = app(BatchCourseRegistrationService::class);
    $metrics = $service->registerChunk(
        studentIds: [$f['student100']->id],
        institutionId: $f['institution']->id,
        sessionId: $f['targetSession']->id,
        semesterId: $f['targetSemester']->id,
    );

    expect($metrics)->toBe([
        'students_processed' => 1,
        'level_created' => 0,
        'carryover_created' => 0,
        'skipped' => 1,
    ]);

    expect(CourseRegistration::query()
        ->where('student_id', $f['student100']->id)
        ->where('course_id', $f['course100']->id)
        ->where('academic_session_id', $f['targetSession']->id)
        ->where('semester_id', $f['targetSemester']->id)
        ->count()
    )->toBe(1);
});

it('runs chunked batch registration via the Livewire component and updates progress metrics', function (): void {
    $f = seedBatchRegistrationFixture();

    // Add a carryover for student200
    $failedCourse = Course::factory()->create([
        'institution_id' => $f['institution']->id,
        'department_id' => $f['department']->id,
        'program_id' => $f['program']->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'GST101',
    ]);

    Result::query()->create([
        'institution_id' => $f['institution']->id,
        'student_id' => $f['student200']->id,
        'course_id' => $failedCourse->id,
        'academic_session_id' => $f['prevSession2']->id,
        'semester_id' => $f['prevSemester2']->id,
        'ca_score' => 10,
        'exam_score' => 25,
        'total_score' => 35,
        'grade' => 'F',
        'grade_point' => 0.0,
        'remark' => 'fail',
    ]);

    $admin = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($admin);

    Livewire::test('pages::cms.students.registration')
        ->call('openBatchModal')
        ->set('batch_session_id', (string) $f['targetSession']->id)
        ->set('batch_semester_id', (string) $f['targetSemester']->id)
        ->set('batch_department_id', (string) $f['department']->id)
        ->set('batch_program_id', (string) $f['program']->id)
        ->call('startBatchRegistration')
        ->assertSet('batchTotalStudents', 2)
        ->assertSet('batchProcessedCount', 2)
        ->assertSet('batchLevelCreated', 3)
        ->assertSet('batchCarryoverCreated', 1)
        ->assertSet('batchSkipped', 0)
        ->assertSet('batchRunning', false)
        ->assertSet('batchCompleted', true)
        ->assertDispatched('notify');
});

it('forbids batch registration actions for users without registrations.create permission', function (): void {
    $f = seedBatchRegistrationFixture();

    $viewPerm = Permission::firstOrCreate(
        ['permission_name' => 'registrations.view'],
        ['description' => 'View Registrations']
    );

    $role = Role::create([
        'role_name' => 'Registration Viewer',
        'description' => 'Can view registrations only',
    ]);
    $role->permissions()->attach($viewPerm);

    $viewer = User::factory()
        ->for($f['institution'])
        ->create();
    $viewer->roles()->attach($role);

    $this->actingAs($viewer);

    Livewire::test('pages::cms.students.registration')
        ->assertOk()
        ->call('openBatchModal')
        ->assertForbidden();

    Livewire::test('pages::cms.students.registration')
        ->assertOk()
        ->call('startBatchRegistration')
        ->assertForbidden();
});

it('populates department and program options in the batch modal for super admins', function (): void {
    $f = seedBatchRegistrationFixture();

    $superAdmin = User::factory()
        ->withRole('Super Admin')
        ->create(['institution_id' => null]);

    $this->actingAs($superAdmin);

    Livewire::test('pages::cms.students.registration')
        ->call('openBatchModal')
        ->assertSet('showBatchModal', true)
        ->assertSee($f['department']->name)
        ->assertSee($f['program']->acronym ?: $f['program']->name);
});

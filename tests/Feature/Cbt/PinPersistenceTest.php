<?php

use App\Models\AcademicSession;
use App\Models\CbtExam;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentCbtProfile;
use App\Services\Cbt\ExamPackagingService;
use App\Services\Cbt\PinGeneratorService;
use Illuminate\Support\Str;

it('persists CBT login PINs across repeated generator calls', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
    ]);
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
    ]);

    $service = new PinGeneratorService;

    // First generation
    $service->generateForStudents(collect([$student]), $session, $semester);
    $initialProfile = StudentCbtProfile::where('student_id', $student->id)
        ->where('academic_session_id', $session->id)
        ->where('semester_id', $semester->id)
        ->first();

    expect($initialProfile)->not->toBeNull();
    $initialPin = $initialProfile->cbt_pin;

    // Second generation call should not change the PIN
    $service->generateForStudents(collect([$student]), $session, $semester);
    $updatedProfile = StudentCbtProfile::where('student_id', $student->id)
        ->where('academic_session_id', $session->id)
        ->where('semester_id', $semester->id)
        ->first();

    expect($updatedProfile->cbt_pin)->toBe($initialPin);
});

it('uses existing CBT login PIN during exam packaging', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
    ]);
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
    ]);
    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'level' => 100,
        'status' => 'registered',
        'is_carryover' => false,
    ]);

    $exam = CbtExam::create([
        'uuid' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'title' => 'Test Exam',
        'duration_minutes' => 60,
        'total_questions' => 10,
        'pass_mark' => 40.00,
        'status' => 'draft',
    ]);

    // Pre-create a CBT profile with a specific PIN
    $existingPin = '123456';
    StudentCbtProfile::create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'cbt_pin' => $existingPin,
        'last_generated_at' => now(),
    ]);

    $packagingService = new ExamPackagingService;
    $package = $packagingService->createPackage($exam);

    expect($package['success'])->toBeTrue();

    // Verify PIN remains unchanged in database and no duplicate was created
    $profilesInDb = StudentCbtProfile::where('student_id', $student->id)
        ->where('academic_session_id', $session->id)
        ->where('semester_id', $semester->id)
        ->get();

    expect($profilesInDb)->toHaveCount(1);
    expect($profilesInDb->first()->cbt_pin)->toBe($existingPin);
});

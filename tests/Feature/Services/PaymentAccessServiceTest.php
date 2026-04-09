<?php

use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Program;
use App\Models\Department;
use App\Models\Institution;
use App\Services\PaymentAccessService;

test('it correctly identifies required invoices for the student level', function () {
    $institution = Institution::factory()->create();
    $session = AcademicSession::factory()->create(['name' => '2025/2026']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
    
    $department = Department::factory()->create(['institution_id' => $institution->id]);
    $program = Program::factory()->create(['department_id' => $department->id, 'institution_id' => $institution->id]);
    
    // Student in 300L (2023 intake, 2025/2026 session)
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'admission_year' => 2023,
        'entry_level' => 100,
    ]);
    
    // Required Invoice template for 200L in the same program
    $invoice200 = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'target_type' => 'program',
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => '200',
        'is_required_for_results' => true,
        'status' => 'published',
    ]);

    // Required Invoice template for 300L (same program) - UNPAID
    $invoice300 = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'target_type' => 'program',
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => '300',
        'is_required_for_results' => true,
        'status' => 'published',
    ]);

    $service = app(PaymentAccessService::class);
    $missing = $service->getMissingInvoicesForResults($student, $session, $semester);

    // CURRENT BEHAVIOR (BUGGY): It returns both because it ignores 'level' constraint if target_type is 'program'.
    // We want it to ONLY return the 300L one.
    
    expect($missing)->toHaveCount(1);
    expect($missing->first()->id)->toBe($invoice300->id);
    expect($missing->pluck('id'))->not->toContain($invoice200->id);
});

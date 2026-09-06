<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
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

test('it correctly evaluates allowable payment percentage threshold for access restrictions', function () {
    $institution = Institution::factory()->create();
    $session = AcademicSession::factory()->create(['name' => '2025/2026']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);

    $department = Department::factory()->create(['institution_id' => $institution->id]);
    $program = Program::factory()->create(['department_id' => $department->id, 'institution_id' => $institution->id]);

    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'admission_year' => 2025,
        'entry_level' => 100,
    ]);

    // Invoice requiring 50% payment for exam card
    $invoiceExams = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'target_type' => 'dept',
        'department_id' => $department->id,
        'is_required_for_exams' => true,
        'required_percent_for_exams' => 50,
        'status' => 'published',
    ]);

    // Student invoice with 50% payment (5,000 paid out of 10,000)
    StudentInvoice::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'invoice_id' => $invoiceExams->id,
        'total_amount' => 10000,
        'amount_paid' => 5000,
        'balance' => 5000,
        'status' => 'partial',
    ]);

    $service = app(PaymentAccessService::class);
    $missingExams = $service->getMissingInvoicesForExamCard($student, $session, $semester);

    // 50% paid meets the 50% threshold, so not missing
    expect($missingExams)->toBeEmpty();

    // Now test with 40% paid on a 50% required invoice
    $invoiceResults = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'target_type' => 'dept',
        'department_id' => $department->id,
        'is_required_for_results' => true,
        'required_percent_for_results' => 50,
        'status' => 'published',
    ]);

    StudentInvoice::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'invoice_id' => $invoiceResults->id,
        'total_amount' => 10000,
        'amount_paid' => 4000,
        'balance' => 6000,
        'status' => 'partial',
    ]);

    $missingResults = $service->getMissingInvoicesForResults($student, $session, $semester);

    // 40% paid is less than 50% required, so invoice IS missing
    expect($missingResults)->toHaveCount(1);
    expect($missingResults->first()->id)->toBe($invoiceResults->id);
});

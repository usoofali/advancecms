<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\ProjectSession;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentProject;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('locks academic projects portal and displays required invoice when project fee is unpaid', function (): void {
    $institution = Institution::factory()->create();
    $session = AcademicSession::factory()->create(['status' => 'active', 'name' => '2025/2026']);
    $department = Department::factory()->create(['institution_id' => $institution->id]);
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
        'duration_years' => 2,
    ]);

    // Final year student (200L in 2-year program)
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'admission_year' => 2024,
        'entry_level' => 100,
    ]);

    $studentUser = User::where('email', $student->email)->first();

    $projectSession = ProjectSession::create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'title' => '2025/2026 Academic Project Session',
        'status' => 'Active',
    ]);

    $invoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'target_type' => 'dept',
        'department_id' => $department->id,
        'title' => 'Final Year Project Fee',
        'is_required_for_projects' => true,
        'required_percent_for_projects' => 100,
        'status' => 'published',
    ]);

    $this->actingAs($studentUser);

    // 1. Should show locked payment card
    Livewire::test('student.projects.index')
        ->assertSee('Project Fee Payment Required', false)
        ->assertSee('Final Year Project Fee', false);

    // 2. Action method registerSelf should be blocked
    Livewire::test('student.projects.index')
        ->call('registerSelf');

    expect(StudentProject::where('student_id', $student->id)->exists())->toBeFalse();

    // 3. Mark invoice as paid
    $studentInvoice = StudentInvoice::where('student_id', $student->id)->where('invoice_id', $invoice->id)->first();
    $studentInvoice->update([
        'amount_paid' => $studentInvoice->total_amount,
        'balance' => 0,
        'status' => 'paid',
    ]);

    // 4. Should now be unlocked and display registration card
    Livewire::test('student.projects.index')
        ->assertDontSee('Project Fee Payment Required', false)
        ->assertSee('Eligible for Academic Project Session', false);
});

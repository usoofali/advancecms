<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function createStudentInvoiceFixture(Institution $institution, float $totalAmount, float $amountPaid, ?int $departmentId = null): void
{
    $department = $departmentId
        ? Department::query()->findOrFail($departmentId)
        : Department::factory()->for($institution)->create();

    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);

    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
    ]);

    $invoice = Invoice::query()->create([
        'institution_id' => $institution->id,
        'title' => 'Fixture Invoice',
        'academic_session_id' => AcademicSession::factory()->create()->id,
        'due_date' => now()->addMonth()->toDateString(),
        'target_type' => 'dept',
        'department_id' => $department->id,
        'status' => 'published',
        'created_by' => User::factory()->create()->id,
    ]);

    StudentInvoice::query()->create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'total_amount' => $totalAmount,
        'amount_paid' => $amountPaid,
        'balance' => $totalAmount - $amountPaid,
        'status' => 'partial',
    ]);
}

it('scopes invoice metrics to the user institution for non-super-admin users', function (): void {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();

    createStudentInvoiceFixture($institutionA, 1000, 200);
    createStudentInvoiceFixture($institutionB, 5000, 1000);

    $user = User::factory()->withRole('Institutional Admin')->create([
        'institution_id' => $institutionA->id,
    ]);

    $this->actingAs($user);

    $stats = Livewire::test('pages::cms.invoices.invoice-index')
        ->instance()
        ->getStats();

    expect($stats['total_invoiced'])->toBe(1000.0)
        ->and($stats['total_paid'])->toBe(200.0)
        ->and($stats['outstanding'])->toBe(800.0);
});

it('shows institution-wide invoice metrics for super admin when no institution filter is set', function (): void {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();

    createStudentInvoiceFixture($institutionA, 1000, 200);
    createStudentInvoiceFixture($institutionB, 500, 100);

    $user = User::factory()->withRole('Super Admin')->create([
        'institution_id' => null,
    ]);

    $this->actingAs($user);

    $stats = Livewire::test('pages::cms.invoices.invoice-index')
        ->instance()
        ->getStats();

    expect($stats['total_invoiced'])->toBe(1500.0)
        ->and($stats['total_paid'])->toBe(300.0)
        ->and($stats['outstanding'])->toBe(1200.0);
});

it('scopes invoice metrics to the selected institution for super admin', function (): void {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();

    createStudentInvoiceFixture($institutionA, 1000, 250);
    createStudentInvoiceFixture($institutionB, 9999, 1);

    $user = User::factory()->withRole('Super Admin')->create([
        'institution_id' => null,
    ]);

    $this->actingAs($user);

    $stats = Livewire::test('pages::cms.invoices.invoice-index')
        ->set('institutionFilter', $institutionA->id)
        ->instance()
        ->getStats();

    expect($stats['total_invoiced'])->toBe(1000.0)
        ->and($stats['total_paid'])->toBe(250.0)
        ->and($stats['outstanding'])->toBe(750.0);
});

it('scopes invoice metrics by department filter', function (): void {
    $institution = Institution::factory()->create();
    $deptA = Department::factory()->for($institution)->create();
    $deptB = Department::factory()->for($institution)->create();

    createStudentInvoiceFixture($institution, 3000, 0, $deptA->id);
    createStudentInvoiceFixture($institution, 7000, 0, $deptB->id);

    $user = User::factory()->withRole('Institutional Admin')->create([
        'institution_id' => $institution->id,
    ]);

    $this->actingAs($user);

    $stats = Livewire::test('pages::cms.invoices.invoice-index')
        ->set('departmentFilter', $deptA->id)
        ->instance()
        ->getStats();

    expect($stats['total_invoiced'])->toBe(3000.0)
        ->and($stats['total_paid'])->toBe(0.0)
        ->and($stats['outstanding'])->toBe(3000.0);
});

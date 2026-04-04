<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Receipt;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function createPendingPaymentForInstitution(Institution $institution, string $reference): Payment
{
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'email' => null,
    ]);

    $invoice = Invoice::query()->create([
        'institution_id' => $institution->id,
        'title' => 'Fixture Invoice '.$reference,
        'academic_session_id' => AcademicSession::factory()->create()->id,
        'due_date' => now()->addMonth()->toDateString(),
        'target_type' => 'dept',
        'department_id' => $department->id,
        'status' => 'published',
        'created_by' => User::factory()->create()->id,
    ]);

    $studentInvoice = StudentInvoice::query()->create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'total_amount' => 5000,
        'amount_paid' => 0,
        'balance' => 5000,
        'status' => 'pending',
    ]);

    return Payment::query()->create([
        'institution_id' => $institution->id,
        'student_invoice_id' => $studentInvoice->id,
        'amount_paid' => 1000,
        'reference' => $reference,
        'status' => 'pending',
    ]);
}

it('lists only payments for the signed-in user institution', function (): void {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();

    createPendingPaymentForInstitution($institutionA, 'REF-SCOPE-A-UNIQUE');
    createPendingPaymentForInstitution($institutionB, 'REF-SCOPE-B-UNIQUE');

    $user = User::factory()->withRole('Accountant')->create([
        'institution_id' => $institutionA->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cms.invoices.payment-index')
        ->set('statusFilter', 'all')
        ->assertSee('REF-SCOPE-A-UNIQUE', false)
        ->assertDontSee('REF-SCOPE-B-UNIQUE', false);
});

it('lets super admin filter payments by institution', function (): void {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();

    createPendingPaymentForInstitution($institutionA, 'REF-SUPER-A-UNIQUE');
    createPendingPaymentForInstitution($institutionB, 'REF-SUPER-B-UNIQUE');

    $user = User::factory()->withRole('Super Admin')->create([
        'institution_id' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cms.invoices.payment-index')
        ->set('statusFilter', 'all')
        ->assertSee('REF-SUPER-A-UNIQUE', false)
        ->assertSee('REF-SUPER-B-UNIQUE', false)
        ->set('institutionFilter', $institutionA->id)
        ->assertSee('REF-SUPER-A-UNIQUE', false)
        ->assertDontSee('REF-SUPER-B-UNIQUE', false);
});

it('does not approve a payment outside the user institution scope', function (): void {
    Mail::fake();

    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();

    createPendingPaymentForInstitution($institutionA, 'REF-OWN-UNIQUE');
    $paymentOther = createPendingPaymentForInstitution($institutionB, 'REF-OTHER-UNIQUE');

    $user = User::factory()->withRole('Accountant')->create([
        'institution_id' => $institutionA->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cms.invoices.payment-index')
        ->set('approvingPaymentId', $paymentOther->id)
        ->call('approve');

    expect($paymentOther->fresh()->status)->toBe('pending')
        ->and(Receipt::query()->where('payment_id', $paymentOther->id)->exists())->toBeFalse();
});

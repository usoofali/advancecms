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

it('can search payments by reference, student name, student matric number', function (): void {
    $institution = Institution::factory()->create();

    $paymentA = createPendingPaymentForInstitution($institution, 'REF-SEARCH-ALPHA');
    $studentA = $paymentA->studentInvoice->student;
    $studentA->update([
        'first_name' => 'Adewale',
        'last_name' => 'Adekunle',
        'matric_number' => 'CMS/ADE/2026/001',
    ]);

    $paymentB = createPendingPaymentForInstitution($institution, 'REF-SEARCH-BETA');
    $studentB = $paymentB->studentInvoice->student;
    $studentB->update([
        'first_name' => 'Chinedu',
        'last_name' => 'Okonkwo',
        'matric_number' => 'CMS/CHI/2026/999',
    ]);

    $user = User::factory()->withRole('Accountant')->create([
        'institution_id' => $institution->id,
    ]);

    $this->actingAs($user);

    // 1. Search by reference
    Livewire::test('pages::cms.invoices.payment-index')
        ->set('statusFilter', 'all')
        ->set('search', 'ALPHA')
        ->assertSee('REF-SEARCH-ALPHA', false)
        ->assertDontSee('REF-SEARCH-BETA', false);

    // 2. Search by first name
    Livewire::test('pages::cms.invoices.payment-index')
        ->set('statusFilter', 'all')
        ->set('search', 'Adewale')
        ->assertSee('REF-SEARCH-ALPHA', false)
        ->assertDontSee('REF-SEARCH-BETA', false);

    // 3. Search by last name
    Livewire::test('pages::cms.invoices.payment-index')
        ->set('statusFilter', 'all')
        ->set('search', 'Adekunle')
        ->assertSee('REF-SEARCH-ALPHA', false)
        ->assertDontSee('REF-SEARCH-BETA', false);

    // 4. Search by matric number
    Livewire::test('pages::cms.invoices.payment-index')
        ->set('statusFilter', 'all')
        ->set('search', 'CHI/2026/999')
        ->assertDontSee('REF-SEARCH-ALPHA', false)
        ->assertSee('REF-SEARCH-BETA', false);
});

it('auto-generates unique reference when payment is created with empty or null reference', function (): void {
    $institution = Institution::factory()->create();
    $paymentA = createPendingPaymentForInstitution($institution, '');
    $paymentB = createPendingPaymentForInstitution($institution, '');

    expect($paymentA->reference)->not->toBeEmpty()
        ->and($paymentB->reference)->not->toBeEmpty()
        ->and($paymentA->reference)->not->toBe($paymentB->reference);
});

it('allows student to submit manual payment without reference in portal-invoices', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->create(['institution_id' => $institution->id]);
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
    ]);
    $academicSession = AcademicSession::factory()->create([
        'status' => 'active',
    ]);

    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
    ]);

    $user = User::where('email', $student->email)->first() ?? User::factory()->withRole('Student')->create([
        'institution_id' => $institution->id,
        'email' => $student->email,
    ]);
    if (! $user->hasRole('Student')) {
        $user->assignRole('Student');
    }

    $invoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'academic_session_id' => $academicSession->id,
    ]);

    $studentInvoice = StudentInvoice::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'total_amount' => 50000,
        'amount_paid' => 0,
        'balance' => 50000,
        'status' => 'unpaid',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cms.students.portal-invoices')
        ->call('selectInvoice', $studentInvoice->id)
        ->set('paymentAmount', 25000)
        ->set('paymentMethod', 'bank_transfer')
        ->set('paymentReference', '')
        ->call('submitPayment')
        ->assertHasNoErrors();

    $payment = Payment::where('student_invoice_id', $studentInvoice->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->amount_paid)->toEqual(25000)
        ->and($payment->reference)->toStartWith('MAN-');

    // Attempting to create another payment while pending exists should be prevented
    Livewire::test('pages::cms.students.portal-invoices')
        ->call('selectInvoice', $studentInvoice->id)
        ->assertDispatched('notify', fn ($name, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'warning');

    expect(Payment::where('student_invoice_id', $studentInvoice->id)->count())->toBe(1);

    // Deleting pending payment allows recording a new payment
    Livewire::test('pages::cms.students.portal-invoices')
        ->call('confirmDeletePayment', $payment->id)
        ->call('deletePayment')
        ->assertDispatched('notify', fn ($name, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'success');

    expect(Payment::where('student_invoice_id', $studentInvoice->id)->count())->toBe(0);

    // Now student can select invoice and record payment again
    Livewire::test('pages::cms.students.portal-invoices')
        ->call('selectInvoice', $studentInvoice->id)
        ->set('paymentAmount', 50000)
        ->set('paymentMethod', 'cash')
        ->set('paymentReference', 'CASH-REC-001')
        ->call('submitPayment')
        ->assertHasNoErrors();

    expect(Payment::where('student_invoice_id', $studentInvoice->id)->count())->toBe(1)
        ->and(Payment::where('student_invoice_id', $studentInvoice->id)->first()->reference)->toBe('CASH-REC-001');
});

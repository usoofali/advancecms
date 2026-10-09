<?php

use App\Livewire\Pages\Admissions\Apply;
use App\Models\AcademicSession;
use App\Models\Applicant;
use App\Models\ApplicationForm;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Program;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Services\AdmissionService;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('requires invoice_id and validates session match when creating an application form', function (): void {
    $institution = Institution::factory()->create();
    $sessionA = AcademicSession::factory()->create(['name' => '2025/2026']);
    $sessionB = AcademicSession::factory()->create(['name' => '2026/2027']);

    $invoiceA = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $sessionA->id,
        'category' => Invoice::CATEGORY_REGISTRATION,
        'title' => 'Session A Registration Fee',
    ]);

    $admin = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($admin);

    // 1. Missing invoice_id fails validation
    Volt::test('pages::cms.admissions.form-create')
        ->set('name', '2026/2027 Post UTME Form')
        ->set('amount', 5000)
        ->set('academic_session_id', $sessionB->id)
        ->set('invoice_id', null)
        ->call('save')
        ->assertHasErrors(['invoice_id']);

    // 2. Invoice with mismatched academic session fails validation
    Volt::test('pages::cms.admissions.form-create')
        ->set('name', '2026/2027 Post UTME Form')
        ->set('amount', 5000)
        ->set('academic_session_id', $sessionB->id)
        ->set('invoice_id', $invoiceA->id)
        ->call('save')
        ->assertHasErrors(['invoice_id']);
});

it('creates and updates application form with linked registration invoice', function (): void {
    $institution = Institution::factory()->create();
    $session = AcademicSession::factory()->create(['name' => '2026/2027']);
    $program = Program::factory()->create(['institution_id' => $institution->id]);

    $invoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'category' => Invoice::CATEGORY_REGISTRATION,
        'title' => 'Freshers Registration Fee',
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Tuition',
        'amount' => 50000,
    ]);

    $admin = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($admin);

    Volt::test('pages::cms.admissions.form-create')
        ->set('name', '2026/2027 General Admission Form')
        ->set('amount', 7500)
        ->set('program_id', $program->id)
        ->set('academic_session_id', $session->id)
        ->set('invoice_id', $invoice->id)
        ->set('category', 'fresh')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('cms.admissions.forms.index'));

    $createdForm = ApplicationForm::where('name', '2026/2027 General Admission Form')->first();
    expect($createdForm)->not->toBeNull();
    expect($createdForm->invoice_id)->toBe($invoice->id);
    expect($createdForm->invoice->title)->toBe('Freshers Registration Fee');

    // Verify index table displays the linked invoice
    Volt::test('pages::cms.admissions.form-index')
        ->assertSee('2026/2027 General Admission Form', false)
        ->assertSee('Freshers Registration Fee', false)
        ->assertSee('50,000.00', false);
});

it('prioritizes application form linked invoice when admitting an applicant', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
    ]);
    $session = AcademicSession::factory()->create(['name' => '2026/2027']);

    // Invoice linked to form
    $linkedInvoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'category' => Invoice::CATEGORY_REGISTRATION,
        'title' => 'Linked Registration Fee',
    ]);
    InvoiceItem::create([
        'invoice_id' => $linkedInvoice->id,
        'item_name' => 'Compulsory Fee',
        'amount' => 60000,
    ]);

    // Another generic invoice in same session
    $genericInvoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'category' => Invoice::CATEGORY_ADMISSION,
        'target_type' => 'program',
        'program_id' => $program->id,
        'title' => 'Generic Admission Fee',
    ]);
    InvoiceItem::create([
        'invoice_id' => $genericInvoice->id,
        'item_name' => 'Other Fee',
        'amount' => 30000,
    ]);

    $form = ApplicationForm::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'name' => 'Form with linked invoice',
        'amount' => 5000,
        'academic_session_id' => $session->id,
        'invoice_id' => $linkedInvoice->id,
        'category' => 'fresh',
        'is_active' => true,
    ]);

    $applicant = Applicant::create([
        'application_number' => 'APP-2026-TEST01',
        'full_name' => 'Test Applicant',
        'email' => 'test@example.com',
        'phone' => '08011223344',
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'application_form_id' => $form->id,
        'payment_status' => 'paid',
        'admission_status' => 'pending',
    ]);

    app(AdmissionService::class)->admit($applicant);

    $applicant->refresh();
    expect($applicant->admission_status)->toBe('admitted');

    $studentInvoice = StudentInvoice::where('applicant_id', $applicant->id)->first();
    expect($studentInvoice)->not->toBeNull();
    expect($studentInvoice->invoice_id)->toBe($linkedInvoice->id);
    expect($studentInvoice->total_amount)->toEqual(60000);
});

it('renders application forms on /apply route with linked registration fee', function (): void {
    $institution = Institution::factory()->create([
        'is_admission_open' => true,
        'admission_start_date' => now()->subDay(),
        'admission_end_date' => now()->addMonth(),
    ]);
    $session = AcademicSession::factory()->create(['name' => '2026/2027']);

    $invoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'category' => Invoice::CATEGORY_REGISTRATION,
        'title' => 'Portal Registration Fee',
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Registration Levy',
        'amount' => 40000,
    ]);

    $form = ApplicationForm::create([
        'institution_id' => $institution->id,
        'name' => 'Public Degree Form',
        'amount' => 12000,
        'academic_session_id' => $session->id,
        'invoice_id' => $invoice->id,
        'category' => 'fresh',
        'is_active' => true,
    ]);

    Livewire::test(Apply::class)
        ->assertSee('Public Degree Form', false)
        ->assertSee('12,000.00', false)
        ->assertSee('Reg. Fee:', false)
        ->assertSee('40,000.00', false);
});

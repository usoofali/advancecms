<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('redirects guests to login', function (): void {
    $this->get(route('cms.admissions.issue-notification'))
        ->assertRedirect(route('login'));
});

it('forbids users without view_applications', function (): void {
    $user = User::factory()->withRole('Student')->create();

    $this->actingAs($user)
        ->get(route('cms.admissions.issue-notification'))
        ->assertForbidden();
});

it('allows admission staff to open the issue notification page', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Admission Officer')
        ->create();

    $this->actingAs($user)
        ->get(route('cms.admissions.issue-notification'))
        ->assertSuccessful()
        ->assertSee('Issue admission notification', false);
});

it('generates a printable notification letter and linked invoice from entered details', function (): void {
    $institution = Institution::factory()->create(['name' => 'Test Polytechnic']);
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'name' => 'Computer Science',
        'award_type' => 'diploma',
    ]);
    $session = AcademicSession::factory()->create([
        'name' => '2026/2027',
        'status' => 'active',
    ]);
    $invoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'academic_session_id' => $session->id,
        'category' => Invoice::CATEGORY_REGISTRATION,
        'title' => 'Fresh Student Registration Fee',
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Tuition & Development',
        'amount' => 45000,
    ]);

    $user = User::factory()
        ->for($institution)
        ->withRole('Admission Officer')
        ->create();

    // Verify invoice is required and must match session
    Livewire::actingAs($user)
        ->test('pages::cms.admissions.issue-admission-notification')
        ->set('departmentId', (string) $department->id)
        ->set('programId', (string) $program->id)
        ->set('fullName', 'Jane Candidate')
        ->set('academicSessionId', $session->id)
        ->set('invoiceId', null)
        ->call('generateLetter')
        ->assertHasErrors(['invoiceId']);

    // Generate with valid invoice and session
    Livewire::actingAs($user)
        ->test('pages::cms.admissions.issue-admission-notification')
        ->set('departmentId', (string) $department->id)
        ->set('programId', (string) $program->id)
        ->set('fullName', 'Jane Candidate')
        ->set('academicSessionId', $session->id)
        ->set('invoiceId', $invoice->id)
        ->set('entryLevel', 200)
        ->set('admissionYear', 2026)
        ->set('awardType', 'diploma')
        ->call('generateLetter')
        ->assertHasNoErrors()
        ->tap(function ($component): void {
            expect($component->get('letter')['letter_title'])->toBe('NOTIFICATION OF PROVISIONAL ADMISSION');
            expect($component->get('invoicePayload'))->not->toBeNull();
            expect($component->get('invoicePayload')['title'])->toBe('Fresh Student Registration Fee');
            expect($component->get('invoicePayload')['total_amount'])->toBe(45000.0);
        })
        ->assertSee('JANE CANDIDATE', false)
        ->assertSee('2026/2027', false)
        ->assertSee('Computer Science', false)
        ->assertSee('NOTIFICATION OF PROVISIONAL ADMISSION', false)
        ->assertSee('Registration Invoice', false)
        ->assertDontSee('Invoice Title:', false)
        ->assertDontSee('Walk-In / Provisional', false)
        ->assertDontSee('Invoice Fee Breakdown', false)
        ->assertSee('Tuition &amp; Development', false)
        ->assertSee('45,000.00', false)
        ->assertSee('Print Both (Letter &amp; Invoice)', false);
});

it('rejects invoice when session does not match selected session', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
    ]);
    $sessionA = AcademicSession::factory()->create(['name' => '2025/2026']);
    $sessionB = AcademicSession::factory()->create(['name' => '2026/2027']);

    // Invoice for Session A
    $invoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $sessionA->id,
        'category' => Invoice::CATEGORY_REGISTRATION,
    ]);

    $user = User::factory()
        ->for($institution)
        ->withRole('Admission Officer')
        ->create();

    Livewire::actingAs($user)
        ->test('pages::cms.admissions.issue-admission-notification')
        ->set('departmentId', (string) $department->id)
        ->set('programId', (string) $program->id)
        ->set('fullName', 'John Doe')
        ->set('academicSessionId', $sessionB->id)
        ->set('invoiceId', $invoice->id)
        ->call('generateLetter')
        ->assertHasErrors(['invoiceId']);
});

<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('allows authorized users to create an invoice template with customized percentage thresholds', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $session = AcademicSession::factory()->create();
    $department = Department::factory()->create(['institution_id' => $institution->id]);

    $this->actingAs($admin);

    Livewire::test('pages::cms.invoices.invoice-create')
        ->set('title', 'Tuition & Registration Fee')
        ->set('academic_session_id', $session->id)
        ->set('department_id', $department->id)
        ->set('category', Invoice::CATEGORY_GENERAL)
        ->set('due_date', now()->addDays(30)->format('Y-m-d'))
        ->set('is_required_for_results', true)
        ->set('required_percent_for_results', 60)
        ->set('is_required_for_exams', true)
        ->set('required_percent_for_exams', 50)
        ->set('is_required_for_registration', true)
        ->set('required_percent_for_registration', 70)
        ->set('is_required_for_course_form', true)
        ->set('required_percent_for_course_form', 80)
        ->set('items', [['item_name' => 'Tuition Fee', 'amount' => 50000]])
        ->call('save', 'published')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('invoices', [
        'title' => 'Tuition & Registration Fee',
        'is_required_for_results' => true,
        'required_percent_for_results' => 60,
        'is_required_for_exams' => true,
        'required_percent_for_exams' => 50,
        'is_required_for_registration' => true,
        'required_percent_for_registration' => 70,
        'is_required_for_course_form' => true,
        'required_percent_for_course_form' => 80,
    ]);
});

it('allows authorized users to edit an invoice template and update percentage thresholds', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $session = AcademicSession::factory()->create();
    $department = Department::factory()->create(['institution_id' => $institution->id]);

    $invoice = Invoice::factory()->create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'department_id' => $department->id,
        'is_required_for_exams' => true,
        'required_percent_for_exams' => 100,
        'status' => 'draft',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::cms.invoices.invoice-create', ['invoice' => $invoice])
        ->set('required_percent_for_exams', 40)
        ->call('save', 'published')
        ->assertHasNoErrors();

    $invoice->refresh();
    expect($invoice->required_percent_for_exams)->toBe(40);
});

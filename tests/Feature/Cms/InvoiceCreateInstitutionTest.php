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

it('lists only departments for the signed-in user institution', function (): void {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();

    $deptA = Department::factory()->for($institutionA)->create(['name' => 'ScopedDeptAlphaUnique']);
    Department::factory()->for($institutionB)->create(['name' => 'OtherDeptBetaUnique']);

    $user = User::factory()->withRole('Institutional Admin')->create([
        'institution_id' => $institutionA->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cms.invoices.invoice-create')
        ->assertSee('ScopedDeptAlphaUnique', false)
        ->assertDontSee('OtherDeptBetaUnique', false);
});

it('requires institution for super admin when creating an invoice', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $session = AcademicSession::factory()->create();

    $user = User::factory()->withRole('Super Admin')->create([
        'institution_id' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cms.invoices.invoice-create')
        ->set('title', 'Test Invoice Title')
        ->set('academic_session_id', $session->id)
        ->set('due_date', now()->addMonth()->format('Y-m-d'))
        ->set('department_id', $department->id)
        ->set('items', [['item_name' => 'Fee', 'amount' => '100']])
        ->call('save', 'draft')
        ->assertHasErrors(['institution_id']);
});

it('persists the selected institution when super admin creates an invoice', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $session = AcademicSession::factory()->create();

    $user = User::factory()->withRole('Super Admin')->create([
        'institution_id' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cms.invoices.invoice-create')
        ->set('institution_id', $institution->id)
        ->set('title', 'Super Admin Invoice')
        ->set('academic_session_id', $session->id)
        ->set('due_date', now()->addMonth()->format('Y-m-d'))
        ->set('department_id', $department->id)
        ->set('items', [['item_name' => 'Tuition', 'amount' => '2500']])
        ->call('save', 'draft')
        ->assertHasNoErrors()
        ->assertRedirect(route('cms.invoices.index'));

    expect(Invoice::query()->where('title', 'Super Admin Invoice')->where('institution_id', $institution->id)->exists())->toBeTrue();
});

it('clears department program and semester when super admin changes institution on create', function (): void {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();
    $deptA = Department::factory()->for($institutionA)->create();
    $session = AcademicSession::factory()->create();

    $user = User::factory()->withRole('Super Admin')->create([
        'institution_id' => null,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::cms.invoices.invoice-create')
        ->set('institution_id', $institutionA->id)
        ->set('department_id', $deptA->id)
        ->set('academic_session_id', $session->id)
        ->set('semester_id', '');

    expect($component->get('department_id'))->not->toBeNull();

    $component->set('institution_id', $institutionB->id);

    expect($component->get('department_id'))->toBeNull()
        ->and($component->get('program_id'))->toBeNull()
        ->and($component->get('semester_id'))->toBeNull();
});

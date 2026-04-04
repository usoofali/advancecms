<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('rejects duplicate acronym within the same institution', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $otherDepartment = Department::factory()->for($institution)->create();

    $user = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'acronym' => 'DUPE',
        'name' => 'Existing Program',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cms.programs.create')
        ->set('department_id', (string) $otherDepartment->id)
        ->set('name', 'Another Program')
        ->set('acronym', 'DUPE')
        ->set('duration_years', 4)
        ->set('award_type', 'degree')
        ->set('status', 'active')
        ->call('save')
        ->assertHasErrors(['acronym']);
});

it('allows the same acronym for programs in different institutions', function (): void {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();
    $departmentA = Department::factory()->for($institutionA)->create();
    $departmentB = Department::factory()->for($institutionB)->create();

    Program::factory()->create([
        'institution_id' => $institutionA->id,
        'department_id' => $departmentA->id,
        'acronym' => 'SAME',
        'name' => 'Program A',
    ]);

    $user = User::factory()->withRole('Super Admin')->create();

    $this->actingAs($user);

    Livewire::test('pages::cms.programs.create')
        ->set('institution_id', (string) $institutionB->id)
        ->set('department_id', (string) $departmentB->id)
        ->set('name', 'Program B')
        ->set('acronym', 'SAME')
        ->set('duration_years', 3)
        ->set('award_type', 'diploma')
        ->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('cms.programs.index', absolute: false));

    expect(Program::query()
        ->where('institution_id', $institutionB->id)
        ->where('acronym', 'SAME')
        ->exists())->toBeTrue();
});

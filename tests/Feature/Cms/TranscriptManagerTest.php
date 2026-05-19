<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('authorizes reports.generate permission to access transcript manager', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);

    $this->actingAs($user);

    // Should fail authorization because standard user does not have permission
    Livewire::test('pages::cms.results.transcript-manager')
        ->assertForbidden();
});

it('scopes HOD department ID and filters correctly', function (): void {
    $institution = Institution::factory()->create();
    $department1 = Department::factory()->for($institution)->create();
    $department2 = Department::factory()->for($institution)->create();

    $program1 = Program::factory()->for($department1)->create();
    $program2 = Program::factory()->for($department2)->create();

    $student1 = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program1->id,
    ]);
    $student2 = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program2->id,
    ]);

    $hodRole = Role::where('role_name', 'Head of Department (HOD)')->first();

    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$hodRole->role_id]);

    // Scope HOD user strictly to Department 1
    $user->assignScopedRole('Head of Department (HOD)', $department1);

    $this->actingAs($user);

    Livewire::test('pages::cms.results.transcript-manager')
        ->assertOk()
        ->assertSet('isHod', true)
        ->assertSet('filterDepartment', $department1->id)

        // Assert that the departments available in the query are limited strictly to the scoped department
        ->assertViewHas('departments', function ($depts) use ($department1, $department2) {
            return $depts->contains($department1) && ! $depts->contains($department2);
        })

        // Changing institution should keep HOD's department ID preserved
        ->set('filterInstitution', $institution->id)
        ->assertSet('filterDepartment', $department1->id);
});

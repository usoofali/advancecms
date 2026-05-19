<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('preserves HOD department ID when active filters change', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $session = AcademicSession::factory()->create(['status' => 'active']);

    $hodRole = Role::where('role_name', 'Head of Department (HOD)')->first();

    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$hodRole->role_id]);

    // Scoped role mapping (polymorphic pivot)
    $user->assignScopedRole('Head of Department (HOD)', $department);

    $this->actingAs($user);

    Livewire::test('pages::cms.courses.allocations')
        ->assertOk()
        ->assertSet('isHod', true)
        ->assertSet('department_id', $department->id)
        ->set('session_id', $session->id)
        ->assertSet('department_id', $department->id); // Must still be the HOD's department!
});

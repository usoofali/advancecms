<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('authorizes system.manage permission to access system configuration page', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);

    $this->actingAs($user);

    Volt::test('pages::settings.⚡system')
        ->assertForbidden();
});

it('allows admin users to run regrade results', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();

    $superAdminRole = Role::where('role_name', 'Super Admin')->first();

    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$superAdminRole->role_id]);

    $this->actingAs($user);

    Volt::test('pages::settings.⚡system')
        ->assertOk()
        ->set('regrade_department_id', (string) $department->id)
        ->call('regradeResults', false)
        ->assertHasNoErrors()
        ->assertDispatched('notify', message: __('Student results re-graded successfully.'));
});

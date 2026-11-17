<?php

use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

it('allows authorized users to view roles and plain-language permission descriptions', function () {
    $institution = Institution::factory()->create();
    $superAdmin = User::factory()->create(['institution_id' => $institution->id]);
    $superAdmin->roles()->sync([Role::where('role_name', 'Super Admin')->value('role_id')]);

    $this->actingAs($superAdmin)
        ->get(route('settings.roles'))
        ->assertSuccessful()
        ->assertSee('Roles & Permissions')
        ->assertSee('Approve or reject all pending CBT exam scores at once')
        ->assertSee('View the payroll overview and monthly salary summaries');
});

it('forbids users without roles.view permission from accessing roles settings', function () {
    $institution = Institution::factory()->create();
    $lecturer = User::factory()->create(['institution_id' => $institution->id]);
    $lecturer->roles()->sync([Role::where('role_name', 'Lecturer')->value('role_id')]);

    $this->actingAs($lecturer)
        ->get(route('settings.roles'))
        ->assertForbidden();
});

it('creates and updates a role with selected permissions and protects Super Admin from deletion', function () {
    $institution = Institution::factory()->create();
    $superAdmin = User::factory()->create(['institution_id' => $institution->id]);
    $superAdmin->roles()->sync([Role::where('role_name', 'Super Admin')->value('role_id')]);

    $superAdminRole = Role::where('role_name', 'Super Admin')->firstOrFail();

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.⚡roles')
        ->call('openRoleCreateModal')
        ->set('roleName', 'Bursar Clerk')
        ->set('roleDescription', 'Handles invoice generation and payment verification')
        ->set('selectedPermissions', ['invoices.view', 'payments.verify'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $created = Role::where('role_name', 'Bursar Clerk')->firstOrFail();
    expect($created->permissions->pluck('permission_name')->all())
        ->toEqualCanonicalizing(['invoices.view', 'payments.verify']);

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.⚡roles')
        ->call('openRoleEditModal', $created->role_id)
        ->set('selectedPermissions', ['invoices.view', 'payments.verify', 'invoices.print_report'])
        ->call('saveRole')
        ->assertHasNoErrors();

    expect($created->fresh()->permissions->pluck('permission_name')->all())
        ->toEqualCanonicalizing(['invoices.view', 'payments.verify', 'invoices.print_report']);

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.⚡roles')
        ->set('rolePendingDeleteId', $superAdminRole->role_id)
        ->call('deleteRole');

    expect(Role::where('role_name', 'Super Admin')->exists())->toBeTrue();
});

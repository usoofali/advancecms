<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

test('roles page is displayed to authorized users', function () {
    $viewPermission = Permission::firstOrCreate(['permission_name' => 'roles.view']);
    $role = Role::firstOrCreate(['role_name' => 'Super Admin']);
    $role->permissions()->sync([$viewPermission->permission_id]);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    $this->actingAs($user);

    $response = $this->get('/settings/roles');
    $response->assertSuccessful();
});

test('unauthorized users cannot view roles page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get('/settings/roles');
    $response->assertForbidden();
});

test('roles list and permissions are loaded in component', function () {
    $viewPermission = Permission::firstOrCreate(['permission_name' => 'roles.view']);
    $role = Role::firstOrCreate(['role_name' => 'Super Admin']);
    $role->permissions()->sync([$viewPermission->permission_id]);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    $this->actingAs($user);

    $component = Volt::test('pages::settings.⚡roles')
        ->assertSee('Super Admin');

    expect($component->get('roles'))->not->toBeEmpty();
});

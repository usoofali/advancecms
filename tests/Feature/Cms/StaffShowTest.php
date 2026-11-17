<?php

use App\Models\Institution;
use App\Models\PayrollStaffProfile;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

it('makes staff name and number clickable in staff directory and displays the staff profile page', function () {
    $institution = Institution::factory()->create();
    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $staff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'first_name' => 'Zainab',
        'last_name' => 'Musa',
        'staff_number' => 'STF/2026/ZM001',
        'bank_name' => 'Access Bank',
        'account_number' => '0123456789',
    ]);

    PayrollStaffProfile::factory()->create([
        'institution_id' => $institution->id,
        'staff_id' => $staff->id,
        'pay_type' => 'fixed',
        'base_salary' => 120000,
    ]);

    $this->actingAs($admin);

    $showUrl = route('cms.staff.show', $staff->id);

    $this->get(route('cms.staff.index'))
        ->assertSuccessful()
        ->assertSee($showUrl, false)
        ->assertSee('Zainab Musa')
        ->assertSee('STF/2026/ZM001');

    $this->get($showUrl)
        ->assertSuccessful()
        ->assertSee('Zainab Musa')
        ->assertSee('STF/2026/ZM001')
        ->assertSee('Access Bank')
        ->assertSee('120,000.00');
});

it('blocks viewing staff profile from another institution', function () {
    $instA = Institution::factory()->create();
    $instB = Institution::factory()->create();

    $adminB = User::factory()->create(['institution_id' => $instB->id]);
    $adminB->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $staffA = Staff::factory()->create(['institution_id' => $instA->id]);

    $this->actingAs($adminB);

    $this->get(route('cms.staff.show', $staffA->id))->assertForbidden();
});

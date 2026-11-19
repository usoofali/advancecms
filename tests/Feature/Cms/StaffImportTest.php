<?php

use App\Models\Institution;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('imports valid staff records from a csv file with required columns only', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()->for($institution)->withRole('Institutional Admin')->create();

    $csv = implode("\n", [
        'first_name,last_name,email,designation,role_name',
        'Amina,Bello,amina.bello@example.com,Lecturer,Lecturer',
        'Musa,Garba,musa.garba@example.com,Senior Accountant,Accountant',
    ]);

    $file = UploadedFile::fake()->createWithContent('staff.csv', $csv);

    $this->actingAs($admin);

    Livewire::test('pages::cms.staff.index')
        ->set('importFile', $file)
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('importedCount', 2)
        ->assertSet('importFailures', [])
        ->assertDispatched('notify');

    $amina = Staff::where('email', 'amina.bello@example.com')->first();
    $musa = Staff::where('email', 'musa.garba@example.com')->first();

    expect($amina)->not->toBeNull()
        ->and($amina->institution_id)->toBe($institution->id)
        ->and($amina->designation)->toBe('Lecturer')
        ->and($amina->status)->toBe('active')
        ->and($amina->staff_number)->not->toBeEmpty();

    expect($musa)->not->toBeNull()
        ->and($musa->designation)->toBe('Senior Accountant');
});

it('reports row-level validation failures while importing valid rows', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()->for($institution)->withRole('Institutional Admin')->create();

    Staff::factory()->create([
        'institution_id' => $institution->id,
        'email' => 'existing@example.com',
    ]);

    $csv = implode("\n", [
        'first_name,last_name,email,designation,role_name',
        'Valid,User,valid.user@example.com,Lecturer,Lecturer',
        'Missing,Role,missing.role@example.com,Lecturer,',
        'Bad,Email,not-an-email,Lecturer,Lecturer',
        'Dup,Email,existing@example.com,Lecturer,Lecturer',
        'Admin,Attempt,admin.attempt@example.com,Director,Super Admin',
    ]);

    $file = UploadedFile::fake()->createWithContent('staff.csv', $csv);

    $this->actingAs($admin);

    $component = Livewire::test('pages::cms.staff.index')
        ->set('importFile', $file)
        ->call('import')
        ->assertSet('importedCount', 1);

    expect($component->get('importFailures'))->toHaveCount(4);
    expect(Staff::where('email', 'valid.user@example.com')->exists())->toBeTrue();
});

it('forbids staff import without staff.import permission', function (): void {
    $institution = Institution::factory()->create();

    $viewerRole = Role::create([
        'role_name' => 'Staff Viewer',
        'description' => 'View staff only',
    ]);
    $viewPerm = Permission::where('permission_name', 'staff.view')->firstOrFail();
    $viewerRole->permissions()->attach($viewPerm);

    $viewer = User::factory()->for($institution)->create();
    $viewer->roles()->attach($viewerRole);

    $this->actingAs($viewer);

    Livewire::test('pages::cms.staff.index')
        ->assertOk()
        ->call('import')
        ->assertForbidden();
});

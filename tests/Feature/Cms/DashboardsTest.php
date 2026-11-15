<?php

use App\Models\AcademicSession;
use App\Models\ActivityLog;
use App\Models\Institution;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    AcademicSession::factory()->create(['status' => 'active', 'name' => '2026/2027']);
});

it('renders institutional admin dashboard with payroll, inventory, and activity logs when addons are enabled', function () {
    $institution = Institution::factory()->create([
        'name' => 'Arise College of Health',
        'addons' => ['inventory', 'payroll'],
    ]);

    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    PayrollRun::create([
        'institution_id' => $institution->id,
        'title' => 'October 2026 Payroll',
        'month' => 10,
        'year' => 2026,
        'status' => 'under_review',
        'total_gross' => 500000,
        'total_deductions' => 50000,
        'total_net' => 450000,
        'prepared_by' => $admin->id,
    ]);

    InventoryLocation::create([
        'institution_id' => $institution->id,
        'name' => 'Central Medical Store',
        'location_type' => 'store_warehouse',
        'status' => 'active',
    ]);

    InventoryItem::factory()->create([
        'institution_id' => $institution->id,
        'min_stock_threshold' => 10,
        'status' => 'active',
    ]);

    ActivityLog::create([
        'institution_id' => $institution->id,
        'user_id' => $admin->id,
        'action' => 'created',
        'module' => 'payroll',
        'description' => 'Prepared October 2026 Payroll run',
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Arise College of Health')
        ->assertSee('Financial Health')
        ->assertSee('Payroll Operations')
        ->assertSee('October 2026 Payroll')
        ->assertSee('Campus Inventory')
        ->assertSee('Prepared October 2026 Payroll run');
});

it('hides payroll and inventory cards on institutional admin dashboard when addons are disabled', function () {
    $institution = Institution::factory()->create([
        'name' => 'Plain College',
        'addons' => [],
    ]);

    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Plain College')
        ->assertDontSee('Payroll Operations')
        ->assertDontSee('Campus Inventory');
});

it('renders super admin dashboard with overview', function () {
    $institution = Institution::factory()->create();
    $superAdmin = User::factory()->create(['institution_id' => $institution->id]);
    $superAdmin->roles()->sync([Role::where('role_name', 'Super Admin')->value('role_id')]);

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Overview')
        ->assertSee('Institutions');
});

it('renders lecturer dashboard with teaching metrics and personal timetable', function () {
    $institution = Institution::factory()->create(['addons' => []]);
    Staff::factory()->create([
        'institution_id' => $institution->id,
        'email' => 'ibrahim.sadiq@example.com',
        'first_name' => 'Ibrahim',
        'last_name' => 'Sadiq',
        'staff_number' => 'STF/LEC/001',
    ]);
    $lecturer = User::where('email', 'ibrahim.sadiq@example.com')->firstOrFail();
    $lecturer->roles()->sync([Role::where('role_name', 'Lecturer')->value('role_id')]);

    $this->actingAs($lecturer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Ibrahim Sadiq')
        ->assertSee('Assigned Courses')
        ->assertSee('Classes Today')
        ->assertSee('Personal Timetable')
        ->assertDontSee('Payroll Management');
});

it('renders inventory officer dashboard without teaching clutter', function () {
    $institution = Institution::factory()->create(['addons' => ['inventory']]);
    InventoryItem::factory()->create([
        'institution_id' => $institution->id,
        'min_stock_threshold' => 5,
        'status' => 'active',
    ]);
    Staff::factory()->create([
        'institution_id' => $institution->id,
        'email' => 'amina.bello@example.com',
        'first_name' => 'Amina',
        'last_name' => 'Bello',
    ]);
    $officer = User::where('email', 'amina.bello@example.com')->firstOrFail();
    $officer->roles()->sync([Role::where('role_name', 'Inventory Officer')->value('role_id')]);

    $this->actingAs($officer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Amina Bello')
        ->assertSee('Accessible Rooms')
        ->assertSee('Inventory Hub')
        ->assertDontSee('Assigned Courses');
});

it('renders payroll officer dashboard with payroll management card', function () {
    $institution = Institution::factory()->create(['addons' => ['payroll']]);
    Staff::factory()->create([
        'institution_id' => $institution->id,
        'email' => 'chinedu.okafor@example.com',
        'first_name' => 'Chinedu',
        'last_name' => 'Okafor',
    ]);
    $officer = User::where('email', 'chinedu.okafor@example.com')->firstOrFail();
    $officer->roles()->sync([Role::where('role_name', 'Payroll Officer')->value('role_id')]);

    $this->actingAs($officer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Chinedu Okafor')
        ->assertSee('Payroll Management')
        ->assertDontSee('Assigned Courses');
});

it('renders student dashboard with academic standing, financial pulse, and student services', function () {
    $institution = Institution::factory()->create(['addons' => ['ca_module']]);
    $studentUser = User::factory()->create([
        'institution_id' => $institution->id,
        'email' => 'student.dash@example.com',
    ]);
    $studentUser->roles()->sync([Role::where('role_name', 'Student')->value('role_id')]);

    Student::factory()->create([
        'institution_id' => $institution->id,
        'email' => 'student.dash@example.com',
        'first_name' => 'Fatima',
        'last_name' => 'Lawal',
        'matric_number' => 'MAT/2026/100',
    ]);

    $this->actingAs($studentUser)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Fatima Lawal')
        ->assertSee('MAT/2026/100')
        ->assertSee('Current CGPA')
        ->assertSee('Financial Pulse')
        ->assertSee('Academic Standing')
        ->assertSee('CA Tests & Quizzes');
});

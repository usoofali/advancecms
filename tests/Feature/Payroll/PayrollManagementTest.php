<?php

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\Department;
use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollStaffProfile;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

it('blocks access to payroll routes when user lacks payroll permissions', function () {
    $institution = Institution::factory()->create();
    $lecturer = User::factory()->create(['institution_id' => $institution->id]);
    $lecturer->roles()->sync([Role::where('role_name', 'Lecturer')->value('role_id')]);

    $this->actingAs($lecturer);

    $this->get(route('cms.payroll.index'))->assertForbidden();
    $this->get(route('cms.payroll.settings'))->assertForbidden();
    $this->get(route('cms.payroll.runs'))->assertForbidden();
});

it('computes fixed permanent salaries, contact-credit-unit casual lecturer pay, and polymorphic allowances/deductions', function () {
    $institution = Institution::factory()->create();
    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $dept = Department::factory()->create(['institution_id' => $institution->id, 'name' => 'Nursing Sciences']);

    $permCat = PayrollCategory::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Permanent Staff',
        'pay_type' => 'fixed',
        'is_default' => true,
    ]);

    $casualCat = PayrollCategory::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Casual Lecturer',
        'pay_type' => 'contact_based',
        'contact_formula' => 'contact_x_credit_unit',
        'default_rate' => 2000,
    ]);

    // Staff 1: Permanent Staff (Fixed ₦100,000)
    $permStaff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'first_name' => 'Amina',
        'last_name' => 'Bello',
        'email' => 'amina@college.test',
        'status' => 'active',
        'bank_name' => 'First Bank',
        'account_number' => '1234567890',
    ]);
    PayrollStaffProfile::factory()->create([
        'institution_id' => $institution->id,
        'staff_id' => $permStaff->id,
        'payroll_category_id' => $permCat->id,
        'department_id' => $dept->id,
        'base_salary' => 100000,
    ]);

    // Staff 2: Casual Lecturer (paid per contact x credit_unit @ custom rate ₦2,500)
    $lecturerRoleId = Role::where('role_name', 'Lecturer')->value('role_id');
    $casualStaff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'role_id' => $lecturerRoleId,
        'first_name' => 'Chidi',
        'last_name' => 'Okafor',
        'email' => 'chidi@college.test',
        'status' => 'active',
        'bank_name' => 'GTBank',
        'account_number' => '0987654321',
    ]);
    PayrollStaffProfile::factory()->create([
        'institution_id' => $institution->id,
        'staff_id' => $casualStaff->id,
        'payroll_category_id' => $casualCat->id,
        'department_id' => $dept->id,
        'custom_rate' => 2500,
    ]);

    // Record 2 submitted attendances for a 3-credit-unit course (2 * 3 * 2500 = 15,000)
    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $dept->id,
        'credit_unit' => 3,
    ]);
    $allocation = CourseAllocation::factory()->create([
        'institution_id' => $institution->id,
        'user_id' => $casualStaff->user->id,
        'course_id' => $course->id,
    ]);
    Attendance::factory()->count(2)->create([
        'institution_id' => $institution->id,
        'course_allocation_id' => $allocation->id,
        'date' => '2026-11-10',
        'status' => 'submitted',
        'is_combined_child' => false,
    ]);

    // Polymorphic components:
    // 1. Institution-wide deduction: Staff Welfare ₦1,000
    PayrollComponent::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Staff Welfare',
        'type' => 'deduction',
        'calculation_type' => 'fixed',
        'amount_or_rate' => 1000,
        'applicable_type' => null,
        'applicable_id' => null,
    ]);
    // 2. Category-scoped allowance: Housing 10% for Permanent Staff only (₦10,000)
    PayrollComponent::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Housing Allowance',
        'type' => 'allowance',
        'calculation_type' => 'percentage',
        'amount_or_rate' => 10,
        'applicable_type' => PayrollCategory::class,
        'applicable_id' => $permCat->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::cms.payroll.runs')
        ->set('gen_month', 11)
        ->set('gen_year', 2026)
        ->set('gen_title', 'November 2026 Payroll')
        ->call('generateRun')
        ->assertHasNoErrors();

    $run = PayrollRun::where('institution_id', $institution->id)->firstOrFail();

    $permItem = PayrollItem::where('payroll_run_id', $run->id)->where('staff_id', $permStaff->id)->firstOrFail();
    expect((float) $permItem->base_pay)->toBe(100000.00);
    expect((float) $permItem->total_allowances)->toBe(10000.00);
    expect((float) $permItem->total_deductions)->toBe(1000.00);
    expect((float) $permItem->net_pay)->toBe(109000.00);

    $casualItem = PayrollItem::where('payroll_run_id', $run->id)->where('staff_id', $casualStaff->id)->firstOrFail();
    expect($casualItem->contacts_count)->toBe(2);
    expect((float) $casualItem->credit_units_total)->toBe(6.00);
    expect((float) $casualItem->base_pay)->toBe(15000.00);
    expect((float) $casualItem->total_allowances)->toBe(0.00);
    expect((float) $casualItem->total_deductions)->toBe(1000.00);
    expect((float) $casualItem->net_pay)->toBe(14000.00);
});

it('enforces segregated approval so payroll officer cannot approve final run, and logs post-approval edits', function () {
    $institution = Institution::factory()->create();
    $officer = User::factory()->create(['institution_id' => $institution->id]);
    $officer->roles()->sync([Role::where('role_name', 'Payroll Officer')->value('role_id')]);

    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $staff = Staff::factory()->create(['institution_id' => $institution->id, 'status' => 'active']);
    PayrollStaffProfile::factory()->create([
        'institution_id' => $institution->id,
        'staff_id' => $staff->id,
        'pay_type' => 'fixed',
        'base_salary' => 80000,
    ]);

    // Officer generates and submits run
    $this->actingAs($officer);
    Livewire::test('pages::cms.payroll.runs')
        ->set('gen_month', 11)
        ->set('gen_year', 2026)
        ->call('generateRun');

    $run = PayrollRun::firstOrFail();

    Livewire::test('pages::cms.payroll.runs', ['run_id' => $run->id])
        ->call('submitForReview');

    expect($run->fresh()->status)->toBe('under_review');

    // Officer is forbidden from approving
    Livewire::test('pages::cms.payroll.runs', ['run_id' => $run->id])
        ->call('approveRun')
        ->assertForbidden();

    // Admin approves run
    $this->actingAs($admin);
    Livewire::test('pages::cms.payroll.runs', ['run_id' => $run->id])
        ->call('approveRun')
        ->assertHasNoErrors();

    expect($run->fresh()->status)->toBe('approved');

    // Post-approval adjustment by Admin creates audit log
    $item = $run->items()->firstOrFail();
    Livewire::test('pages::cms.payroll.runs', ['run_id' => $run->id])
        ->call('openAdjustModal', $item->id)
        ->set('adjust_amount', '5000')
        ->set('adjust_note', 'Approved weekend clinical bonus')
        ->call('saveAdjustment')
        ->assertHasNoErrors();

    expect((float) $item->fresh()->net_pay)->toBe(85000.00);
    expect(ActivityLog::where('module', 'Payroll')->where('action', 'post_approval_edit')->exists())->toBeTrue();
});

it('isolates payroll data between tenants and supports bank schedule export, department sheet, and staff self-service payslips', function () {
    $instA = Institution::factory()->create(['name' => 'College A']);
    $instB = Institution::factory()->create(['name' => 'College B']);

    $adminA = User::factory()->create(['institution_id' => $instA->id]);
    $adminA->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $adminB = User::factory()->create(['institution_id' => $instB->id]);
    $adminB->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $lecturerRoleId = Role::where('role_name', 'Lecturer')->value('role_id');
    $staffA = Staff::factory()->create([
        'institution_id' => $instA->id,
        'role_id' => $lecturerRoleId,
        'email' => 'lecturer.a@college.test',
        'status' => 'active',
    ]);
    PayrollStaffProfile::factory()->create([
        'institution_id' => $instA->id,
        'staff_id' => $staffA->id,
        'pay_type' => 'fixed',
        'base_salary' => 95000,
    ]);

    $this->actingAs($adminA);
    Livewire::test('pages::cms.payroll.runs')
        ->set('gen_month', 11)
        ->set('gen_year', 2026)
        ->call('generateRun');

    $runA = PayrollRun::where('institution_id', $instA->id)->firstOrFail();
    $runA->update(['status' => 'approved', 'approved_by' => $adminA->id, 'approved_at' => now()]);
    $itemA = $runA->items()->firstOrFail();

    // Bank schedule & Department sheet accessible to Admin A
    $this->get(route('cms.payroll.bank-schedule', ['run' => $runA->id]))->assertSuccessful();
    $this->get(route('cms.payroll.department-sheet', ['run' => $runA->id]))->assertSuccessful();

    Livewire::test('pages::cms.payroll.bank-schedule', ['run' => $runA])
        ->call('exportCsv')
        ->assertFileDownloaded();

    // Admin B is blocked from College A's bank schedule & payslip
    $this->actingAs($adminB);
    $this->get(route('cms.payroll.bank-schedule', ['run' => $runA->id]))->assertForbidden();
    $this->get(route('cms.payroll.payslip', ['item' => $itemA->id]))->assertForbidden();

    // Staff A can view their own approved payslip
    $this->actingAs($staffA->user);
    $this->get(route('cms.payroll.my-payslips'))->assertSuccessful()->assertSee('95,000.00');
    $this->get(route('cms.payroll.payslip', ['item' => $itemA->id]))->assertSuccessful()->assertSee('Employee Payslip');
});

it('uses staff record as single source of truth for bank account details in payroll', function () {
    $institution = Institution::factory()->create();
    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $staff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'bank_name' => 'Zenith Bank',
        'account_number' => '1122334455',
        'account_name' => 'Old Account Name',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::cms.payroll.staff')
        ->call('openEditProfileModal', $staff->id)
        ->assertSet('prof_bank_name', 'Zenith Bank')
        ->assertSet('prof_account_number', '1122334455')
        ->set('prof_bank_name', 'UBA')
        ->set('prof_account_number', '2233445566')
        ->set('prof_account_name', 'Updated Staff Name')
        ->set('prof_base_salary', '150000')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $staff->refresh();
    expect($staff->bank_name)->toBe('UBA');
    expect($staff->account_number)->toBe('2233445566');
    expect($staff->account_name)->toBe('Updated Staff Name');
});

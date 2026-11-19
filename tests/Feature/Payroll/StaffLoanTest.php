<?php

use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollStaffProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffLoan;
use App\Models\StaffLoanRepayment;
use App\Models\User;
use App\Services\PayrollCalculator;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('enforces configurable concurrent open loan limits including paused loans', function (): void {
    $institution = Institution::factory()->create(['max_concurrent_staff_loans' => 1]);
    $staff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'status' => 'active',
    ]);
    $admin = User::factory()->for($institution)->withRole('Institutional Admin')->create();

    $this->actingAs($admin);

    // 1st loan succeeds
    Livewire::test('pages::cms.payroll.loans')
        ->call('openCreateModal')
        ->set('staff_id', $staff->id)
        ->set('title', 'Salary Advance')
        ->set('principal_amount', '50000')
        ->set('monthly_deduction', '10000')
        ->set('start_month', 1)
        ->set('start_year', 2026)
        ->call('saveLoan')
        ->assertHasNoErrors();

    $loan = StaffLoan::firstOrFail();

    // Pause the 1st loan — paused loans still count toward the concurrent limit
    Livewire::test('pages::cms.payroll.loans')
        ->call('togglePause', $loan->id);

    expect($loan->fresh()->status)->toBe('paused');

    // 2nd loan blocked while limit is 1
    Livewire::test('pages::cms.payroll.loans')
        ->call('openCreateModal')
        ->set('staff_id', $staff->id)
        ->set('title', 'Equipment Loan')
        ->set('principal_amount', '30000')
        ->set('monthly_deduction', '5000')
        ->set('start_month', 1)
        ->set('start_year', 2026)
        ->call('saveLoan')
        ->assertHasErrors(['staff_id']);

    // Raise institution limit to 2 via Payroll Settings
    Livewire::test('pages::cms.payroll.settings')
        ->set('max_concurrent_staff_loans', 2)
        ->call('saveLoanPolicy')
        ->assertHasNoErrors();

    expect($institution->fresh()->max_concurrent_staff_loans)->toBe(2);

    // Now 2nd loan succeeds
    Livewire::test('pages::cms.payroll.loans')
        ->call('openCreateModal')
        ->set('staff_id', $staff->id)
        ->set('title', 'Equipment Loan')
        ->set('principal_amount', '30000')
        ->set('monthly_deduction', '5000')
        ->set('start_month', 1)
        ->set('start_year', 2026)
        ->call('saveLoan')
        ->assertHasNoErrors();

    expect(StaffLoan::openForStaff($staff->id)->count())->toBe(2);
});

it('forbids loan mutations without payroll.manage_loans permission', function (): void {
    $institution = Institution::factory()->create();
    $staff = Staff::factory()->create(['institution_id' => $institution->id, 'status' => 'active']);

    $viewerRole = Role::create([
        'role_name' => 'Payroll Viewer',
        'description' => 'View payroll only',
    ]);
    $viewPerm = Permission::where('permission_name', 'payroll.view')->firstOrFail();
    $viewerRole->permissions()->attach($viewPerm);

    $viewer = User::factory()->for($institution)->create();
    $viewer->roles()->attach($viewerRole);

    $this->actingAs($viewer);

    Livewire::test('pages::cms.payroll.loans')
        ->assertOk()
        ->call('openCreateModal')
        ->assertForbidden();
});

it('respects start period, caps loan deduction at available net pay, and settles on markPaid', function (): void {
    $institution = Institution::factory()->create(['max_concurrent_staff_loans' => 2]);
    $admin = User::factory()->for($institution)->withRole('Institutional Admin')->create();

    $category = PayrollCategory::create([
        'institution_id' => $institution->id,
        'name' => 'Permanent Staff',
        'pay_type' => 'fixed',
        'is_default' => true,
        'status' => 'active',
    ]);

    $staff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'status' => 'active',
    ]);

    PayrollStaffProfile::create([
        'institution_id' => $institution->id,
        'staff_id' => $staff->id,
        'payroll_category_id' => $category->id,
        'base_salary' => 15000,
        'status' => 'active',
    ]);

    // Loan starting in March 2026 (month 3): 20,000/mo on 15,000 salary -> capped at 15,000
    $loan = StaffLoan::create([
        'institution_id' => $institution->id,
        'staff_id' => $staff->id,
        'title' => 'Emergency Advance',
        'principal_amount' => 25000,
        'monthly_deduction' => 20000,
        'total_repaid' => 0,
        'remaining_balance' => 25000,
        'start_month' => 3,
        'start_year' => 2026,
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    $calculator = app(PayrollCalculator::class);

    // February 2026: before start_month -> 0 loan deduction
    $febRun = $calculator->generateOrRecalculateRun($institution, 2, 2026, $admin);
    $febItem = $febRun->items()->where('staff_id', $staff->id)->firstOrFail();
    expect((float) $febItem->total_deductions)->toBe(0.0)
        ->and((float) $febItem->net_pay)->toBe(15000.0);

    // March 2026: capped at available pay (15,000)
    $marRun = $calculator->generateOrRecalculateRun($institution, 3, 2026, $admin);
    $marItem = $marRun->items()->where('staff_id', $staff->id)->firstOrFail();

    expect((float) $marItem->total_deductions)->toBe(15000.0)
        ->and((float) $marItem->net_pay)->toBe(0.0)
        ->and($marItem->deductions_breakdown)->toHaveCount(1)
        ->and($marItem->deductions_breakdown[0]['loan_id'])->toBe($loan->id)
        ->and((float) $marItem->deductions_breakdown[0]['amount'])->toBe(15000.0)
        ->and((float) $marItem->deductions_breakdown[0]['remaining_after'])->toBe(10000.0);

    // Draft run must NOT mutate loan balance yet
    expect((float) $loan->fresh()->remaining_balance)->toBe(25000.0);

    // Approve and mark Paid via Livewire
    $marRun->update(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);

    $this->actingAs($admin);

    Livewire::test('pages::cms.payroll.runs', ['run_id' => $marRun->id])
        ->call('markPaid')
        ->assertDispatched('notify');

    // Idempotent: calling settleRunLoans again does not double-post
    $calculator->settleRunLoans($marRun->fresh(), $admin);

    $loan->refresh();
    expect((float) $loan->total_repaid)->toBe(15000.0)
        ->and((float) $loan->remaining_balance)->toBe(10000.0)
        ->and($loan->status)->toBe('active')
        ->and(StaffLoanRepayment::where('staff_loan_id', $loan->id)->count())->toBe(1);

    // April 2026: remaining_balance is 10,000 (< 20,000 monthly_deduction), so final installment is 10,000
    $aprRun = $calculator->generateOrRecalculateRun($institution, 4, 2026, $admin);
    $aprItem = $aprRun->items()->where('staff_id', $staff->id)->firstOrFail();

    expect((float) $aprItem->total_deductions)->toBe(10000.0)
        ->and((float) $aprItem->net_pay)->toBe(5000.0);

    $aprRun->update(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);
    $calculator->settleRunLoans($aprRun, $admin);

    $loan->refresh();
    expect((float) $loan->remaining_balance)->toBe(0.0)
        ->and($loan->status)->toBe('completed');
});

it('records manual repayments and displays remaining loan balance on payslip', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()->for($institution)->withRole('Institutional Admin')->create();
    $staff = Staff::factory()->create(['institution_id' => $institution->id, 'status' => 'active']);

    $loan = StaffLoan::create([
        'institution_id' => $institution->id,
        'staff_id' => $staff->id,
        'title' => 'Co-op Loan',
        'principal_amount' => 20000,
        'monthly_deduction' => 5000,
        'total_repaid' => 0,
        'remaining_balance' => 20000,
        'start_month' => 1,
        'start_year' => 2026,
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::cms.payroll.loans')
        ->call('openRepaymentModal', $loan->id)
        ->set('repayment_amount', '20000')
        ->set('repayment_notes', 'Full early settlement')
        ->call('recordManualRepayment')
        ->assertHasNoErrors();

    $loan->refresh();
    expect((float) $loan->remaining_balance)->toBe(0.0)
        ->and($loan->status)->toBe('completed');

    // Payslip renders remaining_after when present on deduction breakdown
    $run = PayrollRun::create([
        'institution_id' => $institution->id,
        'title' => 'May 2026 Payroll',
        'month' => 5,
        'year' => 2026,
        'status' => 'approved',
        'prepared_by' => $admin->id,
        'prepared_at' => now(),
    ]);

    $item = PayrollItem::create([
        'payroll_run_id' => $run->id,
        'institution_id' => $institution->id,
        'staff_id' => $staff->id,
        'pay_type' => 'fixed',
        'base_pay' => 50000,
        'deductions_breakdown' => [
            ['name' => 'Loan: Co-op Loan', 'amount' => 5000, 'loan_id' => $loan->id, 'remaining_after' => 15000],
        ],
        'total_deductions' => 5000,
        'gross_pay' => 50000,
        'net_pay' => 45000,
    ]);

    Livewire::test('pages::cms.payroll.payslip', ['item' => $item])
        ->assertSee('Loan: Co-op Loan')
        ->assertSee('Remaining Balance:');
});

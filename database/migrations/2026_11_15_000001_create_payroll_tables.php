<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('name');
            $table->enum('pay_type', ['fixed', 'contact_based'])->default('fixed');
            $table->enum('contact_formula', [
                'contact_x_credit_unit',
                'hour_x_credit_unit',
                'per_contact',
                'per_hour',
            ])->nullable();
            $table->decimal('default_rate', 12, 2)->default(0.00);
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'status'], 'pay_cat_inst_status_idx');
        });

        Schema::create('payroll_staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('payroll_category_id')->nullable()->constrained('payroll_categories')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('pay_type', ['fixed', 'contact_based'])->nullable();
            $table->enum('contact_formula', [
                'contact_x_credit_unit',
                'hour_x_credit_unit',
                'per_contact',
                'per_hour',
            ])->nullable();
            $table->decimal('base_salary', 12, 2)->default(0.00);
            $table->decimal('custom_rate', 12, 2)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'staff_id'], 'pay_prof_inst_staff_uq');
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['allowance', 'deduction']);
            $table->enum('calculation_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('amount_or_rate', 12, 2)->default(0.00);
            $table->string('applicable_type')->nullable();
            $table->unsignedBigInteger('applicable_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['institution_id', 'is_active'], 'pay_comp_inst_active_idx');
            $table->index(['applicable_type', 'applicable_id'], 'pay_comp_applicable_idx');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->enum('status', ['draft', 'under_review', 'approved', 'paid'])->default('draft');
            $table->decimal('total_gross', 15, 2)->default(0.00);
            $table->decimal('total_deductions', 15, 2)->default(0.00);
            $table->decimal('total_net', 15, 2)->default(0.00);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'month', 'year'], 'pay_run_inst_period_uq');
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('payroll_category_id')->nullable()->constrained('payroll_categories')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('pay_type')->default('fixed');
            $table->decimal('base_pay', 12, 2)->default(0.00);
            $table->unsignedInteger('contacts_count')->default(0);
            $table->decimal('credit_units_total', 8, 2)->default(0.00);
            $table->decimal('hours_total', 8, 2)->default(0.00);
            $table->decimal('rate_used', 12, 2)->default(0.00);
            $table->json('allowances_breakdown')->nullable();
            $table->decimal('total_allowances', 12, 2)->default(0.00);
            $table->json('deductions_breakdown')->nullable();
            $table->decimal('total_deductions', 12, 2)->default(0.00);
            $table->decimal('manual_adjustment', 12, 2)->default(0.00);
            $table->string('adjustment_note')->nullable();
            $table->decimal('gross_pay', 12, 2)->default(0.00);
            $table->decimal('net_pay', 12, 2)->default(0.00);
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'staff_id'], 'pay_item_run_staff_uq');
            $table->index(['institution_id', 'department_id'], 'pay_item_inst_dept_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_components');
        Schema::dropIfExists('payroll_staff_profiles');
        Schema::dropIfExists('payroll_categories');
    }
};

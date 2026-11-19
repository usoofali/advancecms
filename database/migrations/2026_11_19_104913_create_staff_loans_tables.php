<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('staff_loan_repayments');
        Schema::dropIfExists('staff_loans');

        Schema::create('staff_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('title');
            $table->string('reference_no')->nullable();
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('monthly_deduction', 12, 2);
            $table->decimal('total_repaid', 12, 2)->default(0.00);
            $table->decimal('remaining_balance', 12, 2);
            $table->unsignedTinyInteger('start_month');
            $table->unsignedSmallInteger('start_year');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'staff_id', 'status'], 'staff_loans_inst_staff_status_idx');
        });

        Schema::create('staff_loan_repayments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_loan_id')->constrained('staff_loans')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained('payroll_items')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('payment_source')->default('payroll');
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['staff_loan_id', 'payroll_run_id'], 'staff_loan_repay_loan_run_uq');
            $table->index(['institution_id', 'staff_loan_id'], 'staff_loan_repay_inst_loan_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_loan_repayments');
        Schema::dropIfExists('staff_loans');
    }
};

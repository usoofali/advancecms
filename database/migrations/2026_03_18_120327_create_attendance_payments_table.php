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
        Schema::create('attendance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->integer('contacts_count')->default(0);
            $table->decimal('allowance_rate', 10, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('pending'); // pending, approved, paid, cancelled
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'month', 'year'], 'unique_staff_payment_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_payments');
    }
};

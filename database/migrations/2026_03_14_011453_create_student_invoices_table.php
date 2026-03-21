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
        Schema::create('student_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2);
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_invoices');
    }
};

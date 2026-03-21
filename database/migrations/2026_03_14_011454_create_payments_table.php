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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('student_invoice_id')->constrained('student_invoices')->cascadeOnDelete();
            $table->decimal('amount_paid', 15, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'pos', 'opay'])->default('cash');
            $table->enum('payment_type', ['manual', 'automated'])->default('manual');
            $table->string('reference')->unique();
            $table->string('gateway_order_no')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

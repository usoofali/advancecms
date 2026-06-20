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
        Schema::create('ca_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ca_test_id')->constrained('ca_tests')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('total_score', 8, 2)->default(0.00);
            $table->decimal('normalized_score', 8, 2)->default(0.00);
            $table->integer('attempt_count')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ca_results');
    }
};

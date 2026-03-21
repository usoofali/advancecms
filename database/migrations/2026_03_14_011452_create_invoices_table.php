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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->date('due_date')->nullable();
            $table->enum('target_type', ['dept', 'program', 'level', 'student'])->default('student');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->string('level')->nullable();
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

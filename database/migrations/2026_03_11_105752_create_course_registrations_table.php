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
        Schema::create('course_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->enum('status', ['registered', 'dropped'])->default('registered');
            $table->timestamps();

            $table->unique(['student_id', 'course_id', 'semester_id'], 'unique_registration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_registrations');
    }
};

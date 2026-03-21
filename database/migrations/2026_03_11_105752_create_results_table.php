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
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->decimal('ca_score', 5, 2)->default(0);
            $table->decimal('exam_score', 5, 2)->default(0);
            $table->decimal('total_score', 5, 2)->default(0);
            $table->char('grade', 1)->nullable();
            $table->decimal('grade_point', 3, 1)->nullable();
            $table->enum('remark', ['pass', 'fail'])->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_id', 'semester_id'], 'unique_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};

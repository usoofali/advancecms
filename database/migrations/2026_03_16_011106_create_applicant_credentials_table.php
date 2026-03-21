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
        Schema::create('applicant_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');

            // Exam Sittings
            $table->string('sitting_1_exam_type')->nullable();
            $table->string('sitting_1_exam_number')->nullable();
            $table->string('sitting_1_exam_year')->nullable();

            $table->string('sitting_2_exam_type')->nullable();
            $table->string('sitting_2_exam_number')->nullable();
            $table->string('sitting_2_exam_year')->nullable();

            // Core Subject Grades
            $table->string('subject_english')->nullable();
            $table->string('subject_mathematics')->nullable();
            $table->string('subject_biology')->nullable();
            $table->string('subject_chemistry')->nullable();
            $table->string('subject_physics')->nullable();

            $table->string('document_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicant_credentials');
    }
};

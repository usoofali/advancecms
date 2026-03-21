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
        Schema::table('students', function (Blueprint $table) {
            // Personal Information
            $table->string('blood_group')->nullable()->after('phone');
            $table->string('state')->nullable()->after('blood_group');
            $table->string('lga')->nullable()->after('state');

            // Credentials - Sitting 1
            $table->string('sitting_1_exam_type')->nullable()->after('lga');
            $table->string('sitting_1_exam_number')->nullable()->after('sitting_1_exam_type');
            $table->string('sitting_1_exam_year')->nullable()->after('sitting_1_exam_number');

            // Credentials - Sitting 2
            $table->string('sitting_2_exam_type')->nullable()->after('sitting_1_exam_year');
            $table->string('sitting_2_exam_number')->nullable()->after('sitting_2_exam_type');
            $table->string('sitting_2_exam_year')->nullable()->after('sitting_2_exam_number');

            // Core Subjects
            $table->string('subject_english')->nullable()->after('sitting_2_exam_year');
            $table->string('subject_mathematics')->nullable()->after('subject_english');
            $table->string('subject_biology')->nullable()->after('subject_mathematics');
            $table->string('subject_chemistry')->nullable()->after('subject_biology');
            $table->string('subject_physics')->nullable()->after('subject_chemistry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'blood_group', 'state', 'lga',
                'sitting_1_exam_type', 'sitting_1_exam_number', 'sitting_1_exam_year',
                'sitting_2_exam_type', 'sitting_2_exam_number', 'sitting_2_exam_year',
                'subject_english', 'subject_mathematics', 'subject_biology', 'subject_chemistry', 'subject_physics',
            ]);
        });
    }
};

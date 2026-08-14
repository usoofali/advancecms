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
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('100');

            // Polymorphic allocation link (CourseAllocation or Course)
            $table->nullableMorphs('allocatable');

            // Cached relationships for fast direct queries
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Lecturer

            $table->string('day_of_week'); // Monday, Tuesday, Wednesday, Thursday, Friday, Saturday
            $table->unsignedTinyInteger('period_number')->default(1); // 1, 2, 3, 4, 5, 6
            $table->string('start_time')->default('08:00'); // e.g. 08:00
            $table->string('end_time')->default('10:00');   // e.g. 10:00

            $table->timestamps();

            // Indexes for fast lookup
            $table->index(['institution_id', 'academic_session_id', 'semester_id', 'program_id', 'level'], 'timetable_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};

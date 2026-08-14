<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('student_placements')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();

            // Rating Metrics (1-5 scale)
            $table->unsignedTinyInteger('punctuality_rating')->default(5);
            $table->unsignedTinyInteger('attendance_rating')->default(5);
            $table->unsignedTinyInteger('conduct_discipline_rating')->default(5);
            $table->unsignedTinyInteger('technical_skills_rating')->default(5);
            $table->unsignedTinyInteger('logbook_maintenance_rating')->default(5);

            $table->decimal('total_score', 5, 2)->unsigned()->default(100.00);
            $table->string('performance_grade', 5)->default('A');
            $table->text('supervisor_remarks')->nullable();
            $table->timestamp('evaluated_at')->useCurrent();
            $table->timestamps();

            $table->unique('placement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_evaluations');
    }
};

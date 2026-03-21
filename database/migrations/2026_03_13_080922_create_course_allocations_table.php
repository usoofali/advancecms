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
        Schema::create('course_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // The lecturer
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent assigning the exact same course to the same lecturer in the same session/semester
            $table->unique(['user_id', 'course_id', 'academic_session_id', 'semester_id'], 'course_alloc_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_allocations');
    }
};

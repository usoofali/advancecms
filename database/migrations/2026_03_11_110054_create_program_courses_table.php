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
        Schema::create('program_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(100);
            $table->unsignedTinyInteger('semester')->default(1);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['program_id', 'course_id', 'level', 'semester'], 'unique_program_course');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_courses');
    }
};

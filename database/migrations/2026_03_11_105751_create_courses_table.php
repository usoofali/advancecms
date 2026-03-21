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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('course_code', 20)->unique();
            $table->string('title');
            $table->unsignedTinyInteger('credit_unit')->default(2);
            $table->enum('course_type', ['core', 'elective'])->default('core');
            $table->unsignedSmallInteger('level')->default(100);
            $table->unsignedTinyInteger('semester')->default(1)->comment('1 = First, 2 = Second');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

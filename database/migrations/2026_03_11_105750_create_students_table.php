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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->string('matric_number', 30)->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->year('admission_year');
            $table->unsignedTinyInteger('entry_level')->default(100);
            $table->enum('status', ['active', 'suspended', 'withdrawn', 'graduated', 'deceased'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

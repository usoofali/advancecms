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
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->enum('name', ['first', 'second']);
            $table->timestamps();

            $table->unique(['academic_session_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};

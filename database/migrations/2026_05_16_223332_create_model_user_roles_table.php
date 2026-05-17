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
        Schema::create('model_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();

            // Polymorphic relation to link to Department, Course, Faculty, etc.
            $table->morphs('model');

            $table->timestamps();

            // Ensure a user cannot be assigned the same role on the same model multiple times
            $table->unique(['user_id', 'role_id', 'model_type', 'model_id'], 'model_user_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_user_roles');
    }
};

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
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_relationship')->nullable();
            $table->string('next_of_kin_phone')->nullable();
            $table->string('next_of_kin_address')->nullable();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_relationship')->nullable();
            $table->string('next_of_kin_phone')->nullable();
            $table->string('next_of_kin_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['next_of_kin_name', 'next_of_kin_relationship', 'next_of_kin_phone', 'next_of_kin_address']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['next_of_kin_name', 'next_of_kin_relationship', 'next_of_kin_phone', 'next_of_kin_address']);
        });
    }
};

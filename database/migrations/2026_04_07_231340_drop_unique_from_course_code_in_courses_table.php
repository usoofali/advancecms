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
        try {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropUnique(['course_code']);
            });
        } catch (\Exception $e) {
            // Index might not exist in some environments (e.g. fresh test DB)
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unique('course_code');
        });
    }
};

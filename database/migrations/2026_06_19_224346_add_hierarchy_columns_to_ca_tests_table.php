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
        Schema::table('ca_tests', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->nullable()->after('course_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->after('academic_session_id')->constrained('semesters')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ca_tests', function (Blueprint $table) {
            $table->dropForeign(['institution_id']);
            $table->dropForeign(['academic_session_id']);
            $table->dropForeign(['semester_id']);
            $table->dropColumn(['institution_id', 'academic_session_id', 'semester_id']);
        });
    }
};

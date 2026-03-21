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
        // 1. Update users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
        });

        // 2. Update programs table
        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->nullable()->constrained('institutions')->cascadeOnDelete();
        });

        // 3. Update courses table (re-parenting to Program)
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->nullable()->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('program_id')->after('institution_id')->nullable()->constrained('programs')->cascadeOnDelete();
            // Optional: Remove department_id if we want strict Program inheritance
            // $table->dropConstrainedForeignId('department_id');
        });

        // 4. Update students table
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->nullable()->constrained('institutions')->cascadeOnDelete();
        });

        // 5. Update course_registrations table
        Schema::table('course_registrations', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->nullable()->constrained('institutions')->cascadeOnDelete();
        });

        // 6. Update results table
        Schema::table('results', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->nullable()->constrained('institutions')->cascadeOnDelete();
        });

        // 7. Create staff table
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('lecturer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');

        Schema::table('results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });

        Schema::table('course_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropConstrainedForeignId('program_id');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });
    }
};

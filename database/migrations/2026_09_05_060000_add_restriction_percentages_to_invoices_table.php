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
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('required_percent_for_results')->default(100)->after('is_required_for_results');
            $table->unsignedInteger('required_percent_for_exams')->default(100)->after('is_required_for_exams');
            $table->unsignedInteger('required_percent_for_registration')->default(100)->after('is_required_for_registration');
            $table->unsignedInteger('required_percent_for_course_form')->default(100)->after('is_required_for_course_form');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'required_percent_for_results',
                'required_percent_for_exams',
                'required_percent_for_registration',
                'required_percent_for_course_form',
            ]);
        });
    }
};

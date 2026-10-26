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
            $table->boolean('is_required_for_placements')->default(false)->after('required_percent_for_course_form');
            $table->unsignedInteger('required_percent_for_placements')->default(100)->after('is_required_for_placements');
            $table->boolean('is_required_for_projects')->default(false)->after('required_percent_for_placements');
            $table->unsignedInteger('required_percent_for_projects')->default(100)->after('is_required_for_projects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'is_required_for_placements',
                'required_percent_for_placements',
                'is_required_for_projects',
                'required_percent_for_projects',
            ]);
        });
    }
};

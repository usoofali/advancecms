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
        Schema::table('staff', function (Blueprint $table) {
            $table->decimal('attendance_allowance', 10, 2)->default(0)->after('designation');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('attendance_allowance');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('attendance_allowance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->decimal('attendance_allowance', 10, 2)->nullable()->after('name');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->decimal('attendance_allowance', 10, 2)->default(0)->after('name');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('attendance_allowance');
        });
    }
};

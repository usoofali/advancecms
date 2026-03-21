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
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('hod_id')->nullable()->after('institution_id')->constrained('staff')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hod_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
        });
    }
};

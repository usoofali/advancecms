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
            if (!Schema::hasColumn('students', 'signature_path')) {
                $table->string('signature_path')->nullable();
            }
        });

        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'signature_path')) {
                $table->string('signature_path')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'signature_path')) {
                $table->dropColumn('signature_path');
            }
        });

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'signature_path')) {
                $table->dropColumn('signature_path');
            }
        });
    }
};

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
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->boolean('has_batches')->default(false)->after('status');
            $table->json('batches')->nullable()->after('has_batches');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('batch', 10)->nullable()->after('admission_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('batch');
        });

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn(['has_batches', 'batches']);
        });
    }
};

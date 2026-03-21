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
        Schema::table('institutions', function (Blueprint $table) {
            $table->dateTime('admission_start_date')->nullable()->after('status');
            $table->dateTime('admission_end_date')->nullable()->after('admission_start_date');
            $table->boolean('is_admission_open')->default(false)->after('admission_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['admission_start_date', 'admission_end_date', 'is_admission_open']);
        });
    }
};

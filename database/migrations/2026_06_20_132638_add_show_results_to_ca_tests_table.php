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
            $table->boolean('show_results')->default(true)->after('randomize_options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ca_tests', function (Blueprint $table) {
            $table->dropColumn('show_results');
        });
    }
};

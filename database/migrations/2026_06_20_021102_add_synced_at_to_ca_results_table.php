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
        Schema::table('ca_results', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable()->after('attempt_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ca_results', function (Blueprint $table) {
            $table->dropColumn('synced_at');
        });
    }
};

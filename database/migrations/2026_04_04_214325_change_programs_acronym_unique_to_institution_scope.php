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
        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique(['acronym']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->unique(['institution_id', 'acronym']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique(['institution_id', 'acronym']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->unique('acronym');
        });
    }
};

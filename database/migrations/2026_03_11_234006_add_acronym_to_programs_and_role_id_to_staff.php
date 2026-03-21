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
            $table->string('acronym', 10)->nullable()->after('name')->unique();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('institution_id')->constrained('roles', 'role_id')->nullOnDelete();
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('role')->default('lecturer')->after('status');
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('acronym');
        });
    }
};

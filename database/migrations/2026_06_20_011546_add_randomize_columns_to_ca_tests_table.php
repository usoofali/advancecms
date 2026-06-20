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
            $table->boolean('randomize_questions')->default(true)->after('is_published');
            $table->boolean('randomize_options')->default(true)->after('randomize_questions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ca_tests', function (Blueprint $table) {
            $table->dropColumn(['randomize_questions', 'randomize_options']);
        });
    }
};

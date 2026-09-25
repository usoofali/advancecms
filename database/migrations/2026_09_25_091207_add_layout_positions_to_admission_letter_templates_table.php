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
        Schema::table('admission_letter_templates', function (Blueprint $table) {
            $table->string('logo_position')->default('left')->after('signatory_subtitle');
            $table->string('qr_position')->default('header_right')->after('logo_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_letter_templates', function (Blueprint $table) {
            $table->dropColumn(['logo_position', 'qr_position']);
        });
    }
};

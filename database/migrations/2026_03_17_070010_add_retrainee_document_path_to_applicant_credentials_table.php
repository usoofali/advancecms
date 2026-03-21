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
        Schema::table('applicant_credentials', function (Blueprint $table) {
            $table->string('retrainee_document_path')->nullable()->after('secondary_document_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_credentials', function (Blueprint $table) {
            $table->dropColumn('retrainee_document_path');
        });
    }
};

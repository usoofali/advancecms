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
            $table->string('primary_document_path')->nullable()->after('subject_physics');
            $table->string('secondary_document_path')->nullable()->after('primary_document_path');
            $table->dropColumn('document_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_credentials', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('subject_physics');
            $table->dropColumn(['primary_document_path', 'secondary_document_path']);
        });
    }
};

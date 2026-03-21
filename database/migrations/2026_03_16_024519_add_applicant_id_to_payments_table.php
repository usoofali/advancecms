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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('student_invoice_id')->nullable()->change();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->cascadeOnDelete()->after('student_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('student_invoice_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('applicant_id');
        });
    }
};

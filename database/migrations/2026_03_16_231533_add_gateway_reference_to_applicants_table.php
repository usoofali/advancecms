<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('gateway_reference')->nullable()->after('payment_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn('gateway_reference');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_items', 'is_contact_overridden')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->boolean('is_contact_overridden')->default(false)->after('contacts_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_items', 'is_contact_overridden')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->dropColumn('is_contact_overridden');
            });
        }
    }
};

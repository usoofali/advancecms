<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('institutions', 'addons')) {
            Schema::table('institutions', function (Blueprint $table) {
                $table->dropColumn('addons');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('institutions', 'addons')) {
            Schema::table('institutions', function (Blueprint $table) {
                $table->json('addons')->nullable()->after('status');
            });
        }
    }
};

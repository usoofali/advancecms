<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placement_supervisors', function (Blueprint $table) {
            if (! Schema::hasColumn('placement_supervisors', 'supervisable_type')) {
                $table->nullableMorphs('supervisable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('placement_supervisors', function (Blueprint $table) {
            if (Schema::hasColumn('placement_supervisors', 'supervisable_type')) {
                $table->dropMorphs('supervisable');
            }
        });
    }
};

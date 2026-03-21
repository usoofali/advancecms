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
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('staff_number', 30)->unique()->after('institution_id');
            $table->string('first_name')->after('staff_number');
            $table->string('last_name')->after('first_name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('designation')->after('phone');
            $table->enum('status', ['active', 'inactive', 'suspended', 'retired'])->default('active')->after('designation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('name')->after('institution_id');
            $table->dropColumn(['staff_number', 'first_name', 'last_name', 'phone', 'designation', 'status']);
        });
    }
};

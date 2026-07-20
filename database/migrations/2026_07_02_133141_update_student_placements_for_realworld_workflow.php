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
        Schema::table('student_placements', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->change();
            $table->string('custom_organization_name')->nullable()->after('organization_id');
            $table->string('custom_organization_address')->nullable()->after('custom_organization_name');
            $table->string('custom_organization_city')->nullable()->after('custom_organization_address');
            $table->string('custom_organization_state')->nullable()->after('custom_organization_city');
            $table->string('workflow_stage')->default('Pending_Selection')->after('status');
        });

        Schema::table('generated_documents', function (Blueprint $table) {
            $table->string('purpose')->default('request')->after('document_number'); // 'request', 'acceptance_form', 'posting', 'group_cover'
            $table->string('batch_group_id')->nullable()->after('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'batch_group_id']);
        });

        Schema::table('student_placements', function (Blueprint $table) {
            $table->dropColumn([
                'custom_organization_name',
                'custom_organization_address',
                'custom_organization_city',
                'custom_organization_state',
                'workflow_stage',
            ]);
        });
    }
};

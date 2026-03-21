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
        Schema::table('registration_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('registration_statuses', 'is_closed')) {
                $table->dropColumn('is_closed');
            }

            if (! Schema::hasColumn('registration_statuses', 'status')) {
                $table->enum('status', ['open', 'closed'])->default('open')->after('semester_id');
            }

            $uniqueName = 'unique_registration_status';
            $indexes = Schema::getIndexes('registration_statuses');
            $indexExists = collect($indexes)->contains(fn ($index) => $index['name'] === $uniqueName);

            if (! $indexExists) {
                $table->unique(['student_id', 'academic_session_id', 'semester_id'], $uniqueName);
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_statuses', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->boolean('is_closed')->default(false)->after('semester_id');
        });
    }
};

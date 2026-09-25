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
        if (! Schema::connection('hub_mysql')->hasTable('tenant_user_cache')) {
            Schema::connection('hub_mysql')->create('tenant_user_cache', function (Blueprint $table) {
                $table->id();
                $table->string('phone_number', 32)->unique()->index();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('external_user_id', 100)->index();
                $table->enum('role', ['student', 'staff', 'guardian'])->default('student');
                $table->unsignedBigInteger('institution_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::connection('hub_mysql')->table('tenant_user_cache', function (Blueprint $table) {
                if (! Schema::connection('hub_mysql')->hasColumn('tenant_user_cache', 'institution_id')) {
                    $table->unsignedBigInteger('institution_id')->nullable()->after('role');
                }
                if (! Schema::connection('hub_mysql')->hasColumn('tenant_user_cache', 'metadata')) {
                    $table->json('metadata')->nullable()->after('institution_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('hub_mysql')->dropIfExists('tenant_user_cache');
    }
};

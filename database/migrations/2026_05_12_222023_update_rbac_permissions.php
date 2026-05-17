<?php

use Database\Seeders\RbacSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // We will truncate the role_permissions and permissions tables to prepare for the new granular RBAC
        Schema::disableForeignKeyConstraints();
        DB::table('role_permissions')->truncate();
        DB::table('permissions')->truncate();
        Schema::enableForeignKeyConstraints();

        // Run the seeder to repopulate roles and permissions
        $seeder = new RbacSeeder;
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback is destructive in this case, but we could theoretically restore old data if we backed it up.
        // For this specific migration, we just leave it as is or truncate and let an older seeder run.
    }
};

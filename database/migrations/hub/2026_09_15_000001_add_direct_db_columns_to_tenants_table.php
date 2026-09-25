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
        if (Schema::connection('hub_mysql')->hasTable('tenants')) {
            Schema::connection('hub_mysql')->table('tenants', function (Blueprint $table) {
                if (! Schema::connection('hub_mysql')->hasColumn('tenants', 'institution_id')) {
                    $table->unsignedBigInteger('institution_id')->nullable()->default(1)->after('code');
                }
                if (! Schema::connection('hub_mysql')->hasColumn('tenants', 'db_host')) {
                    $table->string('db_host', 100)->default('127.0.0.1')->after('code');
                    $table->string('db_port', 10)->default('3306')->after('db_host');
                    $table->string('db_database', 100)->nullable()->after('db_port');
                    $table->string('db_username', 100)->nullable()->after('db_database');
                    $table->text('db_password')->nullable()->after('db_username');
                }
                if (Schema::connection('hub_mysql')->hasColumn('tenants', 'api_base_url')) {
                    $table->dropColumn(['api_base_url', 'api_key', 'api_secret']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('hub_mysql')->hasTable('tenants')) {
            Schema::connection('hub_mysql')->table('tenants', function (Blueprint $table) {
                if (Schema::connection('hub_mysql')->hasColumn('tenants', 'db_host')) {
                    $table->dropColumn(['db_host', 'db_port', 'db_database', 'db_username', 'db_password']);
                }
            });
        }
    }
};

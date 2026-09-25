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
        if (! Schema::connection('hub_mysql')->hasTable('tenants')) {
            Schema::connection('hub_mysql')->create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 50)->unique();
                $table->unsignedBigInteger('institution_id')->nullable()->default(1);
                // Direct Database Connection Credentials (MySQL)
                $table->string('db_host', 100)->default('127.0.0.1');
                $table->string('db_port', 10)->default('3306');
                $table->string('db_database', 100)->nullable();
                $table->string('db_username', 100)->nullable();
                $table->text('db_password')->nullable();
                $table->enum('status', ['active', 'suspended', 'maintenance'])->default('active');
                $table->timestamps();
            });
        } else {
            Schema::connection('hub_mysql')->table('tenants', function (Blueprint $table) {
                if (! Schema::connection('hub_mysql')->hasColumn('tenants', 'institution_id')) {
                    $table->unsignedBigInteger('institution_id')->nullable()->default(1)->after('code');
                }
                if (! Schema::connection('hub_mysql')->hasColumn('tenants', 'db_host')) {
                    $table->string('db_host', 100)->default('127.0.0.1');
                    $table->string('db_port', 10)->default('3306');
                    $table->string('db_database', 100)->nullable();
                    $table->string('db_username', 100)->nullable();
                    $table->text('db_password')->nullable();
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
        Schema::connection('hub_mysql')->dropIfExists('tenants');
    }
};

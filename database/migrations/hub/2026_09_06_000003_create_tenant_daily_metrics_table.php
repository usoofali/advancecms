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
        if (! Schema::connection('hub_mysql')->hasTable('tenant_daily_metrics')) {
            Schema::connection('hub_mysql')->create('tenant_daily_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->date('metric_date')->index();
                $table->unsignedInteger('total_students')->default(0);
                $table->unsignedInteger('active_students')->default(0);
                $table->unsignedInteger('total_staff')->default(0);
                $table->decimal('fees_collected', 14, 2)->default(0.00);
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'metric_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('hub_mysql')->dropIfExists('tenant_daily_metrics');
    }
};

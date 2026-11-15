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
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('classification', 30)->default('durable'); // durable, consumable
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['institution_id', 'classification'], 'inv_cat_inst_class_idx');
            $table->index(['institution_id', 'status'], 'inv_cat_inst_status_idx');
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('inventory_category_id')->constrained('inventory_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->string('unit_of_measure', 50)->default('Pieces');
            $table->unsignedInteger('min_stock_threshold')->default(0);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'inv_items_inst_code_unique');
            $table->index(['institution_id', 'inventory_category_id'], 'inv_items_inst_cat_idx');
            $table->index(['institution_id', 'status'], 'inv_items_inst_status_idx');
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('location_type', 40)->default('lab_clinic'); // lab_clinic, store_warehouse, office_hall, workshop, other
            $table->nullableMorphs('locatable');
            $table->string('building_or_block')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['institution_id', 'location_type'], 'inv_loc_inst_type_idx');
            $table->index(['institution_id', 'status'], 'inv_loc_inst_status_idx');
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('min_threshold_override')->nullable();
            $table->timestamp('last_counted_at')->nullable();
            $table->foreignId('last_counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inventory_item_id', 'inventory_location_id'], 'inv_stocks_item_location_unique');
            $table->index(['institution_id', 'inventory_location_id'], 'inv_stocks_inst_loc_idx');
        });

        Schema::create('inventory_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('inventory_stock_id')->constrained('inventory_stocks')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->unsignedInteger('previous_quantity');
            $table->unsignedInteger('new_quantity');
            $table->integer('variance');
            $table->string('adjustment_type', 40)->default('recount'); // recount, initial_stock, replenishment, consumption, damage_writeoff
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'inventory_location_id'], 'inv_adj_inst_loc_idx');
            $table->index(['institution_id', 'inventory_item_id'], 'inv_adj_inst_item_idx');
            $table->index(['institution_id', 'created_at'], 'inv_adj_inst_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_adjustments');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
    }
};

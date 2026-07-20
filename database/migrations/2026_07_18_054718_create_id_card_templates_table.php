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
        Schema::create('id_card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('student'); // student, staff, both
            $table->string('layout')->default('classic'); // classic, modern_sidebar, minimal
            $table->string('orientation')->default('horizontal'); // horizontal, vertical
            $table->string('primary_color', 7)->default('#2563eb');
            $table->string('secondary_color', 7)->default('#1e40af');
            $table->string('text_color', 7)->default('#ffffff');
            $table->string('accent_color', 7)->default('#f59e0b');
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('background_image_path')->nullable();
            $table->boolean('show_qr')->default(true);
            $table->boolean('show_barcode')->default(true);
            $table->boolean('show_blood_group')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_card_templates');
    }
};

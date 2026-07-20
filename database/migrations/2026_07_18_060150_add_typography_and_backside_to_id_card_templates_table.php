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
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->string('font_family')->default('Inter, sans-serif')->after('accent_color');
            $table->string('font_weight')->default('normal')->after('font_family');
            $table->string('font_style')->default('normal')->after('font_weight');
            $table->string('text_align')->default('left')->after('font_style');
            $table->text('disclaimer_text')->nullable()->after('footer_text');
            $table->boolean('show_signature_line')->default(true)->after('show_blood_group');
            $table->string('back_background_color', 7)->default('#f8fafc')->after('text_align');
            $table->string('back_text_color', 7)->default('#3f3f46')->after('back_background_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->dropColumn([
                'font_family',
                'font_weight',
                'font_style',
                'text_align',
                'disclaimer_text',
                'show_signature_line',
                'back_background_color',
                'back_text_color',
            ]);
        });
    }
};

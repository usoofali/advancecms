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
        Schema::create('admission_letter_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'notification' or 'offer'
            $table->string('letter_title')->nullable();
            $table->string('salutation')->nullable();
            $table->text('opening_text')->nullable();
            $table->text('body_text')->nullable();
            $table->text('conditions_text')->nullable();
            $table->text('closing_text')->nullable();
            $table->string('signatory_title')->nullable();
            $table->string('signatory_subtitle')->nullable();
            $table->boolean('show_qr_code')->default(true);
            $table->timestamps();

            $table->unique(['institution_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_letter_templates');
    }
};

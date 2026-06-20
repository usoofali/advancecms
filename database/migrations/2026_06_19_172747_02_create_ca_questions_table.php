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
        Schema::create('ca_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ca_test_id')->constrained('ca_tests')->cascadeOnDelete();
            $table->text('text');
            $table->string('image_path')->nullable();
            $table->integer('coin_reward')->default(0);
            $table->decimal('marks', 8, 2)->default(1.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ca_questions');
    }
};

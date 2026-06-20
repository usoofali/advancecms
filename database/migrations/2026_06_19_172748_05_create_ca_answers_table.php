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
        Schema::create('ca_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ca_attempt_id')->constrained('ca_attempts')->cascadeOnDelete();
            $table->foreignId('ca_question_id')->constrained('ca_questions')->cascadeOnDelete();
            $table->foreignId('ca_question_option_id')->nullable()->constrained('ca_question_options')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->decimal('marks_earned', 8, 2)->default(0.00);
            $table->integer('coins_earned')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ca_answers');
    }
};

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
        Schema::create('cbt_exams', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->integer('duration_minutes')->default(60);
            $table->integer('total_questions')->default(50);
            $table->decimal('pass_mark', 5, 2)->default(40.00);
            $table->boolean('randomize_questions')->default(true);
            $table->boolean('randomize_options')->default(true);
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('cbt_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cbt_exam_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->string('media_path')->nullable();
            $table->enum('type', ['single', 'multiple'])->default('single');
            $table->decimal('marks', 5, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('cbt_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cbt_question_id')->constrained()->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });

        Schema::create('student_cbt_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('cbt_pin', 6);
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id', 'semester_id'], 'student_cbt_profile_unique');
        });

        Schema::create('cbt_results_staging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbt_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->integer('attempt_number')->default(1);
            $table->enum('attempt_type', ['normal', 'resit', 'carryover'])->default('normal');
            $table->decimal('score_raw', 8, 2);
            $table->decimal('score_percent', 5, 2);
            $table->json('responses')->nullable();
            $table->string('submission_token')->unique();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cbt_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['export', 'import']);
            $table->string('resource_type'); // e.g., 'exam', 'results'
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('status'); // e.g., 'success', 'failed'
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_sync_logs');
        Schema::dropIfExists('cbt_results_staging');
        Schema::dropIfExists('student_cbt_profiles');
        Schema::dropIfExists('cbt_options');
        Schema::dropIfExists('cbt_questions');
        Schema::dropIfExists('cbt_exams');
    }
};

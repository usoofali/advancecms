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
        Schema::create('project_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('coordinator_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('Active');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('project_session_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_session_id')->constrained('project_sessions')->cascadeOnDelete();
            $table->string('title');
            $table->integer('stage_order')->default(1);
            $table->text('description')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamps();
        });

        Schema::create('student_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('project_session_id')->constrained('project_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedBigInteger('approved_topic_id')->nullable();
            $table->foreignId('current_stage_id')->nullable()->constrained('project_session_stages')->nullOnDelete();
            $table->string('overall_status')->default('Topic Pending');
            $table->integer('progress_percentage')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_project_id')->constrained('student_projects')->cascadeOnDelete();
            $table->string('topic_title');
            $table->text('description')->nullable();
            $table->integer('topic_order')->default(1);
            $table->string('status')->default('Submitted');
            $table->text('feedback')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('student_projects', function (Blueprint $table) {
            $table->foreign('approved_topic_id')->references('id')->on('project_topics')->nullOnDelete();
        });

        Schema::create('project_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_project_id')->constrained('student_projects')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('project_session_stages')->cascadeOnDelete();
            $table->integer('version_number')->default(1);
            $table->text('student_remarks')->nullable();
            $table->string('file_path');
            $table->string('file_original_name')->nullable();
            $table->string('status')->default('Submitted');
            $table->text('supervisor_feedback')->nullable();
            $table->string('supervisor_file_path')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_project_id')->constrained('student_projects')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_project_id')->constrained('student_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_activities');
        Schema::dropIfExists('project_messages');
        Schema::dropIfExists('project_submissions');

        Schema::table('student_projects', function (Blueprint $table) {
            $table->dropForeign(['approved_topic_id']);
        });

        Schema::dropIfExists('project_topics');
        Schema::dropIfExists('student_projects');
        Schema::dropIfExists('project_session_stages');
        Schema::dropIfExists('project_sessions');
    }
};

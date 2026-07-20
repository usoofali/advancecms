<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('placement_type_id')->constrained('placement_types')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('academic_session');

            // Placement status
            $table->string('status')->default('Pending'); // Pending, Assigned, Accepted, Rejected, Completed, Cancelled

            // Approval workflow for document generation
            $table->string('approval_status')->default('Draft'); // Draft, Department_Approved, Academic_Approved, Generated

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_placements');
    }
};

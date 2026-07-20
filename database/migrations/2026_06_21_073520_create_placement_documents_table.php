<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('student_placements')->cascadeOnDelete();
            $table->string('type'); // e.g., Acceptance Letter, Response Letter
            $table->string('file_path');

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_documents');
    }
};

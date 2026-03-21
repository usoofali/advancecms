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
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->foreignId('institution_id')->constrained('institutions')->onDelete('cascade');
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('application_form_id')->constrained('application_forms')->onDelete('cascade');
            $table->string('payment_status')->default('pending'); // pending, paid
            $table->string('admission_status')->default('pending'); // pending, admitted, rejected
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};

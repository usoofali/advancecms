<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

test('scopeAtLevel handles students admitted in future or past years without unsigned underflow error', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($institution)->for($department)->create();

    $session2023 = AcademicSession::factory()->create(['name' => '2023/2024']);
    $session2025 = AcademicSession::factory()->create(['name' => '2025/2026']);

    // Student admitted in 2025 (later than session 2023)
    $student2025 = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'admission_year' => '2025',
        'entry_level' => 100,
    ]);

    // Student admitted in 2023 (same as session 2023)
    $student2023 = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'admission_year' => '2023',
        'entry_level' => 100,
    ]);

    // Query session 2023 at level 100
    $studentsAt100In2023 = Student::atLevel(100, $session2023)->pluck('id')->toArray();

    expect($studentsAt100In2023)->toContain($student2023->id)
        ->and($studentsAt100In2023)->not->toContain($student2025->id);

    // Query session 2025 at level 100
    $studentsAt100In2025 = Student::atLevel(100, $session2025)->pluck('id')->toArray();

    expect($studentsAt100In2025)->toContain($student2025->id);
});

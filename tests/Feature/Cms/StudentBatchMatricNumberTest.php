<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Student::$suppressEnrollmentNotification = true;
});

it('generates standard matric number when no batch is specified', function (): void {
    $institution = Institution::factory()->create(['acronym' => 'TEST']);
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'acronym' => 'CSC',
    ]);

    $student = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'admission_year' => 2025,
        'entry_level' => 100,
        'status' => 'active',
    ]);

    expect($student->matric_number)->toBe('TEST/CSC/2025/001');
    expect($student->batch)->toBeNull();
});

it('generates standard matric number for Batch A without suffix and maintains shared sequence', function (): void {
    $institution = Institution::factory()->create(['acronym' => 'TEST']);
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'acronym' => 'CSC',
    ]);

    // First student: default/legacy (no batch)
    $student1 = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'First',
        'last_name' => 'Student',
        'gender' => 'male',
        'admission_year' => 2025,
        'entry_level' => 100,
        'status' => 'active',
    ]);

    // Second student: Batch A
    $student2 = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'Second',
        'last_name' => 'Student',
        'gender' => 'female',
        'admission_year' => 2025,
        'batch' => 'A',
        'entry_level' => 100,
        'status' => 'active',
    ]);

    expect($student1->matric_number)->toBe('TEST/CSC/2025/001');
    expect($student2->matric_number)->toBe('TEST/CSC/2025/002');
    expect($student2->batch)->toBe('A');
});

it('generates independent numbering sequence and /B suffix for Batch B students', function (): void {
    $institution = Institution::factory()->create(['acronym' => 'TEST']);
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'acronym' => 'CSC',
    ]);

    // Batch A students
    Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'A1',
        'last_name' => 'Student',
        'gender' => 'male',
        'admission_year' => 2025,
        'batch' => 'A',
        'entry_level' => 100,
        'status' => 'active',
    ]);

    // Batch B students (start at 001 with /B suffix)
    $batchBStudent1 = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'B1',
        'last_name' => 'Student',
        'gender' => 'female',
        'admission_year' => 2025,
        'batch' => 'B',
        'entry_level' => 100,
        'status' => 'active',
    ]);

    $batchBStudent2 = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'B2',
        'last_name' => 'Student',
        'gender' => 'male',
        'admission_year' => 2025,
        'batch' => 'B',
        'entry_level' => 100,
        'status' => 'active',
    ]);

    expect($batchBStudent1->matric_number)->toBe('TEST/CSC/2025/001/B');
    expect($batchBStudent2->matric_number)->toBe('TEST/CSC/2025/002/B');
});

it('calculates the exact same academic level for Batch A and Batch B students under the same session', function (): void {
    $session2025 = AcademicSession::create([
        'name' => '2025/2026',
        'status' => 'active',
        'has_batches' => true,
        'batches' => ['A', 'B'],
    ]);

    $session2027 = AcademicSession::create([
        'name' => '2027/2028',
        'status' => 'closed',
    ]);

    $institution = Institution::factory()->create();
    $program = Program::factory()->create(['institution_id' => $institution->id]);

    $studentA = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'BatchA',
        'last_name' => 'Student',
        'gender' => 'male',
        'admission_year' => 2025,
        'batch' => 'A',
        'entry_level' => 100,
        'status' => 'active',
    ]);

    $studentB = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'BatchB',
        'last_name' => 'Student',
        'gender' => 'female',
        'admission_year' => 2025,
        'batch' => 'B',
        'entry_level' => 100,
        'status' => 'active',
    ]);

    // Both enter at 100L in 2025/2026
    expect($studentA->currentLevel($session2025))->toBe(100);
    expect($studentB->currentLevel($session2025))->toBe(100);

    // Both advance to 300L in 2027/2028
    expect($studentA->currentLevel($session2027))->toBe(300);
    expect($studentB->currentLevel($session2027))->toBe(300);
});

it('registers student in Batch B via create student component and generates /B matric number', function (): void {
    $institution = Institution::factory()->create(['acronym' => 'ADV']);
    $department = Department::factory()->create(['institution_id' => $institution->id]);
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'acronym' => 'SFT',
    ]);

    $session = AcademicSession::create([
        'name' => '2025/2026',
        'status' => 'active',
        'has_batches' => true,
        'batches' => ['A', 'B'],
    ]);

    $admin = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($admin);

    Livewire::test('pages::cms.students.create')
        ->set('department_id', $department->id)
        ->set('program_id', $program->id)
        ->set('session_id', $session->id)
        ->set('batch', 'B')
        ->set('first_name', 'Musa')
        ->set('last_name', 'Ali')
        ->set('gender', 'male')
        ->set('entry_level', 100)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successBatch', 'B')
        ->assertSet('successMatricNumber', 'ADV/SFT/2025/001/B');

    $student = Student::where('email', null)->latest()->first();
    expect($student)->not->toBeNull();
    expect($student->batch)->toBe('B');
    expect($student->matric_number)->toBe('ADV/SFT/2025/001/B');
});

it('filters students by batch in students directory', function (): void {
    $institution = Institution::factory()->create(['acronym' => 'ADV']);
    $department = Department::factory()->create(['institution_id' => $institution->id]);
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'duration_years' => 4,
    ]);

    AcademicSession::create([
        'name' => '2025/2026',
        'status' => 'active',
        'has_batches' => true,
        'batches' => ['A', 'B'],
    ]);

    $studentA = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'Alpha',
        'last_name' => 'User',
        'gender' => 'male',
        'admission_year' => 2025,
        'batch' => 'A',
        'entry_level' => 100,
        'status' => 'active',
    ]);

    $studentB = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'first_name' => 'Beta',
        'last_name' => 'User',
        'gender' => 'female',
        'admission_year' => 2025,
        'batch' => 'B',
        'entry_level' => 100,
        'status' => 'active',
    ]);

    $admin = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($admin);

    // Filter by Batch B
    Livewire::test('pages::cms.students.index')
        ->set('filterBatch', 'B')
        ->assertSee($studentB->full_name)
        ->assertDontSee($studentA->full_name);

    // Filter by Batch A
    Livewire::test('pages::cms.students.index')
        ->set('filterBatch', 'A')
        ->assertSee($studentA->full_name)
        ->assertDontSee($studentB->full_name);
});

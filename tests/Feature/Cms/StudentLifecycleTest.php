<?php

use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('deletes linked user account when student is deleted and creates new account when student is re-created', function (): void {
    $institution = Institution::factory()->create();
    $program = Program::factory()->create(['institution_id' => $institution->id]);

    Student::$suppressEnrollmentNotification = true;

    // 1. Create initial student
    $student = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'matric_number' => 'TEST/2025/001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'email' => 'johndoe@test.com',
        'admission_year' => 2025,
        'entry_level' => 100,
        'status' => 'active',
    ]);

    expect(User::where('email', 'johndoe@test.com')->exists())->toBeTrue();

    // 2. Delete student
    $student->delete();
    expect(User::where('email', 'johndoe@test.com')->exists())->toBeFalse();

    // 3. Re-create student with updated matric number
    $recreatedStudent = Student::create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'matric_number' => 'TEST/2025/002',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'email' => 'johndoe@test.com',
        'admission_year' => 2025,
        'entry_level' => 100,
        'status' => 'active',
    ]);

    expect(User::where('email', 'johndoe@test.com')->exists())->toBeTrue();
});

<?php

use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('syncs missing student user accounts via artisan command', function (): void {
    $institution = Institution::factory()->create();
    $program = Program::factory()->create(['institution_id' => $institution->id]);

    Student::$suppressEnrollmentNotification = true;
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'email' => 'artisanteststudent@test.com',
    ]);

    User::where('email', 'artisanteststudent@test.com')->delete();

    $this->artisan('students:sync-users')
        ->expectsOutputToContain('Successfully created 1 missing student user accounts.')
        ->assertExitCode(0);

    expect(User::where('email', 'artisanteststudent@test.com')->exists())->toBeTrue();
});

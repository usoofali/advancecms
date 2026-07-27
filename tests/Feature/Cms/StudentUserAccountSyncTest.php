<?php

use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('displays missing user account status and allows creating user account from student profile', function (): void {
    $institution = Institution::factory()->create();
    $program = Program::factory()->create(['institution_id' => $institution->id]);

    $admin = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    // Create student without triggering saved listener user creation (or delete user)
    Student::$suppressEnrollmentNotification = true;
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'email' => 'orphanstudent@test.com',
    ]);

    User::where('email', 'orphanstudent@test.com')->delete();

    $this->actingAs($admin);

    Volt::test('pages.cms.students.show', ['student' => $student])
        ->assertSee('User Account Missing')
        ->assertSee('Missing Portal Login Account')
        ->call('createUserAccount')
        ->assertDispatched('notify');

    expect(User::where('email', 'orphanstudent@test.com')->exists())->toBeTrue();
});

it('allows authorized admin to reset student user password to default from profile page', function (): void {
    $institution = Institution::factory()->create();
    $program = Program::factory()->create(['institution_id' => $institution->id]);

    $admin = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
    ]);

    $user = User::where('email', $student->email)->first();

    $this->actingAs($admin);

    Volt::test('pages.cms.students.show', ['student' => $student])
        ->assertSee('User Account OK')
        ->call('resetUserPassword')
        ->assertDispatched('notify');

    $user->refresh();
    expect(Hash::check('12345678', $user->password))->toBeTrue();
});

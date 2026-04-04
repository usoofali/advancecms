<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function seedStudentForLetter(): array
{
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'email' => null,
    ]);

    return compact('institution', 'student');
}

it('redirects guests to login when viewing student admission letter', function (): void {
    $data = seedStudentForLetter();

    $this->get(route('cms.students.admission-letter', $data['student']))
        ->assertRedirect(route('login'));
});

it('forbids staff from another institution from viewing the letter', function (): void {
    $data = seedStudentForLetter();
    $otherInstitution = Institution::factory()->create();
    $user = User::factory()
        ->for($otherInstitution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user)
        ->get(route('cms.students.admission-letter', $data['student']))
        ->assertForbidden();
});

it('allows staff of the same institution to view and print the admission letter', function (): void {
    $data = seedStudentForLetter();
    $user = User::factory()
        ->for($data['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $response = $this->actingAs($user)
        ->get(route('cms.students.admission-letter', $data['student']));

    $response->assertSuccessful();
    $response->assertSee($data['student']->matric_number, false);
    $response->assertSee($data['institution']->name, false);
    $response->assertSee('OFFER OF PROVISIONAL ADMISSION', false);
});

it('shows admission letter link on student profile for authorized staff', function (): void {
    $data = seedStudentForLetter();
    $user = User::factory()
        ->for($data['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user)
        ->get(route('cms.students.show', $data['student']))
        ->assertSuccessful()
        ->assertSee(route('cms.students.admission-letter', $data['student'], false), false);
});

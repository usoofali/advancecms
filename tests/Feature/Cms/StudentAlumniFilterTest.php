<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('filters alumni students whose level is greater than 300', function (): void {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);

    $student100 = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'entry_level' => 100,
        'admission_year' => '2023',
    ]);

    $student400 = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'entry_level' => 400,
        'admission_year' => '2020',
    ]);

    $user = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::cms.students.index')
        ->set('filterLevel', 'alumni')
        ->assertSee($student400->matric_number, false)
        ->assertDontSee($student100->matric_number, false)
        ->assertSee('Alumni (>300 Level)');
});

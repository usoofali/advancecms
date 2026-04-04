<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('redirects guests to login', function (): void {
    $this->get(route('cms.admissions.issue-notification'))
        ->assertRedirect(route('login'));
});

it('forbids users without view_applications', function (): void {
    $user = User::factory()->withRole('Student')->create();

    $this->actingAs($user)
        ->get(route('cms.admissions.issue-notification'))
        ->assertForbidden();
});

it('allows admission staff to open the issue notification page', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Admission Officer')
        ->create();

    $this->actingAs($user)
        ->get(route('cms.admissions.issue-notification'))
        ->assertSuccessful()
        ->assertSee('Issue admission notification', false);
});

it('generates a printable notification letter from entered details', function (): void {
    $institution = Institution::factory()->create(['name' => 'Test Polytechnic']);
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'name' => 'Computer Science',
        'award_type' => 'diploma',
    ]);
    $user = User::factory()
        ->for($institution)
        ->withRole('Admission Officer')
        ->create();

    Livewire::actingAs($user)
        ->test('pages::cms.admissions.issue-admission-notification')
        ->set('departmentId', (string) $department->id)
        ->set('programId', (string) $program->id)
        ->set('fullName', 'Jane Candidate')
        ->set('academicSession', '2026/2027')
        ->set('entryLevel', 200)
        ->set('admissionYear', 2026)
        ->set('awardType', 'diploma')
        ->call('generateLetter')
        ->tap(function ($component): void {
            expect($component->get('letter')['letter_title'])->toBe('NOTIFICATION OF PROVISIONAL ADMISSION');
        })
        ->assertSee('JANE CANDIDATE', false)
        ->assertSee('2026/2027', false)
        ->assertSee('Computer Science', false)
        ->assertSee('NOTIFICATION OF PROVISIONAL ADMISSION', false)
        ->assertSee('Test Polytechnic', false)
        ->assertSee('200L', false)
        ->assertSee('Diploma', false);
});

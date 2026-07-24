<?php

use App\Models\AcademicSession;
use App\Models\CbtPinAccessControl;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

test('cbt pin access is locked by default for session, semester and program', function (): void {
    $institution = Institution::factory()->create([
        'addons' => ['exam_module'],
    ]);
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($institution)->for($department)->create();

    $isUnlocked = CbtPinAccessControl::isUnlocked($institution->id, $session->id, $semester->id, $program->id);
    expect($isUnlocked)->toBeFalse();
});

test('authorized admin can unlock and lock cbt pin access via pin-access page', function (): void {
    $institution = Institution::factory()->create([
        'addons' => ['exam_module'],
    ]);
    $adminUser = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($institution)->for($department)->create();

    Livewire::actingAs($adminUser)
        ->test('pages::cms.cbt.pin-access', [
            'session_id' => $session->id,
            'semester_id' => $semester->id,
        ])
        ->assertOk()
        ->call('toggleProgramUnlock', $program->id);

    expect(CbtPinAccessControl::isUnlocked($institution->id, $session->id, $semester->id, $program->id))->toBeTrue();

    Livewire::actingAs($adminUser)
        ->test('pages::cms.cbt.pin-access', [
            'session_id' => $session->id,
            'semester_id' => $semester->id,
        ])
        ->call('toggleProgramUnlock', $program->id);

    expect(CbtPinAccessControl::isUnlocked($institution->id, $session->id, $semester->id, $program->id))->toBeFalse();
});

test('exam card respects cbt pin access lock state', function (): void {
    $institution = Institution::factory()->create([
        'addons' => ['exam_module'],
    ]);
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($institution)->for($department)->create();

    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
    ]);

    $studentUser = User::where('email', $student->email)->first();
    $studentRole = Role::where('role_name', 'Student')->first();
    $studentUser->roles()->sync([$studentRole->role_id]);

    // Default state: LOCKED
    Livewire::actingAs($studentUser)
        ->test('pages::cms.students.exam-card', [
            'session_id' => $session->id,
            'semester_id' => $semester->id,
        ])
        ->assertOk()
        ->assertSee('LOCKED (CONTACT EXAM OFFICE)');

    // Unlock access for this program
    CbtPinAccessControl::create([
        'institution_id' => $institution->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'program_id' => $program->id,
        'is_unlocked' => true,
        'unlocked_at' => now(),
    ]);

    // Unlocked state: coated scratch card foil
    Livewire::actingAs($studentUser)
        ->test('pages::cms.students.exam-card', [
            'session_id' => $session->id,
            'semester_id' => $semester->id,
        ])
        ->assertOk()
        ->assertSee('CLICK TO SCRATCH PIN')
        ->assertDontSee('LOCKED (CONTACT EXAM OFFICE)');
});

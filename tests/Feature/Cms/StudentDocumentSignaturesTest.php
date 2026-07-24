<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

test('course form view renders student signature and academic secretary signature', function () {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($institution)->for($department)->create();
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);

    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'signature_path' => 'signatures/student_sig.png',
    ]);

    $studentUser = User::where('email', $student->email)->first();

    $secRole = Role::where('role_name', 'Academic Secretary')->first();
    $secStaff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'signature_path' => 'signatures/sec_sig.png',
        'role_id' => $secRole->role_id,
    ]);

    Livewire::actingAs($studentUser)
        ->test('pages::cms.students.course-form', [
            'session_id' => $session->id,
            'semester_id' => $semester->id,
        ])
        ->assertOk()
        ->assertSee('Student Signature & Date')
        ->assertSee('Academic Secretary Signature, Stamp & Date')
        ->assertSee('signatures/student_sig.png')
        ->assertSee('signatures/sec_sig.png');
});

test('exam card view renders student signature and examination officer signature and excludes bursary clearance', function () {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($institution)->for($department)->create();
    $session = AcademicSession::factory()->create(['status' => 'active']);
    $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'name' => 'first']);

    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'signature_path' => 'signatures/student_exam_sig.png',
    ]);

    $studentUser = User::where('email', $student->email)->first();

    $examRole = Role::where('role_name', 'Exam Officer')->first();
    $examStaff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'signature_path' => 'signatures/exam_officer_sig.png',
        'role_id' => $examRole->role_id,
    ]);

    Livewire::actingAs($studentUser)
        ->test('pages::cms.students.exam-card', [
            'session_id' => $session->id,
            'semester_id' => $semester->id,
        ])
        ->assertOk()
        ->assertSee('Student\'s Signature & Date')
        ->assertSee('Examination Officer Signature, Stamp & Date')
        ->assertDontSee('Bursary Clearance (Stamp/Sign)')
        ->assertDontSee('Level Coordinator Sign & Date')
        ->assertSee('signatures/student_exam_sig.png')
        ->assertSee('signatures/exam_officer_sig.png');
});

<?php

use App\Actions\Placements\AssignPlacementSupervisorAction;
use App\Actions\Placements\SubmitPlacementEvaluationAction;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\PlacementSupervisor;
use App\Models\PlacementType;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\User;
use App\Services\PlacementSupervisorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->institution = Institution::create([
        'name' => 'State Polytechnic',
        'code' => 'STP',
    ]);

    $this->session = AcademicSession::create([
        'name' => '2025/2026',
        'status' => 'active',
        'institution_id' => $this->institution->id,
    ]);

    $this->organization = Organization::create([
        'name' => 'Apex Tech Solutions',
        'email' => 'contact@apextech.com',
        'address' => '12 Innovation Way',
        'institution_id' => $this->institution->id,
    ]);

    $this->department = Department::create([
        'name' => 'Computer Science',
        'code' => 'CSC',
        'institution_id' => $this->institution->id,
    ]);

    $this->program = Program::create([
        'name' => 'B.Sc Computer Science',
        'code' => 'BSCCSC',
        'department_id' => $this->department->id,
        'institution_id' => $this->institution->id,
    ]);

    $this->placementType = PlacementType::create([
        'name' => 'SIWES 6 Months',
        'code' => 'SIWES-6M',
        'institution_id' => $this->institution->id,
    ]);

    $this->admin = User::factory()->create();
    $this->lecturer = User::factory()->create();
    $this->studentUser = User::factory()->create();

    $this->student = Student::factory()->create([
        'institution_id' => $this->institution->id,
        'program_id' => $this->program->id,
        'matric_number' => 'CSC/2025/001',
        'entry_level' => 400,
    ]);
});

test('assign placement supervisor action assigns supervisor with multi tier scoping', function () {
    $action = new AssignPlacementSupervisorAction;

    $assignment = $action->execute(
        institutionId: $this->institution->id,
        sessionId: $this->session->id,
        organizationId: $this->organization->id,
        userId: $this->lecturer->id,
        assignedBy: $this->admin->id,
        departmentId: $this->department->id,
        programId: $this->program->id,
        level: '400',
        notes: 'Lead CS Supervisor'
    );

    expect($assignment)->toBeInstanceOf(PlacementSupervisor::class);
    expect($assignment->user_id)->toBe($this->lecturer->id);
    expect($assignment->organization_id)->toBe($this->organization->id);
    expect($assignment->department_id)->toBe($this->department->id);
    expect($assignment->level)->toBe('400');
});

test('placement supervisor resolver resolves correct supervisor for student placement', function () {
    $action = new AssignPlacementSupervisorAction;
    $action->execute(
        institutionId: $this->institution->id,
        sessionId: $this->session->id,
        organizationId: $this->organization->id,
        userId: $this->lecturer->id,
        assignedBy: $this->admin->id,
        departmentId: $this->department->id,
        level: '400'
    );

    $placement = StudentPlacement::create([
        'student_id' => $this->student->id,
        'organization_id' => $this->organization->id,
        'placement_type_id' => $this->placementType->id,
        'start_date' => now(),
        'end_date' => now()->addMonths(6),
        'academic_session' => $this->session->id,
        'status' => 'Assigned',
        'workflow_stage' => 'Posting_Issued',
    ]);

    $resolver = new PlacementSupervisorResolver;
    $resolved = $resolver->resolveForPlacement($placement);

    expect($resolved)->not()->toBeNull();
    expect($resolved->user_id)->toBe($this->lecturer->id);
});

test('submit placement evaluation action calculates total score and performance grade', function () {
    $placement = StudentPlacement::create([
        'student_id' => $this->student->id,
        'organization_id' => $this->organization->id,
        'placement_type_id' => $this->placementType->id,
        'start_date' => now(),
        'end_date' => now()->addMonths(6),
        'academic_session' => $this->session->id,
        'status' => 'Assigned',
        'workflow_stage' => 'Posting_Issued',
    ]);

    $action = new SubmitPlacementEvaluationAction;
    $evaluation = $action->execute(
        placement: $placement,
        supervisorId: $this->lecturer->id,
        punctuality: 5,
        attendance: 4,
        conduct: 5,
        technical: 4,
        logbook: 5,
        remarks: 'Outstanding performance throughout the attachment period.'
    );

    // Total score sum = 5+4+5+4+5 = 23 -> (23/25)*100 = 92%
    expect($evaluation->total_score)->toBe(92.0);
    expect($evaluation->performance_grade)->toBe('A');
    expect($evaluation->supervisor_remarks)->toBe('Outstanding performance throughout the attachment period.');
});

test('admin placement supervisors component can assign supervisor', function () {
    Livewire::actingAs($this->admin)
        ->test('admin.placements.supervisors')
        ->set('academic_session_id', (string) $this->session->id)
        ->set('organization_id', (string) $this->organization->id)
        ->set('department_id', (string) $this->department->id)
        ->set('program_id', (string) $this->program->id)
        ->set('level', '400')
        ->set('user_id', (string) $this->lecturer->id)
        ->call('saveSupervisor')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('placement_supervisors', [
        'organization_id' => $this->organization->id,
        'user_id' => $this->lecturer->id,
        'level' => '400',
    ]);
});

test('lecturer my supervisions component allows evaluating assigned student', function () {
    $action = new AssignPlacementSupervisorAction;
    $action->execute(
        institutionId: $this->institution->id,
        sessionId: $this->session->id,
        organizationId: $this->organization->id,
        userId: $this->lecturer->id,
        assignedBy: $this->admin->id
    );

    $placement = StudentPlacement::create([
        'student_id' => $this->student->id,
        'organization_id' => $this->organization->id,
        'placement_type_id' => $this->placementType->id,
        'start_date' => now(),
        'end_date' => now()->addMonths(6),
        'academic_session' => $this->session->id,
        'status' => 'Assigned',
        'workflow_stage' => 'Posting_Issued',
    ]);

    Livewire::actingAs($this->lecturer)
        ->test('lecturer.placements.my-supervisions')
        ->call('openEvalModal', $placement->id)
        ->set('punctuality_rating', 5)
        ->set('attendance_rating', 5)
        ->set('conduct_discipline_rating', 5)
        ->set('technical_skills_rating', 5)
        ->set('logbook_maintenance_rating', 5)
        ->set('supervisor_remarks', 'Flawless evaluation')
        ->call('submitEvaluation')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('placement_evaluations', [
        'placement_id' => $placement->id,
        'student_id' => $this->student->id,
        'supervisor_id' => $this->lecturer->id,
        'total_score' => 100.0,
        'performance_grade' => 'A',
    ]);
});

test('placement supervisor populates polymorphic supervisable relationship', function () {
    $action = new AssignPlacementSupervisorAction;
    $assignment = $action->execute(
        institutionId: $this->institution->id,
        sessionId: $this->session->id,
        organizationId: $this->organization->id,
        userId: $this->lecturer->id,
        assignedBy: $this->admin->id,
        departmentId: $this->department->id
    );

    expect($assignment->supervisable_type)->toBe(Department::class);
    expect($assignment->supervisable_id)->toBe($this->department->id);
    expect($assignment->supervisable)->toBeInstanceOf(Department::class);
});

test('multi tenancy prevents admin from institution A seeing data from institution B', function () {
    $instB = Institution::create(['name' => 'Polytechnic B', 'code' => 'POLYB']);
    $adminB = User::factory()->create(['institution_id' => $instB->id]);
    $deptB = Department::create(['name' => 'Mechanical Eng', 'code' => 'MECH', 'institution_id' => $instB->id]);

    $this->admin->update(['institution_id' => $this->institution->id]);

    Livewire::actingAs($this->admin)
        ->test('admin.placements.supervisors')
        ->assertDontSee('Mechanical Eng');
});

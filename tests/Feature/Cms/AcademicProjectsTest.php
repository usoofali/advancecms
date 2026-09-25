<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\ProjectSession;
use App\Models\ProjectSessionStage;
use App\Models\ProjectSubmission;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->institution = Institution::create(['name' => 'College of Health Sciences', 'acronym' => 'CHS']);
    $this->academicSession = AcademicSession::create([
        'name' => '2026/2027',
        'status' => 'active',
    ]);

    $this->department = Department::create([
        'institution_id' => $this->institution->id,
        'name' => 'Community Health',
        'code' => 'CHE',
    ]);

    $this->program2Yr = Program::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'name' => 'Diploma in Community Health',
        'acronym' => 'DCH',
        'duration_years' => 2,
    ]);

    $this->program3Yr = Program::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'name' => 'Higher Diploma in CHEW',
        'acronym' => 'HDCH',
        'duration_years' => 3,
    ]);

    $this->adminUser = User::factory()->create(['institution_id' => $this->institution->id]);

    $this->staff = Staff::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'first_name' => 'Dr. Paul',
        'last_name' => 'Supervisor',
        'email' => 'paul.supervisor@example.com',
        'staff_number' => 'STF/2026/001',
        'designation' => 'Senior Lecturer',
    ]);

    $this->supervisorUser = User::where('email', 'paul.supervisor@example.com')->first();

    $this->projectSession = ProjectSession::create([
        'institution_id' => $this->institution->id,
        'academic_session_id' => $this->academicSession->id,
        'title' => '2026/2027 Project Session',
        'coordinator_id' => $this->staff->id,
        'status' => 'Active',
    ]);

    $this->stage1 = ProjectSessionStage::create([
        'project_session_id' => $this->projectSession->id,
        'title' => 'Project Proposal',
        'stage_order' => 1,
    ]);

    $this->stage2 = ProjectSessionStage::create([
        'project_session_id' => $this->projectSession->id,
        'title' => 'Chapter One',
        'stage_order' => 2,
    ]);
});

it('verifies student project eligibility based on program duration and final year level', function () {
    // 2-year program: admission 2025 in 2026/2027 session = 200 Level -> ELIGIBLE
    $eligibleStudent = Student::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'first_name' => 'Eligible',
        'last_name' => 'Student',
        'email' => 'eligible.student@example.com',
        'admission_year' => 2025,
        'entry_level' => 100,
    ]);

    // 2-year program: admission 2026 in 2026/2027 session = 100 Level -> INELIGIBLE
    $ineligibleStudent = Student::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'first_name' => 'Ineligible',
        'last_name' => 'Student',
        'email' => 'ineligible.student@example.com',
        'admission_year' => 2026,
        'entry_level' => 100,
    ]);

    $checkEligible = StudentProject::checkEligibility($eligibleStudent, $this->academicSession);
    $checkIneligible = StudentProject::checkEligibility($ineligibleStudent, $this->academicSession);

    expect($checkEligible['eligible'])->toBeTrue()
        ->and($checkEligible['current_level'])->toBe(200);

    expect($checkIneligible['eligible'])->toBeFalse()
        ->and($checkIneligible['current_level'])->toBe(100);
});

it('allows student self-registration when eligible for active session', function () {
    $eligibleStudent = Student::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'first_name' => 'SelfReg',
        'last_name' => 'Student',
        'email' => 'selfreg.student@example.com',
        'admission_year' => 2025,
        'entry_level' => 100,
    ]);

    $studentUser = User::where('email', $eligibleStudent->email)->first();

    Livewire::actingAs($studentUser)
        ->test('student.projects.index')
        ->call('registerSelf');

    $this->assertDatabaseHas('student_projects', [
        'student_id' => $eligibleStudent->id,
        'project_session_id' => $this->projectSession->id,
        'overall_status' => 'Topic Pending',
    ]);
});

it('allows admin to manage project sessions and stages', function () {
    Livewire::actingAs($this->adminUser)
        ->test('admin.projects.sessions')
        ->set('title', '2027/2028 Project Session')
        ->set('academic_session_id', $this->academicSession->id)
        ->set('status', 'Active')
        ->call('saveSession');

    $this->assertDatabaseHas('project_sessions', [
        'title' => '2027/2028 Project Session',
        'institution_id' => $this->institution->id,
    ]);
});

it('handles student topic submission and coordinator approval', function () {
    $student = Student::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'first_name' => 'Topic',
        'last_name' => 'Student',
        'email' => 'topic.student@example.com',
        'admission_year' => 2025,
        'entry_level' => 100,
    ]);

    $studentUser = User::where('email', $student->email)->first();

    $project = StudentProject::create([
        'institution_id' => $this->institution->id,
        'project_session_id' => $this->projectSession->id,
        'student_id' => $student->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'current_stage_id' => $this->stage1->id,
        'overall_status' => 'Topic Pending',
    ]);

    // Student submits 2 topics
    Livewire::actingAs($studentUser)
        ->test('student.projects.index')
        ->set('topic_1', 'AI in Community Health Assessment')
        ->set('desc_1', 'Study on rural clinics')
        ->set('topic_2', 'Water Sanitation Practices')
        ->set('desc_2', 'Survey of clean water access')
        ->call('submitTopics');

    expect($project->topics()->count())->toBe(2);

    $topic1 = $project->topics()->where('topic_order', 1)->first();

    // Coordinator approves Topic 1
    Livewire::actingAs($this->adminUser)
        ->test('admin.projects.manage')
        ->call('openTopicReview', $topic1->id)
        ->set('topic_action', 'Approved')
        ->set('topic_feedback', 'Great study topic. Approved.')
        ->call('processTopicReview');

    $project->refresh();
    $topic1->refresh();

    expect($topic1->status)->toBe('Approved')
        ->and($project->approved_topic_id)->toBe($topic1->id)
        ->and($project->overall_status)->toBe('Topic Approved');
});

it('allows supervisor assignment and chapter review with progress calculation', function () {
    $student = Student::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'first_name' => 'Supervisee',
        'last_name' => 'Student',
        'email' => 'supervisee.student@example.com',
        'admission_year' => 2025,
        'entry_level' => 100,
    ]);

    $project = StudentProject::create([
        'institution_id' => $this->institution->id,
        'project_session_id' => $this->projectSession->id,
        'student_id' => $student->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'current_stage_id' => $this->stage1->id,
        'overall_status' => 'Topic Approved',
    ]);

    // Assign supervisor
    Livewire::actingAs($this->adminUser)
        ->test('admin.projects.manage')
        ->call('openAssignSupervisor', [$project->id])
        ->set('assign_supervisor_id', $this->staff->id)
        ->call('assignSupervisor');

    $project->refresh();
    expect($project->supervisor_id)->toBe($this->staff->id);

    // Create stage submission
    $submission = ProjectSubmission::create([
        'student_project_id' => $project->id,
        'stage_id' => $this->stage1->id,
        'version_number' => 1,
        'file_path' => 'project_submissions/test.pdf',
        'file_original_name' => 'test.pdf',
        'status' => 'Submitted',
    ]);

    // Supervisor reviews and approves stage 1
    Livewire::actingAs($this->supervisorUser)
        ->test('lecturer.projects.my-supervisions')
        ->call('openReview', $submission->id)
        ->set('review_status', 'Approved')
        ->set('supervisor_feedback', 'Well articulated proposal.')
        ->call('submitReview');

    $submission->refresh();
    $project->refresh();

    expect($submission->status)->toBe('Approved')
        ->and($project->progress_percentage)->toBe(50); // 1 out of 2 stages approved = 50%
});

it('supports in-project communication chat messages and unread tracking', function () {
    $student = Student::create([
        'institution_id' => $this->institution->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'first_name' => 'Chat',
        'last_name' => 'Student',
        'email' => 'chat.student@example.com',
        'admission_year' => 2025,
        'entry_level' => 100,
    ]);

    $studentUser = User::where('email', $student->email)->first();

    $project = StudentProject::create([
        'institution_id' => $this->institution->id,
        'project_session_id' => $this->projectSession->id,
        'student_id' => $student->id,
        'department_id' => $this->department->id,
        'program_id' => $this->program2Yr->id,
        'supervisor_id' => $this->staff->id,
        'current_stage_id' => $this->stage1->id,
        'overall_status' => 'In Progress',
    ]);

    // Student sends message
    Livewire::actingAs($studentUser)
        ->test('pages::cms.projects.show', ['project' => $project])
        ->set('chat_message', 'Hello Dr. Paul, I have uploaded the revised draft.')
        ->call('sendMessage');

    $this->assertDatabaseHas('project_messages', [
        'student_project_id' => $project->id,
        'sender_id' => $studentUser->id,
        'message' => 'Hello Dr. Paul, I have uploaded the revised draft.',
    ]);

    // Supervisor opens project and unread messages are marked as read
    expect($project->unreadMessagesCountForUser($this->supervisorUser->id))->toBe(1);

    Livewire::actingAs($this->supervisorUser)
        ->test('pages::cms.projects.show', ['project' => $project]);

    $project->refresh();
    expect($project->unreadMessagesCountForUser($this->supervisorUser->id))->toBe(0);
});

it('filters unregistered students by department using program relationship in manage dashboard', function () {
    $student = Student::create([
        'institution_id' => $this->institution->id,
        'program_id' => $this->program2Yr->id,
        'first_name' => 'Unregistered',
        'last_name' => 'Student',
        'email' => 'unregistered@example.com',
        'admission_year' => 2025,
        'entry_level' => 100,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test('admin.projects.manage')
        ->set('activeTab', 'register')
        ->set('selectedSessionId', $this->projectSession->id)
        ->set('department_id', $this->department->id)
        ->assertSee('Unregistered Student');
});

<?php

use App\Models\AcademicSession;
use App\Models\CaQuestion;
use App\Models\CaQuestionOption;
use App\Models\CaTest;
use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('forbids users without ca_tests.view permission from exporting questions as CSV', function (): void {
    $institution = Institution::factory()->create([
        'addons' => ['exam_module'],
    ]);
    $user = User::factory()
        ->for($institution)
        ->withRole('Student')
        ->create();

    $this->actingAs($user);

    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);
    $session = AcademicSession::factory()->create();
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
    ]);
    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
    ]);

    $test = CaTest::create([
        'institution_id' => $institution->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'title' => 'Sample CA Test',
        'duration_minutes' => 30,
        'is_published' => false,
    ]);

    Livewire::test('pages::cms.ca-tests.lecturer.questions')
        ->set('test_id', $test->id)
        ->call('exportCsv')
        ->assertForbidden();
});

it('allows authorized users to export CA questions as CSV', function (): void {
    $institution = Institution::factory()->create([
        'addons' => ['exam_module'],
    ]);
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);
    $session = AcademicSession::factory()->create();
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
    ]);
    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
    ]);

    $test = CaTest::create([
        'institution_id' => $institution->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'title' => 'Sample CA Test',
        'duration_minutes' => 30,
        'is_published' => false,
    ]);

    $question = CaQuestion::create([
        'ca_test_id' => $test->id,
        'text' => 'What is the capital of Nigeria?',
        'marks' => 2.0,
        'coin_reward' => 5,
    ]);

    CaQuestionOption::create([
        'ca_question_id' => $question->id,
        'text' => 'Abuja',
        'is_correct' => true,
    ]);
    CaQuestionOption::create([
        'ca_question_id' => $question->id,
        'text' => 'Lagos',
        'is_correct' => false,
    ]);
    CaQuestionOption::create([
        'ca_question_id' => $question->id,
        'text' => 'Kano',
        'is_correct' => false,
    ]);
    CaQuestionOption::create([
        'ca_question_id' => $question->id,
        'text' => 'Port Harcourt',
        'is_correct' => false,
    ]);

    $user = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::cms.ca-tests.lecturer.questions')
        ->set('test_id', $test->id)
        ->assertOk()
        ->assertSee('Export CSV')
        ->call('exportCsv')
        ->assertFileDownloaded();

    $download = data_get($component->effects, 'download');
    expect($download)->not->toBeNull();
    expect(data_get($download, 'name'))->toContain('ca_questions_sample-ca-test_');

    $content = base64_decode(data_get($download, 'content'));
    expect($content)->toContain('"Question Text","Option 1","Option 2","Option 3","Option 4","Correct Index",Marks,Coins');
    expect($content)->toContain('"What is the capital of Nigeria?",Abuja,Lagos,Kano,"Port Harcourt",1,2.00,5');
});

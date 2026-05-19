<?php

use App\Models\AcademicSession;
use App\Models\CbtExam;
use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('forbids users without cbt_questions.view permission from accessing the page', function (): void {
    $institution = Institution::factory()->create([
        'addons' => ['exam_module'],
    ]);
    $user = User::factory()
        ->for($institution)
        ->withRole('Student')
        ->create();

    $this->actingAs($user);

    $this->get(route('cms.cbt.questions'))->assertForbidden();
});

it('allows institutional admins to view the CBT questions page and list exams', function (): void {
    $institution = Institution::factory()->create([
        'addons' => ['exam_module'],
    ]);
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
        'acronym' => 'CSC',
    ]);
    $session = AcademicSession::factory()->create();
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'first',
    ]);
    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'CSC101',
    ]);

    $exam = CbtExam::create([
        'uuid' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'title' => 'CSC101 Exam Title',
        'duration_minutes' => 60,
        'total_questions' => 50,
        'pass_mark' => 40.00,
        'status' => 'draft',
    ]);

    $user = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    $this->get(route('cms.cbt.questions'))->assertSuccessful();

    Livewire::test('pages::cms.cbt.questions')
        ->assertOk()
        ->assertSee('CSC101 Exam Title')
        ->assertSee('CSC101')
        ->assertSee('CSC');
});

it('can import questions from a CSV file with non-UTF-8 characters', function (): void {
    $institution = Institution::factory()->create([
        'addons' => ['exam_module'],
    ]);
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
        'acronym' => 'CSC',
    ]);
    $session = AcademicSession::factory()->create();
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'first',
    ]);
    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'CSC101',
    ]);

    $exam = CbtExam::create([
        'uuid' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'title' => 'CSC101 Exam Title',
        'duration_minutes' => 60,
        'total_questions' => 50,
        'pass_mark' => 40.00,
        'status' => 'draft',
    ]);

    $user = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    $this->actingAs($user);

    // Create a CSV string containing Windows-1252 encoded characters
    // 0x92 is the Windows-1252 right single quotation mark / apostrophe
    $csvContent = "Question Text,Opt1,Opt2,Opt3,Opt4,Correct Index,Marks\n".
        'Admission and discharge registers contain the patient'.chr(0x92).'s ______.,Name,Age,Address,None of the above,1,1';

    $file = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

    Livewire::test('pages::cms.cbt.questions')
        ->set('selectedExamId', $exam->id)
        ->set('csvFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    // Verify the question was created and the apostrophe was correctly converted to UTF-8
    $this->assertDatabaseHas('cbt_questions', [
        'cbt_exam_id' => $exam->id,
        'question_text' => 'Admission and discharge registers contain the patient’s ______.',
    ]);
});

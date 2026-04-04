<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function seedResultsFixture(): array
{
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'institution_id' => $institution->id,
    ]);
    $session = AcademicSession::factory()->create();
    $semester = Semester::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'first',
    ]);
    $courseA = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'CSC101',
    ]);
    $courseB = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'level' => 100,
        'semester' => 1,
        'course_code' => 'MTH101',
    ]);
    $student = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'email' => null,
    ]);

    Result::query()->create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'course_id' => $courseA->id,
        'academic_session_id' => $session->id,
        'semester_id' => $semester->id,
        'ca_score' => 20,
        'exam_score' => 55,
        'total_score' => 75,
        'grade' => 'B',
        'grade_point' => 3.0,
        'remark' => 'pass',
    ]);

    return [
        'institution' => $institution,
        'department' => $department,
        'program' => $program,
        'session' => $session,
        'semester' => $semester,
        'courseA' => $courseA,
        'courseB' => $courseB,
        'student' => $student,
    ];
}

it('allows users with permission to view the results index', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    $this->get(route('cms.results.index'))->assertSuccessful();
});

it('does not load the results table until a semester is selected', function (): void {
    $f = seedResultsFixture();
    $user = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::cms.results.index')
        ->assertSet('semester_id', '')
        ->assertSee(__('Choose session, level, and semester to load results.'), false);
});

it('shows semester matrix with course columns and pass or fail counts', function (): void {
    $f = seedResultsFixture();
    $user = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::cms.results.index')
        ->set('department_id', (string) $f['department']->id)
        ->set('program_id', (string) $f['program']->id)
        ->set('session_id', (string) $f['session']->id)
        ->set('level', '100')
        ->set('semester_id', (string) $f['semester']->id)
        ->assertSee('CSC101', false)
        ->assertSee('MTH101', false)
        ->assertSee($f['student']->matric_number, false)
        ->assertSee('75/B', false)
        ->assertSee(__('Passes'), false);
});

it('shows single-course columns when a course is selected', function (): void {
    $f = seedResultsFixture();
    $user = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::cms.results.index')
        ->set('department_id', (string) $f['department']->id)
        ->set('program_id', (string) $f['program']->id)
        ->set('session_id', (string) $f['session']->id)
        ->set('level', '100')
        ->set('semester_id', (string) $f['semester']->id)
        ->set('course_id', (string) $f['courseA']->id)
        ->assertSee(__('CA'), false)
        ->assertSee(__('Exam'), false)
        ->assertSee('20', false)
        ->assertSee('55', false);
});

it('downloads filtered matrix csv when semester is selected', function (): void {
    $f = seedResultsFixture();
    $user = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $response = $this->actingAs($user)->get(route('cms.results.export.csv', [
        'department_id' => $f['department']->id,
        'program_id' => $f['program']->id,
        'session_id' => $f['session']->id,
        'level' => 100,
        'semester_id' => $f['semester']->id,
    ]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('matric_number')
        ->and($response->streamedContent())->toContain('CSC101')
        ->and($response->streamedContent())->toContain('passes');
});

it('downloads course csv when course_id is set', function (): void {
    $f = seedResultsFixture();
    $user = User::factory()
        ->for($f['institution'])
        ->withRole('Institutional Admin')
        ->create();

    $response = $this->actingAs($user)->get(route('cms.results.export.csv', [
        'department_id' => $f['department']->id,
        'program_id' => $f['program']->id,
        'session_id' => $f['session']->id,
        'level' => 100,
        'semester_id' => $f['semester']->id,
        'course_id' => $f['courseA']->id,
    ]));

    $response->assertSuccessful();
    expect($response->streamedContent())->toContain('ca_score')
        ->and($response->streamedContent())->toContain('CSC101');
});

it('rejects csv export without semester', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user)->get(route('cms.results.export.csv', [
        'session_id' => AcademicSession::factory()->create()->id,
    ]))->assertStatus(400);
});

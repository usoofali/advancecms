<?php

use App\Imports\CoursesImport;
use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

test('creating a course updates existing course if institution, department, program, course_code, and semester match', function () {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($institution)->for($department)->create();

    $existingCourse = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'course_code' => 'CSC101',
        'title' => 'OLD TITLE',
        'credit_unit' => 2,
        'level' => 100,
        'semester' => 1,
    ]);

    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$superRole->role_id]);

    Livewire::actingAs($user)
        ->test('pages::cms.courses.create')
        ->set('institution_id', $institution->id)
        ->set('department_id', $department->id)
        ->set('program_id', $program->id)
        ->set('course_code', 'CSC101')
        ->set('title', 'NEW UPDATED TITLE')
        ->set('credit_unit', 3)
        ->set('course_type', 'core')
        ->set('level', 100)
        ->set('semester', 1)
        ->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    expect(Course::where('institution_id', $institution->id)->where('course_code', 'CSC101')->count())->toBe(1);

    $existingCourse->refresh();
    expect($existingCourse->title)->toBe('NEW UPDATED TITLE');
    expect($existingCourse->credit_unit)->toBe(3);
});

test('importing a course updates existing course matching institution, department, program, course_code, and semester', function () {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'acronym' => 'CS',
    ]);

    $course = Course::factory()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'course_code' => 'MTH101',
        'title' => 'INITIAL TITLE',
        'credit_unit' => 2,
        'semester' => 1,
    ]);

    // Create temporary CSV file for import test
    $csvContent = "course_code,title,credit_unit,level,semester,program_acronym,course_type,status\n";
    $csvContent .= "MTH101,UPDATED MATH TITLE,4,100,1,CS,core,active\n";

    $tmpFilePath = sys_get_temp_dir().'/test_courses_import.csv';
    file_put_contents($tmpFilePath, $csvContent);

    $importer = new CoursesImport($institution->id);
    $importer->import($tmpFilePath);

    @unlink($tmpFilePath);

    expect($importer->imported)->toBe(1);
    expect(Course::where('institution_id', $institution->id)->where('course_code', 'MTH101')->count())->toBe(1);

    $course->refresh();
    expect($course->title)->toBe('UPDATED MATH TITLE');
    expect($course->credit_unit)->toBe(4);
});

test('a course with 0 credit units can be created, updated, and imported', function () {
    $institution = Institution::factory()->create();
    $department = Department::factory()->for($institution)->create();
    $program = Program::factory()->for($institution)->for($department)->create(['acronym' => 'GST']);

    $superRole = Role::where('role_name', 'Super Admin')->first();
    $user = User::factory()->create([
        'institution_id' => $institution->id,
    ]);
    $user->roles()->sync([$superRole->role_id]);

    // 1. Create 0 unit course via Livewire
    Livewire::actingAs($user)
        ->test('pages::cms.courses.create')
        ->set('institution_id', $institution->id)
        ->set('department_id', $department->id)
        ->set('program_id', $program->id)
        ->set('course_code', 'GST101')
        ->set('title', 'USE OF ENGLISH')
        ->set('credit_unit', 0)
        ->set('course_type', 'core')
        ->set('level', 100)
        ->set('semester', 1)
        ->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    $createdCourse = Course::where('institution_id', $institution->id)->where('course_code', 'GST101')->first();
    expect($createdCourse)->not->toBeNull();
    expect($createdCourse->credit_unit)->toBe(0);

    // 2. Edit 0 unit course via Livewire
    Livewire::actingAs($user)
        ->test('pages::cms.courses.edit', ['course' => $createdCourse])
        ->set('credit_unit', 0)
        ->set('title', 'USE OF ENGLISH I')
        ->call('save')
        ->assertHasNoErrors();

    $createdCourse->refresh();
    expect($createdCourse->title)->toBe('USE OF ENGLISH I');
    expect($createdCourse->credit_unit)->toBe(0);

    // 3. Import 0 unit course via CSV
    $csvContent = "course_code,title,credit_unit,level,semester,program_acronym,course_type,status\n";
    $csvContent .= "GST102,CITIZENSHIP EDUCATION,0,100,2,GST,core,active\n";

    $tmpFilePath = sys_get_temp_dir().'/test_zero_unit_import.csv';
    file_put_contents($tmpFilePath, $csvContent);

    $importer = new CoursesImport($institution->id);
    $importer->import($tmpFilePath);
    @unlink($tmpFilePath);

    expect($importer->imported)->toBe(1);
    $importedCourse = Course::where('institution_id', $institution->id)->where('course_code', 'GST102')->first();
    expect($importedCourse)->not->toBeNull();
    expect($importedCourse->credit_unit)->toBe(0);
});

test('course import handles header variations and missing header columns gracefully without throwing exception', function () {
    $institution = Institution::factory()->create();

    // CSV file missing course_code header entirely
    $csvContent = "Title,Credit Unit,Level,Semester,Program Acronym\n";
    $csvContent .= "SOME COURSE,2,100,1,CS\n";

    $tmpFilePath = sys_get_temp_dir().'/test_missing_header_import.csv';
    file_put_contents($tmpFilePath, $csvContent);

    $importer = new CoursesImport($institution->id);
    $importer->import($tmpFilePath);
    @unlink($tmpFilePath);

    expect($importer->imported)->toBe(0);
    expect($importer->failures)->not->toBeEmpty();
    expect($importer->failures[0])->toContain('Missing required fields: course_code');
});

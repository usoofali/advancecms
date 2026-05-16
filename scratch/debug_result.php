<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Result;
use App\Models\Student;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$matric = 'GUS/CHEW/2023/012';
$code = 'CHE315';

$student = Student::where('matric_number', $matric)->first();
$course = Course::where('course_code', $code)->first();

if (! $student) {
    echo "Student not found: $matric\n";
    exit;
}

if (! $course) {
    echo "Course not found: $code\n";
    exit;
}

$result = Result::where('student_id', $student->id)->where('course_id', $course->id)->first();
$registration = CourseRegistration::where('student_id', $student->id)->where('course_id', $course->id)->get();

echo 'Student ID: '.$student->id."\n";
echo 'Course ID: '.$course->id."\n";
echo 'Result Found: '.($result ? 'Yes (Session: '.$result->academic_session_id.', Semester: '.$result->semester_id.')' : 'No')."\n";
echo 'Registrations Found: '.$registration->count()."\n";

$sess = AcademicSession::find(3);
$levelCalc = $student->currentLevel($sess);
$inScope = Student::where('id', $student->id)->atLevel(300, $sess)->exists();

echo "Level Calculated: $levelCalc\n";
echo 'In 300 Level Scope: '.($inScope ? 'Yes' : 'No')."\n";

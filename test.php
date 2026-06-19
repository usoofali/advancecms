<?php

use App\Models\Student;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$student = Student::find(2);
echo 'Before: '.$student->status."\n";
$student->update(['status' => 'suspended']);
echo 'After: '.$student->status."\n";

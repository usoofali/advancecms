<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$student = App\Models\Student::find(2);
echo "Before: " . $student->status . "\n";
$student->update(['status' => 'suspended']);
echo "After: " . $student->status . "\n";

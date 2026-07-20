<?php

use App\Models\Student;
use App\Models\User;

$student = Student::factory()->create(['email' => 'test_delete_observer@example.com']);
echo 'Student created: '.$student->id."\n";
echo 'User created? '.(User::where('email', 'test_delete_observer@example.com')->exists() ? 'Yes' : 'No')."\n";
try {
    $student->delete();
    echo 'Student deleted!'."\n";
} catch (Exception $e) {
    echo 'Exception: '.$e->getMessage()."\n";
}
echo 'Student exists? '.(Student::where('id', $student->id)->exists() ? 'Yes' : 'No')."\n";
echo 'User exists? '.(User::where('email', 'test_delete_observer@example.com')->exists() ? 'Yes' : 'No')."\n";

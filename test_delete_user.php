<?php

use App\Models\Student;
use App\Models\User;

$student = Student::factory()->create(['email' => 'test_delete_user@example.com']);
$user = User::where('email', 'test_delete_user@example.com')->first();
try {
    $user->delete();
    echo 'User deleted!'."\n";
} catch (Exception $e) {
    echo 'Exception: '.$e->getMessage()."\n";
}
echo 'Student exists? '.(Student::where('id', $student->id)->exists() ? 'Yes' : 'No')."\n";
echo 'User exists? '.(User::where('email', 'test_delete_user@example.com')->exists() ? 'Yes' : 'No')."\n";

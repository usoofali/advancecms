<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Livewire\Livewire;
use App\Models\Student;
use App\Models\User;

$user = User::whereHas('roles', fn($q) => $q->where('role_name', 'Super Admin'))->first();
auth()->login($user);

$student = Student::first();
dump("Before: " . $student->status);

Livewire::test('pages::cms.students.show', ['student' => $student])
    ->set('newStatus', 'graduated')
    ->call('updateStatus');

$student->refresh();
dump("After: " . $student->status);

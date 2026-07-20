<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::whereHas('roles', function($q) { $q->where('roles.role_id', 2); })->first();

$data = [
    'phone' => '1234567890',
    'gender' => null,
    'date_of_birth' => null,
    'bank_name' => null,
    'account_number' => null,
    'account_name' => null,
    'signature_path' => 'signatures/test1234.png',
];

try {
    $user->staff->update($data);
    echo "Update successful.\n";
    echo "Signature path is now: " . $user->staff->fresh()->signature_path . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Livewire\Settings\Profile;
use Livewire\Livewire;

$user = User::whereHas('roles', function($q) { $q->where('roles.role_id', 2); })->first();
Auth::login($user);

// Let's create a dummy component and call updateProfile
Livewire::test(Profile::class)
    ->set('phone', '08012345678')
    ->set('gender', 'male')
    ->set('signature_data', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
    ->call('updateProfile')
    ->assertHasNoErrors();

echo "Staff signature path: " . $user->staff->fresh()->signature_path . "\n";

<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('livewire can process temporary file upload', function () {
    Storage::fake('public');
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg');

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('photo', $file)
        ->assertSet('photo', function ($uploaded) {
            return $uploaded !== null;
        });
});

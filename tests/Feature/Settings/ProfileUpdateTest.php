<?php

use App\Livewire\Settings\Profile;
use App\Models\Institution;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('staff can load and update gender and date of birth on profile settings', function () {
    $this->seed(RbacSeeder::class);

    $institution = Institution::factory()->create();
    $staff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'email' => 'staff.profile@example.com',
        'gender' => 'Female',
        'date_of_birth' => '1990-05-14',
        'phone' => '08012345678',
    ]);

    $user = User::where('email', 'staff.profile@example.com')->firstOrFail();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertSet('gender', 'female')
        ->assertSet('date_of_birth', '1990-05-14')
        ->set('gender', 'male')
        ->set('date_of_birth', '1992-08-20')
        ->call('updateProfile')
        ->assertHasNoErrors();

    $staff->refresh();
    expect($staff->gender)->toBe('male');
    expect($staff->date_of_birth?->format('Y-m-d'))->toBe('1992-08-20');
});

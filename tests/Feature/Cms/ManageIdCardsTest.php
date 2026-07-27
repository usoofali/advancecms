<?php

use App\Livewire\Cms\IdCards\ManageIdCards;
use App\Models\IdCardRequest;
use App\Models\Institution;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('can render manage id cards component for authorized institutional admin', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $this->actingAs($user);

    Livewire::test(ManageIdCards::class)
        ->assertSuccessful();
});

it('can render manage id cards component for super admin', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    $this->actingAs($user);

    Livewire::test(ManageIdCards::class)
        ->assertSuccessful();
});

it('renders safely when id card request user has null student or staff profile', function (): void {
    $institution = Institution::factory()->create();
    $admin = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    $userWithoutProfile = User::factory()
        ->for($institution)
        ->create();

    IdCardRequest::create([
        'institution_id' => $institution->id,
        'user_id' => $userWithoutProfile->id,
        'type' => 'student',
        'reason' => 'first_issue',
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test(ManageIdCards::class)
        ->assertSuccessful()
        ->assertSee($userWithoutProfile->name);
});

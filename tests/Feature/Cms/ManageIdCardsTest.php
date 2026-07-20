<?php

use App\Livewire\Cms\IdCards\ManageIdCards;
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

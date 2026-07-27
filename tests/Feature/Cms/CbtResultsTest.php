<?php

use App\Models\Institution;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('renders cbt results review page for authorized users', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Super Admin')
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::cms.cbt.results')
        ->assertStatus(200)
        ->assertSee(__('Results & Analytics'))
        ->assertSee(__('Program'))
        ->assertSee(__('Course Level'));
});

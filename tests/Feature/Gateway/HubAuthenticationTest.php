<?php

use App\Livewire\Hub\HubLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('gateway.role', 'hub');
    Config::set('gateway.hub.admin.username', 'admin');
    Config::set('gateway.hub.admin.password', 'secret_password_123');
});

it('redirects unauthenticated hub requests to hub login page', function () {
    $response = $this->get('/api/hub/analytics');

    $response->assertRedirect('/api/hub/login');
});

it('shows validation error on invalid hub credentials', function () {
    Livewire::test(HubLogin::class)
        ->set('username', 'admin')
        ->set('password', 'wrong_password')
        ->call('login')
        ->assertHasErrors(['username']);

    expect(session('hub_authenticated'))->toBeNull();
});

it('authenticates hub session with valid credentials', function () {
    Livewire::test(HubLogin::class)
        ->set('username', 'admin')
        ->set('password', 'secret_password_123')
        ->call('login')
        ->assertRedirect(route('hub.analytics'));

    expect(session('hub_authenticated'))->toBeTrue();
});

it('allows authenticated hub user to access central analytics dashboard', function () {
    $response = $this->withSession(['hub_authenticated' => true])
        ->get('/api/hub/analytics');

    $response->assertStatus(200);
});

it('clears session on hub logout', function () {
    $response = $this->withSession(['hub_authenticated' => true])
        ->post('/api/hub/logout');

    $response->assertRedirect('/api/hub/login');
    expect(session('hub_authenticated'))->toBeNull();
});

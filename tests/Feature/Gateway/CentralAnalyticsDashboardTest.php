<?php

use App\Livewire\Admin\CentralAnalyticsDashboard;
use App\Models\Hub\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('gateway.role', 'hub');
    Tenant::query()->delete();
});

it('can register a new tenant campus', function () {
    Livewire::test(CentralAnalyticsDashboard::class)
        ->call('openNewTenantModal')
        ->set('tenantName', 'College of Technology')
        ->set('tenantCode', 'COTECH')
        ->set('tenantDbHost', '127.0.0.1')
        ->set('tenantDbPort', '3306')
        ->set('tenantDbDatabase', 'cotech_db')
        ->set('tenantDbUsername', 'cotech_user')
        ->set('tenantDbPassword', 'cotech_pass')
        ->set('tenantStatus', 'active')
        ->call('saveTenant')
        ->assertHasNoErrors();

    $tenant = Tenant::where('code', 'COTECH')->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->name)->toBe('College of Technology');
    expect($tenant->db_database)->toBe('cotech_db');
});

it('can edit an existing tenant campus', function () {
    $tenant = Tenant::create([
        'name' => 'Original Campus',
        'code' => 'ORIGINAL',
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => 'original_db',
        'db_username' => 'root',
        'db_password' => '',
        'status' => 'active',
    ]);

    Livewire::test(CentralAnalyticsDashboard::class)
        ->call('editTenant', $tenant->id)
        ->assertSet('tenantName', 'Original Campus')
        ->set('tenantName', 'Updated Campus Name')
        ->call('saveTenant')
        ->assertHasNoErrors();

    $tenant->refresh();
    expect($tenant->name)->toBe('Updated Campus Name');
});

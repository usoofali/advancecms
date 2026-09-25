<?php

use App\Models\Hub\Tenant;
use App\Models\Hub\TenantDailyMetric;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('gateway.role', 'standalone');
    TenantDailyMetric::query()->delete();
    Tenant::query()->delete();
});

it('harvests metrics directly from tenant database via direct connection', function () {
    Event::fake();

    $institution = Institution::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Shared Hosting Campus',
        'code' => 'SHAREDDB',
        'institution_id' => $institution->id,
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => database_path('testing.sqlite'),
        'db_username' => 'root',
        'db_password' => 'secret',
        'status' => 'active',
    ]);

    $program = Program::factory()->create(['institution_id' => $institution->id]);

    Student::factory()->count(2)->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'status' => 'active',
    ]);
    Staff::factory()->create([
        'institution_id' => $institution->id,
    ]);

    $exitCode = Artisan::call('analytics:harvest-metrics');

    expect($exitCode)->toBe(0);

    $metric = TenantDailyMetric::where('tenant_id', $tenant->id)
        ->whereDate('metric_date', today())
        ->first();

    expect($metric)->not->toBeNull();
    expect($metric->total_students)->toBeGreaterThanOrEqual(2);
    expect($metric->raw_payload['mode'] ?? null)->toBe('direct_db');
});

it('handles direct database harvest failure gracefully', function () {
    $tenant = Tenant::create([
        'name' => 'Broken DB Campus',
        'code' => 'BROKENDB',
        'db_host' => 'invalid_host',
        'db_port' => '9999',
        'db_database' => 'non_existent_db',
        'db_username' => 'invalid_user',
        'db_password' => 'invalid_pass',
        'status' => 'active',
    ]);

    $exitCode = Artisan::call('analytics:harvest-metrics');

    expect($exitCode)->toBe(0);

    $metric = TenantDailyMetric::where('tenant_id', $tenant->id)
        ->whereDate('metric_date', today())
        ->first();

    expect($metric)->not->toBeNull();
    expect($metric->raw_payload['status'] ?? null)->toBe('failed');
});

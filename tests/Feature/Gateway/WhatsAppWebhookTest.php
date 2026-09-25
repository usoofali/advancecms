<?php

use App\Models\Hub\Tenant;
use App\Models\Hub\TenantUserCache;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('gateway.role', 'hub');
    Config::set('gateway.hub.whatsapp.verify_token', 'custom_verify_token_123');
    Config::set('gateway.hub.whatsapp.token', 'test_wa_token');
    Config::set('gateway.hub.whatsapp.phone_number_id', '109123456');
    Tenant::query()->delete();
    TenantUserCache::query()->delete();
});

it('verifies meta whatsapp webhook challenge', function () {
    $response = $this->get('/api/hub/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=custom_verify_token_123&hub_challenge=CHALLENGE_STRING');

    $response->assertStatus(200)
        ->assertSee('CHALLENGE_STRING');
});

it('rejects invalid meta whatsapp webhook verify token', function () {
    $response = $this->get('/api/hub/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=WRONG_TOKEN&hub_challenge=CHALLENGE_STRING');

    $response->assertStatus(403);
});

it('handles whatsapp incoming webhook message payload', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'contacts' => [], 'messages' => []], 200),
    ]);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => '109123456',
                'changes' => [
                    [
                        'value' => [
                            'messaging_product' => 'whatsapp',
                            'metadata' => [
                                'display_phone_number' => '15550001111',
                                'phone_number_id' => '109123456',
                            ],
                            'messages' => [
                                [
                                    'from' => '2348012345678',
                                    'id' => 'wamid.HBgL',
                                    'timestamp' => '1600000000',
                                    'text' => [
                                        'body' => 'hello',
                                    ],
                                    'type' => 'text',
                                ],
                            ],
                        ],
                        'field' => 'messages',
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/hub/whatsapp/webhook', $payload);

    $response->assertStatus(200)
        ->assertJson(['status' => 'processed']);
});

it('handles whatsapp onboarding and query processing via direct tenant database connection', function () {
    Event::fake();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp'], 200),
    ]);

    $institution = Institution::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Campus Direct DB',
        'code' => 'CAMPUSDIRECT',
        'institution_id' => $institution->id,
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => ':memory:',
        'db_username' => 'root',
        'db_password' => 'secret',
        'status' => 'active',
    ]);
    $program = Program::factory()->create(['institution_id' => $institution->id]);
    Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'matric_number' => 'STU/2026/0099',
        'status' => 'active',
    ]);

    $onboardingPayload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => '109123456',
                'changes' => [
                    [
                        'value' => [
                            'messaging_product' => 'whatsapp',
                            'metadata' => ['phone_number_id' => '109123456'],
                            'messages' => [
                                [
                                    'from' => '2348099998888',
                                    'id' => 'wamid.ONBOARD',
                                    'timestamp' => '1600000000',
                                    'text' => ['body' => 'CAMPUSDIRECT STU/2026/0099'],
                                    'type' => 'text',
                                ],
                            ],
                        ],
                        'field' => 'messages',
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/hub/whatsapp/webhook', $onboardingPayload);
    $response->assertStatus(200)->assertJson(['status' => 'processed']);

    $cache = TenantUserCache::where('phone_number', '2348099998888')->first();
    expect($cache)->not->toBeNull();
    expect($cache->external_user_id)->toBe('STU/2026/0099');

    $queryPayload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => '109123456',
                'changes' => [
                    [
                        'value' => [
                            'messaging_product' => 'whatsapp',
                            'metadata' => ['phone_number_id' => '109123456'],
                            'messages' => [
                                [
                                    'from' => '2348099998888',
                                    'id' => 'wamid.QUERY',
                                    'timestamp' => '1600000000',
                                    'text' => ['body' => 'check balance'],
                                    'type' => 'text',
                                ],
                            ],
                        ],
                        'field' => 'messages',
                    ],
                ],
            ],
        ],
    ];

    $response2 = $this->postJson('/api/hub/whatsapp/webhook', $queryPayload);
    $response2->assertStatus(200)->assertJson(['status' => 'processed']);
});

it('automatically resolves student or staff by phone number across spoke databases', function () {
    Event::fake();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp'], 200),
    ]);

    $institution = Institution::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Auto Lookup Campus',
        'code' => 'AUTOLUP',
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => ':memory:',
        'db_username' => 'root',
        'db_password' => 'secret',
        'status' => 'active',
    ]);

    $program = Program::factory()->create(['institution_id' => $institution->id]);

    Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'matric_number' => 'STU/AUTOPHONE/001',
        'phone' => '08077665544',
        'status' => 'active',
    ]);

    $autoLookupPayload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => '109123456',
                'changes' => [
                    [
                        'value' => [
                            'messaging_product' => 'whatsapp',
                            'metadata' => ['phone_number_id' => '109123456'],
                            'messages' => [
                                [
                                    'from' => '2348077665544',
                                    'id' => 'wamid.AUTOSEARCH',
                                    'timestamp' => '1600000000',
                                    'text' => ['body' => 'check balance'],
                                    'type' => 'text',
                                ],
                            ],
                        ],
                        'field' => 'messages',
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/hub/whatsapp/webhook', $autoLookupPayload);
    $response->assertStatus(200)->assertJson(['status' => 'processed']);

    $cache = TenantUserCache::where('phone_number', '2348077665544')->first();
    expect($cache)->not->toBeNull();
    expect($cache->external_user_id)->toBe('STU/AUTOPHONE/001');
    expect($cache->tenant_id)->toBe($tenant->id);
    expect($cache->institution_id)->toBe($institution->id);
});

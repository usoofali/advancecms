<?php

use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('can render the vue id card print preview route with payload for authorized institutional admin', function (): void {
    $institution = Institution::factory()->create();
    $user = User::factory()
        ->for($institution)
        ->withRole('Institutional Admin')
        ->create();

    $studentUser = User::factory()->for($institution)->create([
        'name' => 'Jane Student',
        'email' => 'jane.student@example.com',
    ]);

    $student = Student::factory()->for($institution)->create([
        'first_name' => 'Jane',
        'last_name' => 'Student',
        'email' => 'jane.student@example.com',
        'matric_number' => 'ADV/2026/001',
    ]);

    $data = base64_encode(json_encode([
        'ids' => [$student->id],
        'type' => 'student',
        'mode' => 'selected',
    ]));

    $response = $this->actingAs($user)
        ->get(route('cms.id-cards.print', ['data' => $data]))
        ->assertOk()
        ->assertViewIs('pages.cms.id-cards.print-vue')
        ->assertSee('id="id-card-print-app"', false)
        ->assertSee('data-payload="', false);

    expect($response->getContent())
        ->toContain('JANE STUDENT')
        ->toContain('ADV/2026/001');
});

it('forbids unauthorized users from accessing the vue id card print route', function (): void {
    $user = User::factory()->create();

    $data = base64_encode(json_encode([
        'ids' => [1],
        'type' => 'student',
        'mode' => 'selected',
    ]));

    $this->actingAs($user)
        ->get(route('cms.id-cards.print', ['data' => $data]))
        ->assertForbidden();
});

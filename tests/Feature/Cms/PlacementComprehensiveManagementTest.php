<?php

use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\PlacementType;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows admin to reject an organization selection and reverts stage to Pending_Selection with remarks', function () {
    Notification::fake();
    Mail::fake();
    $admin = User::factory()->create();
    $student = Student::factory()->create();
    $type = PlacementType::create(['name' => 'SIWES', 'is_active' => true]);

    $placement = StudentPlacement::create([
        'student_id' => $student->id,
        'placement_type_id' => $type->id,
        'start_date' => now()->addDays(1),
        'end_date' => now()->addMonths(3),
        'academic_session' => '2025/2026',
        'custom_organization_name' => 'Old Clinic',
        'workflow_stage' => 'Pending_Request_Approval',
        'status' => 'Assigned',
    ]);

    Livewire::actingAs($admin)
        ->test('admin.placements.manage')
        ->call('openRejectModal', $placement->id, 'organization')
        ->assertSet('rejectModal', true)
        ->set('rejection_reason', 'Facility not accredited for SIWES')
        ->call('processRejection');

    $placement->refresh();

    expect($placement->workflow_stage)->toBe('Pending_Selection')
        ->and($placement->status)->toBe('Pending')
        ->and($placement->custom_organization_name)->toBeNull()
        ->and($placement->admin_remarks)->toBe('Facility not accredited for SIWES');
});

it('allows admin to cancel and restore a placement', function () {
    Notification::fake();
    Mail::fake();
    $admin = User::factory()->create();
    $student = Student::factory()->create();
    $type = PlacementType::create(['name' => 'SIWES', 'is_active' => true]);

    $placement = StudentPlacement::create([
        'student_id' => $student->id,
        'placement_type_id' => $type->id,
        'start_date' => now()->addDays(1),
        'end_date' => now()->addMonths(3),
        'academic_session' => '2025/2026',
        'workflow_stage' => 'Request_Approved',
        'status' => 'Assigned',
    ]);

    Livewire::actingAs($admin)
        ->test('admin.placements.manage')
        ->call('cancelPlacement', $placement->id);

    $placement->refresh();
    expect($placement->status)->toBe('Cancelled')
        ->and($placement->admin_remarks)->not->toBeNull();

    Livewire::actingAs($admin)
        ->test('admin.placements.manage')
        ->call('restorePlacement', $placement->id);

    $placement->refresh();
    expect($placement->status)->toBe('Assigned')
        ->and($placement->admin_remarks)->toBeNull();
});

it('allows admin to generate group cover letter for an organization', function () {
    Notification::fake();
    Mail::fake();
    $admin = User::factory()->create();
    $student = Student::factory()->create();
    $type = PlacementType::create(['name' => 'SIWES', 'is_active' => true]);
    $org = Organization::create([
        'name' => 'National Hospital',
        'address' => 'Central District',
        'city' => 'Abuja',
        'state' => 'FCT',
    ]);

    DocumentTemplate::create([
        'title' => 'Group Cover Request',
        'type' => 'Group Cover',
        'template_content' => '<p>Please accept {organization_name} {group_table}</p>',
        'active' => true,
    ]);

    $placement = StudentPlacement::create([
        'student_id' => $student->id,
        'placement_type_id' => $type->id,
        'organization_id' => $org->id,
        'start_date' => now()->addDays(1),
        'end_date' => now()->addMonths(3),
        'academic_session' => '2025/2026',
        'workflow_stage' => 'Request_Approved',
        'status' => 'Assigned',
    ]);

    Livewire::actingAs($admin)
        ->test('admin.placements.manage')
        ->call('generateGroupCoverLetterForOrganization', $org->id)
        ->assertRedirect();
});

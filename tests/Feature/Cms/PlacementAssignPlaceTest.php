<?php

use App\Models\PlacementType;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows admin to assign a custom organization place of posting and advances stage to Request_Approved', function () {
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
        'workflow_stage' => 'Pending_Selection',
        'status' => 'Pending',
    ]);

    Livewire::actingAs($admin)
        ->test('admin.placements.manage')
        ->call('openAssignPlaceModal', $placement->id)
        ->assertSet('assignPlaceModal', true)
        ->set('assign_place_mode', 'custom')
        ->set('assign_custom_name', 'St. Nicholas Hospital')
        ->set('assign_custom_address', '57 Campbell Street')
        ->set('assign_custom_city', 'Lagos')
        ->set('assign_custom_state', 'Lagos')
        ->call('saveAssignedPlace');

    $placement->refresh();

    expect($placement->custom_organization_name)->toBe('St. Nicholas Hospital')
        ->and($placement->organization_id)->toBeNull()
        ->and($placement->workflow_stage)->toBe('Request_Approved')
        ->and($placement->status)->toBe('Assigned');
});

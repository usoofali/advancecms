<?php

use App\Models\AcademicSession;
use App\Models\DocumentTemplate;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\PlacementType;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\User;
use App\Services\DocumentGenerationService;
use Database\Seeders\RbacSeeder;
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

it('renders placement management page without query errors for institutional admins', function () {
    $institution = Institution::factory()->create();
    $admin = User::factory()->create(['institution_id' => $institution->id]);
    PlacementType::create(['name' => 'Industrial Training (IT)', 'is_active' => true]);

    Livewire::actingAs($admin)
        ->test('admin.placements.manage')
        ->assertStatus(200)
        ->assertViewHas('types');
});

it('authorizes printing placement documents using permissions and institution scope', function () {
    $this->seed(RbacSeeder::class);

    $institution = Institution::factory()->create();
    $otherInstitution = Institution::factory()->create();

    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $otherAdmin = User::factory()->create(['institution_id' => $otherInstitution->id]);
    $otherAdmin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    $unprivileged = User::factory()->create(['institution_id' => $institution->id]);

    $student = Student::factory()->create(['institution_id' => $institution->id]);
    $type = PlacementType::create(['name' => 'SIWES', 'is_active' => true]);
    $org = Organization::create([
        'name' => 'General Hospital',
        'address' => 'Main Road',
        'city' => 'Abuja',
        'state' => 'FCT',
    ]);

    $template = DocumentTemplate::create([
        'title' => 'Cover Letter',
        'type' => 'Group Cover',
        'template_content' => '<p>Letter for {student_name}</p>',
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

    $doc = app(DocumentGenerationService::class)->generateRecord($placement, $template, 'group_cover');

    $this->actingAs($admin)
        ->get(route('cms.placements.print', ['doc' => $doc->document_number]))
        ->assertSuccessful();

    $this->actingAs($otherAdmin)
        ->get(route('cms.placements.print', ['doc' => $doc->document_number]))
        ->assertForbidden();

    $this->actingAs($unprivileged)
        ->get(route('cms.placements.print', ['doc' => $doc->document_number]))
        ->assertForbidden();
});

it('defaults active academic session in new assignment and bulk assigns only selected students', function () {
    $this->seed(RbacSeeder::class);

    $institution = Institution::factory()->create();
    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->sync([Role::where('role_name', 'Institutional Admin')->value('role_id')]);

    AcademicSession::factory()->create([
        'name' => '2026/2027',
        'status' => 'active',
    ]);

    $program = Program::factory()->create(['institution_id' => $institution->id]);
    $type = PlacementType::create(['name' => 'SIWES', 'is_active' => true]);

    $selectedStudent = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'admission_year' => '2026',
        'entry_level' => 100,
    ]);

    $unselectedStudent = Student::factory()->create([
        'institution_id' => $institution->id,
        'program_id' => $program->id,
        'admission_year' => '2026',
        'entry_level' => 100,
    ]);

    Livewire::actingAs($admin)
        ->test('admin.placements.manage')
        ->call('createPlacement')
        ->assertSet('academic_session', '2026/2027')
        ->call('openBatchModal')
        ->assertSet('batch_session', '2026/2027')
        ->set('batch_program_id', $program->id)
        ->set('batch_level', 100)
        ->set('batch_selected_student_ids', [(string) $selectedStudent->id])
        ->set('batch_placement_type_id', $type->id)
        ->set('batch_start_date', now()->addDay()->toDateString())
        ->set('batch_end_date', now()->addMonths(2)->toDateString())
        ->call('processBatchGeneration')
        ->assertHasNoErrors();

    expect(StudentPlacement::where('student_id', $selectedStudent->id)->exists())->toBeTrue();
    expect(StudentPlacement::where('student_id', $unselectedStudent->id)->exists())->toBeFalse();
});

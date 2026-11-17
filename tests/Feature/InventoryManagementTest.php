<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\InventoryStockAdjustment;
use App\Models\Program;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function () {
    User::clearScopedRoleCache();
    $this->seed(RbacSeeder::class);
});

it('blocks access to inventory routes when user lacks inventory permissions', function () {
    $institution = Institution::factory()->create();
    $lecturerRole = Role::where('role_name', 'Lecturer')->firstOrFail();

    $user = User::factory()->create(['institution_id' => $institution->id]);
    $user->roles()->attach($lecturerRole->role_id);

    $this->actingAs($user)
        ->get(route('cms.inventory.index'))
        ->assertForbidden();
});

it('allows institutional admin to seed categories, create items, and flag low stock', function () {
    $institution = Institution::factory()->create();
    $adminRole = Role::where('role_name', 'Institutional Admin')->firstOrFail();

    $admin = User::factory()->create(['institution_id' => $institution->id]);
    $admin->roles()->attach($adminRole->role_id);

    $this->actingAs($admin);

    $this->get(route('cms.inventory.index'))->assertSuccessful();
    $this->get(route('cms.inventory.catalogue'))->assertSuccessful();

    Livewire::test('pages::cms.inventory.catalogue')
        ->call('seedStandardCategories')
        ->assertHasNoErrors();

    $category = InventoryCategory::where('institution_id', $institution->id)
        ->where('classification', 'consumable')
        ->firstOrFail();

    Livewire::test('pages::cms.inventory.catalogue')
        ->set('item_category_id', $category->id)
        ->set('item_name', 'Sterile Surgical Gloves')
        ->set('item_code', 'GLV-001')
        ->set('item_unit', 'Boxes')
        ->set('item_min_threshold', 20)
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = InventoryItem::where('institution_id', $institution->id)
        ->where('code', 'GLV-001')
        ->firstOrFail();

    $lab = InventoryLocation::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Microbiology Lab',
        'location_type' => 'lab_clinic',
    ]);

    InventoryStock::create([
        'institution_id' => $institution->id,
        'inventory_item_id' => $item->id,
        'inventory_location_id' => $lab->id,
        'quantity' => 8,
    ]);

    expect($item->fresh()->isBelowThreshold())->toBeTrue();

    $this->get(route('cms.inventory.index'))
        ->assertSuccessful()
        ->assertSee('Sterile Surgical Gloves')
        ->assertSee('Low Stock Alert');
});

it('supports polymorphic locatable ownership and strict location officer custody scoping', function () {
    $institution = Institution::factory()->create();
    $dept = Department::factory()->create(['institution_id' => $institution->id, 'name' => 'Medical Laboratory']);
    $program = Program::factory()->create(['institution_id' => $institution->id, 'department_id' => $dept->id]);

    $labA = InventoryLocation::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Assigned Teaching Lab',
        'locatable_type' => $dept->getMorphClass(),
        'locatable_id' => $dept->id,
    ]);

    $labB = InventoryLocation::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Restricted Central Store',
        'locatable_type' => $program->getMorphClass(),
        'locatable_id' => $program->id,
    ]);

    expect($labA->locatable)->toBeInstanceOf(Department::class);
    expect($labB->locatable)->toBeInstanceOf(Program::class);

    $lecturerRole = Role::where('role_name', 'Lecturer')->firstOrFail();
    $staff = Staff::factory()->create([
        'institution_id' => $institution->id,
        'role_id' => $lecturerRole->role_id,
    ]);
    $officerUser = User::where('email', $staff->email)->firstOrFail();

    // Contextual privilege elevation: assigning Inventory Officer strictly on $labA
    $officerUser->assignScopedRole('Inventory Officer', $labA);

    $accessibleIds = InventoryLocation::accessibleBy($officerUser, $institution->id)->pluck('id')->toArray();
    expect($accessibleIds)->toContain($labA->id)
        ->not->toContain($labB->id);

    $category = InventoryCategory::factory()->durable()->create(['institution_id' => $institution->id]);
    $item = InventoryItem::factory()->create([
        'institution_id' => $institution->id,
        'inventory_category_id' => $category->id,
        'name' => 'Binocular Microscope',
        'min_stock_threshold' => 2,
    ]);

    $this->actingAs($officerUser);

    // Can reconcile assigned room $labA
    Livewire::test('pages::cms.inventory.reconcile')
        ->set('location_id', $labA->id)
        ->set("counts.{$item->id}", 12)
        ->set('notes', 'Verified physical lab count')
        ->call('saveItemCount', $item->id)
        ->assertHasNoErrors();

    expect(InventoryStock::where('inventory_location_id', $labA->id)->where('inventory_item_id', $item->id)->value('quantity'))
        ->toBe(12);

    $adjustment = InventoryStockAdjustment::where('inventory_location_id', $labA->id)
        ->where('inventory_item_id', $item->id)
        ->first();
    expect($adjustment)->not->toBeNull();
    expect($adjustment->previous_quantity)->toBe(0);
    expect($adjustment->new_quantity)->toBe(12);
    expect($adjustment->variance)->toBe(12);

    // Negative quantity boundary rejected
    Livewire::test('pages::cms.inventory.reconcile')
        ->set('location_id', $labA->id)
        ->set("counts.{$item->id}", -5)
        ->call('saveItemCount', $item->id)
        ->assertHasErrors(["counts.{$item->id}"]);

    // Attempting to access unassigned room $labB is forbidden
    $this->get(route('cms.inventory.reconcile', ['location_id' => $labB->id]))
        ->assertForbidden();
});

it('isolates inventory data strictly between tenant institutions', function () {
    $institutionA = Institution::factory()->create();
    $institutionB = Institution::factory()->create();

    $adminRole = Role::where('role_name', 'Institutional Admin')->firstOrFail();

    $adminA = User::factory()->create(['institution_id' => $institutionA->id]);
    $adminA->roles()->attach($adminRole->role_id);

    $catB = InventoryCategory::factory()->create(['institution_id' => $institutionB->id]);
    $itemB = InventoryItem::factory()->create([
        'institution_id' => $institutionB->id,
        'inventory_category_id' => $catB->id,
        'name' => 'Tenant B Secret Centrifuge',
        'code' => 'TNB-999',
    ]);
    $locB = InventoryLocation::factory()->create([
        'institution_id' => $institutionB->id,
        'name' => 'Tenant B Store',
    ]);

    $this->actingAs($adminA);

    $this->get(route('cms.inventory.catalogue'))
        ->assertSuccessful()
        ->assertDontSee('Tenant B Secret Centrifuge');

    $this->get(route('cms.inventory.locations'))
        ->assertSuccessful()
        ->assertDontSee('Tenant B Store');

    $this->get(route('cms.inventory.print-report', ['location_id' => $locB->id]))
        ->assertNotFound();
});

it('renders inventory reports, exports CSV, and renders printable stock sheets for auditor role', function () {
    $institution = Institution::factory()->create();
    $auditorRole = Role::where('role_name', 'Inventory Auditor')->firstOrFail();

    $auditor = User::factory()->create(['institution_id' => $institution->id]);
    $auditor->roles()->attach($auditorRole->role_id);

    $category = InventoryCategory::factory()->durable()->create(['institution_id' => $institution->id]);
    $item = InventoryItem::factory()->create([
        'institution_id' => $institution->id,
        'inventory_category_id' => $category->id,
        'name' => 'Diagnostic Centrifuge',
        'min_stock_threshold' => 10,
    ]);
    $location = InventoryLocation::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Central Diagnostics Lab',
    ]);
    InventoryStock::create([
        'institution_id' => $institution->id,
        'inventory_item_id' => $item->id,
        'inventory_location_id' => $location->id,
        'quantity' => 4,
    ]);

    $this->actingAs($auditor);

    $this->get(route('cms.inventory.reports'))
        ->assertSuccessful()
        ->assertSee('Diagnostic Centrifuge');

    $this->get(route('cms.inventory.print-report', ['location_id' => $location->id]))
        ->assertSuccessful()
        ->assertSee('ROOM STOCK SHEET')
        ->assertSee('Diagnostic Centrifuge');

    Livewire::test('pages::cms.inventory.reports')
        ->call('exportCsv')
        ->assertFileDownloaded();

    // Auditor is read-only and cannot reconcile stock
    $this->get(route('cms.inventory.reconcile'))
        ->assertForbidden();
});

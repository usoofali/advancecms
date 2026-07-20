<?php

use App\Livewire\Cms\IdCards\ManageTemplates;
use App\Models\IdCardTemplate;
use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->institution = Institution::factory()->create();
    $this->user = User::factory()->create([
        'institution_id' => $this->institution->id,
    ]);
    $role = Role::firstOrCreate(['role_name' => 'Super Admin'], ['level' => 1]);
    $this->user->roles()->attach($role->id);
    Gate::shouldReceive('authorize')->andReturn(true);
});

it('renders the manage templates component', function () {
    $this->actingAs($this->user);

    Livewire::test(ManageTemplates::class)
        ->assertOk()
        ->assertSee('Manage ID Card Templates');
});

it('can create a new ID card template', function () {
    $this->actingAs($this->user);

    Livewire::test(ManageTemplates::class)
        ->set('form_name', 'Student Test Template')
        ->set('form_type', 'student')
        ->set('form_layout', 'modern_sidebar')
        ->set('form_orientation', 'vertical')
        ->set('form_primary_color', '#ff0000')
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $this->assertDatabaseHas('id_card_templates', [
        'name' => 'Student Test Template',
        'type' => 'student',
        'layout' => 'modern_sidebar',
        'orientation' => 'vertical',
        'primary_color' => '#ff0000',
    ]);
});

it('can edit an existing template', function () {
    $this->actingAs($this->user);

    $template = IdCardTemplate::create([
        'name' => 'Old Template',
        'type' => 'student',
        'layout' => 'classic',
        'orientation' => 'horizontal',
        'primary_color' => '#000000',
        'font_family' => 'Inter, sans-serif',
        'font_weight' => 'normal',
        'font_style' => 'normal',
        'text_align' => 'left',
        'back_background_color' => '#f8fafc',
        'back_text_color' => '#3f3f46',
    ]);

    Livewire::test(ManageTemplates::class)
        ->call('editTemplate', $template->id)
        ->assertSet('form_name', 'Old Template')
        ->set('form_name', 'Updated Template')
        ->set('form_primary_color', '#ffffff')
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $this->assertDatabaseHas('id_card_templates', [
        'id' => $template->id,
        'name' => 'Updated Template',
        'primary_color' => '#ffffff',
    ]);
});

it('can delete a template', function () {
    $this->actingAs($this->user);

    $template = IdCardTemplate::create([
        'name' => 'To Delete',
        'type' => 'student',
        'layout' => 'classic',
        'orientation' => 'horizontal',
        'font_family' => 'Inter, sans-serif',
        'font_weight' => 'normal',
        'font_style' => 'normal',
        'text_align' => 'left',
        'back_background_color' => '#f8fafc',
        'back_text_color' => '#3f3f46',
    ]);

    Livewire::test(ManageTemplates::class)
        ->call('deleteTemplate', $template->id)
        ->assertDispatched('notify');

    $this->assertDatabaseMissing('id_card_templates', [
        'id' => $template->id,
    ]);
});

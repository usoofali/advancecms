<?php

use App\Models\AdmissionLetterTemplate;
use App\Models\Institution;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\ViewModels\AdmissionLetterPayload;
use Livewire\Volt\Volt;

test('returns default template values when institution has no custom template', function () {
    $institution = Institution::factory()->create(['name' => 'Apex Polytechnic']);

    $notificationTpl = AdmissionLetterTemplate::forInstitution($institution, 'notification');
    expect($notificationTpl->letter_title)->toBe('NOTIFICATION OF PROVISIONAL ADMISSION');
    expect($notificationTpl->opening_text)->toContain('Apex Polytechnic');

    $offerTpl = AdmissionLetterTemplate::forInstitution($institution, 'offer');
    expect($offerTpl->letter_title)->toBe('OFFER OF PROVISIONAL ADMISSION');
    expect($offerTpl->body_text)->toContain('{matric_number}');
});

test('customizing institution template overrides default values and replaces placeholders', function () {
    $institution = Institution::factory()->create(['name' => 'Grand College']);

    AdmissionLetterTemplate::create([
        'institution_id' => $institution->id,
        'type' => 'notification',
        'letter_title' => 'PROVISIONAL ADMISSION NOTICE FOR {applicant_name}',
        'salutation' => 'Welcome {applicant_name},',
        'opening_text' => 'We are glad to inform you of your selection at {institution_name} for {program_name}.',
        'body_text' => 'Please pay your acceptance fee to secure matriculation number.',
        'conditions_text' => 'Subject to credential screening.',
        'closing_text' => 'Congratulations!',
        'signatory_title' => 'Dean of Admissions',
        'signatory_subtitle' => 'Grand College Admissions Office',
        'show_qr_code' => false,
    ]);

    $template = AdmissionLetterTemplate::forInstitution($institution, 'notification');
    expect($template->letter_title)->toBe('PROVISIONAL ADMISSION NOTICE FOR {applicant_name}');
    expect($template->show_qr_code)->toBeFalse();

    $replacedTitle = AdmissionLetterPayload::replacePlaceholders($template->letter_title, [
        'applicant_name' => 'MARY SULE',
    ]);
    expect($replacedTitle)->toBe('PROVISIONAL ADMISSION NOTICE FOR MARY SULE');
});

test('livewire templates component allows editing and resetting institution templates', function () {
    $institution = Institution::factory()->create(['name' => 'City University']);
    $user = User::factory()->create(['institution_id' => $institution->id]);

    $role = Role::firstOrCreate(['role_name' => 'Admin'], ['level' => 1]);
    $permNotify = Permission::firstOrCreate(['permission_name' => 'applications.notify']);
    $permView = Permission::firstOrCreate(['permission_name' => 'applications.view']);

    $role->permissions()->syncWithoutDetaching([$permNotify->permission_id, $permView->permission_id]);
    $user->roles()->attach($role->role_id);

    $this->actingAs($user);

    Volt::test('pages::cms.admissions.templates')
        ->set('institutionId', $institution->id)
        ->set('letterTitle', 'CUSTOM CITY UNI ADMISSION NOTICE')
        ->set('salutation', 'Dear Candidate {applicant_name},')
        ->set('logoPosition', 'center')
        ->set('qrPosition', 'footer_right')
        ->call('saveTemplate')
        ->assertHasNoErrors();

    $dbTpl = AdmissionLetterTemplate::where('institution_id', $institution->id)
        ->where('type', 'notification')
        ->first();

    expect($dbTpl)->not->toBeNull();
    expect($dbTpl->letter_title)->toBe('CUSTOM CITY UNI ADMISSION NOTICE');
    expect($dbTpl->logo_position)->toBe('center');
    expect($dbTpl->qr_position)->toBe('footer_right');

    Volt::test('pages::cms.admissions.templates')
        ->set('institutionId', $institution->id)
        ->call('resetToDefault')
        ->assertHasNoErrors();

    $dbTplAfterReset = AdmissionLetterTemplate::where('institution_id', $institution->id)
        ->where('type', 'notification')
        ->first();

    expect($dbTplAfterReset)->toBeNull();
});

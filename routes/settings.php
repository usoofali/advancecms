<?php

use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/password', 'pages::settings.password')->name('user-password.edit');
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/two-factor', 'pages::settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    Route::livewire('settings/system', 'pages::settings.⚡system')
        ->name('settings.system')
        ->middleware('can:system.manage');

    Route::livewire('settings/roles', 'pages::settings.⚡roles')
        ->name('settings.roles')
        ->middleware('can:roles.view');

    Route::livewire('settings/addons', 'pages::settings.⚡addons')
        ->name('settings.addons')
        ->middleware('can:institutions.view');
});

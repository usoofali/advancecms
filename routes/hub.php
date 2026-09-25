<?php

use App\Http\Controllers\Gateway\Hub\WhatsAppWebhookController;
use App\Http\Middleware\EnsureHubAuthenticated;
use App\Livewire\Admin\CentralAnalyticsDashboard;
use App\Livewire\Hub\HubLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public WhatsApp Webhook Endpoints
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

// Hub Authentication Endpoints
Route::middleware(['web'])->group(function () {
    Route::get('/login', HubLogin::class)->name('hub.login');

    Route::post('/logout', function (Request $request) {
        $request->session()->forget('hub_authenticated');

        return redirect()->route('hub.login');
    })->name('hub.logout');

    // Protected Central Hub Dashboard Portal
    Route::middleware([EnsureHubAuthenticated::class])->group(function () {
        Route::get('/analytics', CentralAnalyticsDashboard::class)->name('hub.analytics');
    });
});

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GatewayServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/hub'));

        $role = config('gateway.role', env('CMS_NODE_ROLE', 'spoke'));

        if (in_array($role, ['hub', 'standalone'], true) || app()->environment('testing')) {
            Route::middleware(['api'])
                ->prefix('api/hub')
                ->group(base_path('routes/hub.php'));
        }
    }
}

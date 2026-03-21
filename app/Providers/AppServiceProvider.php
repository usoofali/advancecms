<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        // Fallback session and cache drivers for setup wizard if database isn't ready
        if (request()->is('setup') || request()->is('*livewire*')) {
            try {
                if (! Schema::hasTable('sessions')) {
                    config(['session.driver' => 'file']);
                }
                if (! Schema::hasTable('cache')) {
                    config(['cache.default' => 'file']);
                }
            } catch (\Exception $e) {
                config([
                    'session.driver' => 'file',
                    'cache.default' => 'file',
                ]);
            }
        }

        $this->configureDefaults();

        // Fix 1071 Specified key was too long error on shared hosting (older MySQL/MariaDB)
        Schema::defaultStringLength(191);

        // Super Admin bypass
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }
        });

        // Dynamic Permission Gates
        // Note: For performance, in large systems we'd use a more targeted approach
        // or a dedicated RBAC package, but for this spec, we'll implement core logic.
        Gate::after(function (User $user, string $ability) {
            return $user->hasPermissionTo($ability);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Paginator::useBootstrapFive();

        // Every permission code is defined dynamically in the `permissions`
        // table and assigned to roles via `role_permissions`, so instead of
        // declaring one Gate::define() per code (which would need editing
        // every time an admin adds a permission through the UI), we check
        // membership directly against the user's role. Any ability name
        // passed to can()/@can/the `can:` middleware is treated as a
        // permission code.
        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }

            return in_array($ability, $user->permissionCodes(), true) ?: null;
        });

        // @module('associates') ... @endmodule — gates a sidebar entry (or
        // any markup) behind the current user's role having that module
        // enabled, independent of action-level permissions (@can).
        Blade::if('module', function (string $code) {
            $user = auth()->user();

            return $user && in_array($code, $user->moduleCodes(), true);
        });
    }
}

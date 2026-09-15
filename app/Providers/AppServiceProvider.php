<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\User;
use App\Services\BirthdayService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

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

        // Feeds the notification bell in the topbar (layouts.app) on every
        // authenticated page, the same way the sidebar/topbar user menu are
        // always just rendered inline rather than fetched separately — a
        // View Composer keeps that query out of every controller instead
        // of repeating it wherever the layout is used.
        View::composer('layouts.app', function (ViewContract $view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            // Cumpleaños de socios: without a running scheduler (typical
            // XAMPP install) this is what gets today's birthday
            // notifications into the feed — cached to one run per day.
            app(BirthdayService::class)->ensureNotifiedToday();

            $recent = Notification::query()->latest()->limit(30)->get();

            $view->with('notifications', $recent);
            $view->with('unreadNotificationsCount', $recent->filter(fn (Notification $n) => ! $n->isReadBy($user->id))->count());
        });
    }
}

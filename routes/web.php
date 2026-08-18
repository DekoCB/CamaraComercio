<?php

use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssociateController;
use App\Http\Controllers\AssociateImportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

// -------------------------------------------------------------------
// Guest routes (EP-01)
// -------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');

    // Rate limited (5/min by IP) — these hand-rolled controllers don't
    // come with Breeze/Fortify's built-in throttling, and brute-forcing
    // login or spamming password-reset emails are the obvious abuse
    // vectors to close before production (section 25 of the functional
    // spec: "protección de endpoints").
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('login', [AuthenticatedSessionController::class, 'store']);
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// -------------------------------------------------------------------
// Authenticated routes
// -------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Associates (EP-03). Everyone with a session can browse the list;
    // create/edit require the associates.manage permission (checked
    // both by route middleware and by AssociateRequest::authorize()).
    Route::get('associates', [AssociateController::class, 'index'])->name('associates.index');
    Route::middleware('can:associates.manage')->group(function () {
        Route::get('associates/create', [AssociateController::class, 'create'])->name('associates.create');
        Route::post('associates', [AssociateController::class, 'store'])->name('associates.store');

        // Import (section 15). Static paths registered before the
        // {associate} wildcard below so "import" is never captured as
        // a route-model-binding ID.
        Route::get('associates/import', [AssociateImportController::class, 'create'])->name('associates.import.create');
        Route::post('associates/import/preview', [AssociateImportController::class, 'preview'])->name('associates.import.preview');
        Route::post('associates/import/confirm', [AssociateImportController::class, 'confirm'])->name('associates.import.confirm');
        Route::post('associates/import/cancel', [AssociateImportController::class, 'cancel'])->name('associates.import.cancel');

        Route::get('associates/{associate}/edit', [AssociateController::class, 'edit'])->name('associates.edit');
        Route::put('associates/{associate}', [AssociateController::class, 'update'])->name('associates.update');
    });

    // Billing (EP-04). Consulting is billing.view; the batch-generation
    // form/action requires billing.generate. The static "generate" routes
    // must be registered before the {invoice} wildcard below, or Laravel
    // would try to route-model-bind "generate" as an invoice ID.
    Route::middleware('can:billing.generate')->group(function () {
        Route::get('invoices/generate', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices/generate', [InvoiceController::class, 'store'])->name('invoices.store');
    });
    Route::middleware('can:billing.view')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });

    // Payments (EP-05).
    Route::middleware('can:payments.register')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    });

    // Portfolio / cartera (EP-06).
    Route::middleware('can:portfolio.view')->group(function () {
        Route::get('portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
        Route::get('portfolio/debtors', [PortfolioController::class, 'debtors'])->name('portfolio.debtors');
        Route::get('associates/{associate}/statement', [PortfolioController::class, 'statement'])->name('associates.statement');
    });

    // Reports / reportes (EP-07). Exporting requires reports.export in
    // addition to reports.view (checked separately so a role can see
    // reports on screen without being able to extract the data).
    Route::middleware('can:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/collections', [ReportController::class, 'collections'])->name('reports.collections');
        Route::get('reports/debt', [ReportController::class, 'debt'])->name('reports.debt');
    });
    Route::middleware('can:reports.export')->group(function () {
        Route::get('reports/collections/export/{format}', [ReportController::class, 'exportCollections'])->name('reports.collections.export');
        Route::get('reports/debt/export/{format}', [ReportController::class, 'exportDebt'])->name('reports.debt.export');
    });

    // Administration (EP-02) — each sub-area gated by its own permission,
    // enforced server-side regardless of what the sidebar shows.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:admin.users')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        });

        Route::middleware('can:admin.roles')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::get('roles/{role}/access', [RoleController::class, 'editAccess'])->name('roles.access.edit');
            Route::put('roles/{role}/access', [RoleController::class, 'updateAccess'])->name('roles.access.update');
        });

        Route::middleware('can:admin.modules')->group(function () {
            Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
            Route::get('modules/create', [ModuleController::class, 'create'])->name('modules.create');
            Route::post('modules', [ModuleController::class, 'store'])->name('modules.store');
            Route::put('modules/{module}/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');
        });
    });
});

<?php

use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssociateController;
use App\Http\Controllers\AssociateDeclarationController;
use App\Http\Controllers\AssociateDocumentController;
use App\Http\Controllers\AssociateImportController;
use App\Http\Controllers\AssociateInscriptionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BenefitUsageController;
use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceImportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentImportController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProtestController;
use App\Http\Controllers\RentalController;
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

    // Profile — self-service, no permission needed beyond being logged in.
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // Notifications — shared feed, visible to anyone with a session (same
    // reasoning as the profile routes above: no per-permission gate).
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Associates (EP-03). Everyone with a session can browse the list;
    // create/edit require the associates.manage permission (checked
    // both by route middleware and by AssociateRequest::authorize()).
    Route::get('associates', [AssociateController::class, 'index'])->name('associates.index');
    Route::get('associates/birthdays', [BirthdayController::class, 'index'])->name('associates.birthdays');
    Route::get('associates/{associate}', [AssociateController::class, 'show'])->name('associates.show')->whereNumber('associate');
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
        Route::delete('associates/{associate}', [AssociateController::class, 'destroy'])->name('associates.destroy');

        // Documentación escaneada (acta2.txt [11:39]) y beneficios
        // (acta2.txt [~15:30]) — subir/anular quedan bajo el mismo
        // permiso que el resto de la gestión del padrón de asociados.
        Route::post('associates/{associate}/documents', [AssociateDocumentController::class, 'store'])->name('associates.documents.store');
        Route::delete('associates/documents/{document}', [AssociateDocumentController::class, 'destroy'])->name('associates.documents.destroy');

        // Plantilla rellenable de la Ficha de Inscripción: abre pre-llenada
        // con los datos ya guardados del asociado; guardar actualiza esos
        // mismos datos y genera el PDF (AssociateInscriptionService).
        Route::get('associates/{associate}/ficha-inscripcion', [AssociateInscriptionController::class, 'edit'])->name('associates.inscripcion.edit');
        Route::put('associates/{associate}/ficha-inscripcion', [AssociateInscriptionController::class, 'update'])->name('associates.inscripcion.update');

        // Declaración Jurada: a diferencia de la ficha, requiere firma
        // física — el PDF se genera para imprimir y firmar, con firma y
        // huella digital escaneadas como campos opcionales.
        Route::get('associates/{associate}/declaracion-jurada', [AssociateDeclarationController::class, 'edit'])->name('associates.declaracion.edit');
        Route::put('associates/{associate}/declaracion-jurada', [AssociateDeclarationController::class, 'update'])->name('associates.declaracion.update');

        Route::post('associates/{associate}/benefit-usages', [BenefitUsageController::class, 'store'])->name('associates.benefitUsages.store');
        Route::delete('benefit-usages/{benefitUsage}', [BenefitUsageController::class, 'destroy'])->name('associates.benefitUsages.destroy');
    });

    // Alquiler de espacios (nuevo módulo, sept-2026). Igual que Reportes,
    // ver está separado de administrar: rentals.view alcanza para la
    // lista, el calendario y el PDF; rentals.manage habilita crear,
    // editar, confirmar, facturar y cancelar. "calendar" y "create" deben
    // registrarse antes de {rental} por la misma razón que en Facturación.
    Route::middleware('can:rentals.view')->group(function () {
        Route::get('rentals', [RentalController::class, 'index'])->name('rentals.index');
        Route::get('rentals/calendar', [RentalController::class, 'calendar'])->name('rentals.calendar');
    });
    // "create" is a static path one segment deep like {rental} itself, so
    // it has to be registered before that wildcard below — same reasoning
    // as the "generate"/"import" statics in Facturación.
    Route::middleware('can:rentals.manage')->group(function () {
        Route::get('rentals/create', [RentalController::class, 'create'])->name('rentals.create');
        Route::post('rentals', [RentalController::class, 'store'])->name('rentals.store');
    });
    Route::middleware('can:rentals.view')->group(function () {
        Route::get('rentals/{rental}', [RentalController::class, 'show'])->name('rentals.show');
        Route::get('rentals/{rental}/pdf', [RentalController::class, 'pdf'])->name('rentals.pdf');
    });
    Route::middleware('can:rentals.manage')->group(function () {
        Route::get('rentals/{rental}/edit', [RentalController::class, 'edit'])->name('rentals.edit');
        Route::put('rentals/{rental}', [RentalController::class, 'update'])->name('rentals.update');
        Route::put('rentals/{rental}/confirm', [RentalController::class, 'confirm'])->name('rentals.confirm');
        Route::put('rentals/{rental}/bill', [RentalController::class, 'bill'])->name('rentals.bill');
        Route::put('rentals/{rental}/cancel', [RentalController::class, 'cancel'])->name('rentals.cancel');
    });

    // Billing (EP-04). Consulting is billing.view; the batch-generation
    // form/action requires billing.generate. The static "generate" routes
    // must be registered before the {invoice} wildcard below, or Laravel
    // would try to route-model-bind "generate" as an invoice ID.
    Route::middleware('can:billing.generate')->group(function () {
        Route::get('invoices/generate', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices/generate', [InvoiceController::class, 'store'])->name('invoices.store');

        Route::get('invoices/import', [InvoiceImportController::class, 'create'])->name('invoices.import.create');
        Route::post('invoices/import/preview', [InvoiceImportController::class, 'preview'])->name('invoices.import.preview');
        Route::post('invoices/import/confirm', [InvoiceImportController::class, 'confirm'])->name('invoices.import.confirm');
        Route::post('invoices/import/cancel', [InvoiceImportController::class, 'cancel'])->name('invoices.import.cancel');
    });
    Route::middleware('can:billing.view')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/stats', [InvoiceController::class, 'stats'])->name('invoices.stats');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });
    // Edit/void a single invoice — only while it has no payments yet (see
    // InvoiceService), same "no borrado físico" rule as payments.void.
    Route::middleware('can:billing.edit')->group(function () {
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    });
    Route::middleware('can:billing.void')->group(function () {
        Route::put('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    });

    // Payments (EP-05).
    Route::middleware('can:payments.register')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [PaymentController::class, 'storeQuick'])->name('payments.storeQuick');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');

        Route::get('payments/import', [PaymentImportController::class, 'create'])->name('payments.import.create');
        Route::post('payments/import/preview', [PaymentImportController::class, 'preview'])->name('payments.import.preview');
        Route::post('payments/import/confirm', [PaymentImportController::class, 'confirm'])->name('payments.import.confirm');
        Route::post('payments/import/cancel', [PaymentImportController::class, 'cancel'])->name('payments.import.cancel');
    });
    Route::middleware('can:payments.void')->group(function () {
        Route::put('payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');
    });

    // Portfolio / cartera (EP-06).
    Route::middleware('can:portfolio.view')->group(function () {
        Route::get('portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
        Route::get('portfolio/debtors', [PortfolioController::class, 'debtors'])->name('portfolio.debtors');
        Route::get('portfolio/payments', [PortfolioController::class, 'payments'])->name('portfolio.payments');
        Route::get('associates/{associate}/statement', [PortfolioController::class, 'statement'])->name('associates.statement');
    });
    // Cartera exports need reports.export on top of portfolio.view — same
    // "seeing on screen" vs. "extracting the data" split as Reportes.
    Route::middleware(['can:portfolio.view', 'can:reports.export'])->group(function () {
        Route::get('portfolio/export/{format}', [PortfolioController::class, 'exportIndex'])->name('portfolio.export');
        Route::get('portfolio/debtors/export/{format}', [PortfolioController::class, 'exportDebtors'])->name('portfolio.debtors.export');
    });

    // Reports / reportes (EP-07). Exporting requires reports.export in
    // addition to reports.view (checked separately so a role can see
    // reports on screen without being able to extract the data).
    Route::middleware('can:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/collections', [ReportController::class, 'collections'])->name('reports.collections');
        Route::get('reports/debt', [ReportController::class, 'debt'])->name('reports.debt');
        Route::get('reports/collectors', [ReportController::class, 'collectors'])->name('reports.collectors');
    });
    Route::middleware('can:reports.export')->group(function () {
        Route::get('reports/collections/export/{format}', [ReportController::class, 'exportCollections'])->name('reports.collections.export');
        Route::get('reports/debt/export/{format}', [ReportController::class, 'exportDebt'])->name('reports.debt.export');
        Route::get('reports/collectors/export/{format}', [ReportController::class, 'exportCollectors'])->name('reports.collectors.export');
    });
    // "Protestos y Moras" en Reportes necesita protests.view además de
    // reports.view/reports.export — mismo criterio de permiso en capas
    // que Cartera (ver este dato es del módulo de Protestos, no de
    // Reportes en sí), así que Gerencia (que sí tiene reports.*) no lo
    // ve a menos que también tenga protests.view.
    Route::middleware(['can:reports.view', 'can:protests.view'])->group(function () {
        Route::get('reports/protests', [ReportController::class, 'protests'])->name('reports.protests');
    });
    Route::middleware(['can:reports.export', 'can:protests.view'])->group(function () {
        Route::get('reports/protests/export/{format}', [ReportController::class, 'exportProtests'])->name('reports.protests.export');
    });

    // Registro de Protestos y Moras — alcance PROVISIONAL, ver Protest.
    // Uso interno solo para personal de la CCH (sin consulta pública),
    // por eso no hay una ruta sin permiso como en associates.index.
    // "create" debe registrarse antes de {protest} por la misma razón
    // que en Alquileres y Facturación.
    Route::middleware('can:protests.view')->group(function () {
        Route::get('protests', [ProtestController::class, 'index'])->name('protests.index');
    });
    Route::middleware('can:protests.manage')->group(function () {
        Route::get('protests/create', [ProtestController::class, 'create'])->name('protests.create');
        Route::post('protests', [ProtestController::class, 'store'])->name('protests.store');
    });
    Route::middleware('can:protests.view')->group(function () {
        Route::get('protests/{protest}', [ProtestController::class, 'show'])->name('protests.show');
    });
    Route::middleware('can:protests.manage')->group(function () {
        Route::put('protests/{protest}/regularize', [ProtestController::class, 'regularize'])->name('protests.regularize');
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

<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development baseline (section 34 of the functional spec): roles,
 * permissions, modules, and one starter account per role — so every
 * role has a real login to demo or test with, now that the login form
 * requires picking a role that matches the account (see
 * AuthenticatedSessionController::store()). Every write is an
 * updateOrCreate/firstOrCreate, so this seeder is safe to re-run.
 */
class RolesPermissionsModulesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'associates.manage' => 'Registrar y actualizar asociados',
            'billing.generate' => 'Generar la facturación mensual',
            'billing.view' => 'Consultar facturas',
            'billing.edit' => 'Editar facturas sin pagos registrados',
            'billing.void' => 'Anular facturas emitidas por error',
            'payments.register' => 'Registrar pagos (totales y parciales)',
            'payments.void' => 'Anular pagos registrados por error',
            'portfolio.view' => 'Consultar cartera, morosidad y estado de cuenta',
            'reports.view' => 'Ver reportes de cobranza y deuda',
            'reports.export' => 'Exportar reportes a Excel/PDF',
            'rentals.view' => 'Ver alquileres de espacios y el calendario de reservas',
            'rentals.manage' => 'Crear, confirmar, facturar y cancelar alquileres de espacios',
            'admin.users' => 'Gestionar usuarios',
            'admin.roles' => 'Gestionar roles, permisos y accesos a módulos',
            'admin.modules' => 'Gestionar módulos del sistema',
        ])->map(fn (string $description, string $code) => Permission::updateOrCreate(['code' => $code], ['description' => $description]));

        $modules = collect([
            'dashboard' => ['Dashboard', 'bi-speedometer2', '/dashboard', 1],
            'associates' => ['Asociados', 'bi-people', '/associates', 2],
            'rentals' => ['Alquileres', 'bi-building', '/rentals', 3],
            'billing' => ['Facturación', 'bi-receipt', '/invoices', 4],
            'payments' => ['Pagos', 'bi-cash-coin', '/payments', 5],
            'portfolio' => ['Cartera', 'bi-graph-up', '/portfolio', 6],
            'reports' => ['Reportes', 'bi-bar-chart', '/reports', 7],
            'administration' => ['Administración', 'bi-gear', '/admin/users', 8],
        ])->map(fn (array $attrs, string $code) => Module::updateOrCreate(['code' => $code], [
            'name' => $attrs[0],
            'icon' => $attrs[1],
            'route' => $attrs[2],
            'sort_order' => $attrs[3],
            'is_active' => true,
        ]));

        $adminRole = Role::updateOrCreate(
            ['name' => 'Administrador'],
            ['description' => 'Acceso completo: administración del sistema y todas las operaciones.']
        );
        $adminRole->permissions()->sync($permissions->pluck('id'));
        $adminRole->modules()->sync($modules->pluck('id'));

        $collectorRole = Role::updateOrCreate(
            ['name' => 'Encargado de Cobranzas'],
            ['description' => 'Gestiona asociados, facturación, pagos, cartera y reportes.']
        );
        $collectorRole->permissions()->sync($permissions->only([
            'associates.manage', 'billing.generate', 'billing.view', 'billing.edit', 'billing.void', 'payments.register',
            'portfolio.view', 'reports.view', 'reports.export',
        ])->pluck('id'));
        $collectorRole->modules()->sync($modules->only([
            'dashboard', 'associates', 'billing', 'payments', 'portfolio', 'reports',
        ])->pluck('id'));

        // Los 4 roles pedidos por el cliente en la demo del 15-sep — acta2.txt
        // punto [10:41]: "el administrador tiene acceso total; roles como
        // logística no pueden ver ni modificar facturación ni la base de
        // datos". Gerencia es lectura/operación amplia salvo Administración
        // (mismo criterio que Encargado de Cobranzas, más reportes); los
        // otros tres quedan deliberadamente acotados al mínimo que el acta
        // describe — cualquier ajuste fino de permisos/módulos se hace
        // después desde Administración → Roles, sin tocar código.
        $managementRole = Role::updateOrCreate(
            ['name' => 'Gerencia'],
            ['description' => 'Visión completa de la operación (asociados, facturación, pagos, cartera, reportes), sin administración del sistema.']
        );
        $managementRole->permissions()->sync($permissions->only([
            'associates.manage', 'billing.generate', 'billing.view', 'billing.edit', 'billing.void', 'payments.register',
            'portfolio.view', 'reports.view', 'reports.export', 'rentals.view',
        ])->pluck('id'));
        $managementRole->modules()->sync($modules->only([
            'dashboard', 'associates', 'rentals', 'billing', 'payments', 'portfolio', 'reports',
        ])->pluck('id'));

        $logisticsRole = Role::updateOrCreate(
            ['name' => 'Logística'],
            ['description' => 'Acceso de referencia al padrón de asociados — sin facturación, pagos ni administración.']
        );
        $logisticsRole->permissions()->sync([]);
        $logisticsRole->modules()->sync($modules->only(['dashboard', 'associates'])->pluck('id'));

        $associateManagementRole = Role::updateOrCreate(
            ['name' => 'Gestión de Asociados'],
            ['description' => 'Alta, edición e importación del padrón de asociados, y su situación en cartera.']
        );
        $associateManagementRole->permissions()->sync($permissions->only([
            'associates.manage', 'portfolio.view', 'rentals.view', 'rentals.manage',
        ])->pluck('id'));
        $associateManagementRole->modules()->sync($modules->only(['dashboard', 'associates', 'rentals', 'portfolio'])->pluck('id'));

        $marketingRole = Role::updateOrCreate(
            ['name' => 'Marketing'],
            ['description' => 'Datos de contacto y cumpleaños/aniversarios de asociados — sin facturación, pagos ni administración.']
        );
        $marketingRole->permissions()->sync([]);
        $marketingRole->modules()->sync($modules->only(['dashboard', 'associates'])->pluck('id'));

        User::updateOrCreate(
            ['email' => 'admin@camaracomercio.test'],
            [
                'name' => 'Administrador General',
                'password' => Hash::make('Admin#2026Local'),
                'role_id' => $adminRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'cobranzas@camaracomercio.test'],
            [
                'name' => 'Encargado de Cobranzas',
                'password' => Hash::make('Cobranzas#2026Local'),
                'role_id' => $collectorRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'gerencia@camaracomercio.test'],
            [
                'name' => 'Gerencia',
                'password' => Hash::make('Gerencia#2026Local'),
                'role_id' => $managementRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'logistica@camaracomercio.test'],
            [
                'name' => 'Logística',
                'password' => Hash::make('Logistica#2026Local'),
                'role_id' => $logisticsRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'asociados@camaracomercio.test'],
            [
                'name' => 'Gestión de Asociados',
                'password' => Hash::make('Asociados#2026Local'),
                'role_id' => $associateManagementRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'marketing@camaracomercio.test'],
            [
                'name' => 'Marketing',
                'password' => Hash::make('Marketing#2026Local'),
                'role_id' => $marketingRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Catálogo de espacios alquilables (nuevo módulo Alquileres): mismo
        // criterio que el beneficio "uso gratuito del auditorio" — un
        // espacio real conocido, sembrado una vez; no hay pantalla de
        // administración todavía, así que agregar otro espacio implica
        // esta misma vía (una fila nueva aquí), no una migración.
        Space::updateOrCreate(['name' => 'Auditorio'], [
            'description' => 'Auditorio principal de la Cámara de Comercio de Huancayo.',
            'is_active' => true,
        ]);

        $this->command->info('Roles, permisos, módulos y usuarios de desarrollo listos:');
        $this->command->info('  Administrador:           admin@camaracomercio.test / Admin#2026Local');
        $this->command->info('  Encargado de cobranzas:  cobranzas@camaracomercio.test / Cobranzas#2026Local');
        $this->command->info('  Gerencia:                gerencia@camaracomercio.test / Gerencia#2026Local');
        $this->command->info('  Logística:               logistica@camaracomercio.test / Logistica#2026Local');
        $this->command->info('  Gestión de Asociados:    asociados@camaracomercio.test / Asociados#2026Local');
        $this->command->info('  Marketing:               marketing@camaracomercio.test / Marketing#2026Local');
    }
}

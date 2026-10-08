<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
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
            'rentals.requisitions.manage' => 'Registrar y ver requerimientos de pago y reembolsos de Logística',
            'protests.view' => 'Ver el registro de protestos y moras',
            'protests.manage' => 'Registrar y regularizar protestos y moras',
            'admin.users' => 'Gestionar usuarios',
            'admin.roles' => 'Gestionar roles, permisos y accesos a módulos',
            'admin.modules' => 'Gestionar módulos del sistema',
            'admin.sessions' => 'Ver sesiones activas y cerrarlas remotamente',
            'plates.manage' => 'Registrar trámites de emisión de placas vehiculares y ver sus tarifas',
        ])->map(fn (string $description, string $code) => Permission::updateOrCreate(['code' => $code], ['description' => $description]));

        $modules = collect([
            'dashboard' => ['Dashboard', 'bi-speedometer2', '/dashboard', 1],
            'associates' => ['Asociados', 'bi-people', '/associates', 2],
            'rentals' => ['Alquileres', 'bi-building', '/rentals', 3],
            'billing' => ['Facturación', 'bi-receipt', '/invoices', 4],
            'payments' => ['Pagos', 'bi-cash-coin', '/payments', 5],
            'portfolio' => ['Cartera', 'bi-graph-up', '/portfolio', 6],
            'protests' => ['Protestos y Moras', 'bi-shield-exclamation', '/protests', 7],
            'plates' => ['Placas', 'car', '/plates', 8],
            'reports' => ['Reportes', 'bi-bar-chart', '/reports', 9],
            'administration' => ['Administración', 'bi-gear', '/admin/users', 10],
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
            'portfolio.view', 'reports.view', 'reports.export', 'protests.view', 'protests.manage',
        ])->pluck('id'));
        $collectorRole->modules()->sync($modules->only([
            'dashboard', 'associates', 'billing', 'payments', 'portfolio', 'protests', 'reports',
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

        // A pedido explícito (oct-2026): Logística gana su primer permiso
        // de escritura real, acotado solo a Requerimientos de pago/
        // reembolsos — los documentos que de verdad emite ese
        // departamento. rentals.view se suma porque la pantalla vive
        // dentro de Alquileres y necesita poder entrar al módulo, pero
        // rentals.manage (crear/editar/confirmar/facturar/cancelar
        // alquileres reales) sigue sin otorgárseles.
        $logisticsRole = Role::updateOrCreate(
            ['name' => 'Logística'],
            ['description' => 'Acceso de referencia al padrón de asociados, y registro de requerimientos de pago y reembolsos — sin facturación, pagos ni administración.']
        );
        $logisticsRole->permissions()->sync($permissions->only([
            'rentals.view', 'rentals.requisitions.manage',
        ])->pluck('id'));
        $logisticsRole->modules()->sync($modules->only(['dashboard', 'associates', 'rentals'])->pluck('id'));

        $associateManagementRole = Role::updateOrCreate(
            ['name' => 'Gestión de Asociados'],
            ['description' => 'Alta, edición e importación del padrón de asociados, y su situación en cartera.']
        );
        $associateManagementRole->permissions()->sync($permissions->only([
            'associates.manage', 'portfolio.view', 'rentals.view', 'rentals.manage', 'rentals.requisitions.manage', 'plates.manage',
        ])->pluck('id'));
        $associateManagementRole->modules()->sync($modules->only(['dashboard', 'associates', 'rentals', 'portfolio', 'plates'])->pluck('id'));

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

        // Catálogo de espacios alquilables (nuevo módulo Alquileres), con
        // sus tarifas por hora reales (confirmadas por el cliente oct-2026).
        // Administrable desde "Gestionar espacios" — esto solo cubre una
        // instalación nueva; un entorno ya sembrado se corrige con la
        // migración 2026_10_03_120000, no reseteando estas filas.
        Space::updateOrCreate(['name' => 'Auditorio Mayor'], [
            'description' => 'Auditorio principal de la Cámara de Comercio de Huancayo.',
            'default_rate' => 250.00,
            'is_active' => true,
        ]);
        Space::updateOrCreate(['name' => 'Auditorio Menor'], [
            'description' => 'Sala del auditorio menor de la Cámara de Comercio de Huancayo.',
            'default_rate' => 180.00,
            'is_active' => true,
        ]);
        Space::updateOrCreate(['name' => 'Auditorio Junín'], [
            'description' => 'Sala del auditorio Junín de la Cámara de Comercio de Huancayo.',
            'default_rate' => 130.00,
            'is_active' => true,
        ]);

        Setting::set('rentals.projector_hourly_rate', Setting::get('rentals.projector_hourly_rate', '30.00'));
        Setting::set('rentals.bank_account_official', Setting::get('rentals.bank_account_official', implode("\n", [
            'CUENTA OFICIAL BBVA:',
            'CUENTA BBVA: 0011-0235-02019704-13',
            'CCI: 011-235-000201970413-95',
            'A NOMBRE: Fanny Galván Muñico y José Luis García Terrazos',
        ])));

        $this->command->info('Roles, permisos, módulos y usuarios de desarrollo listos:');
        $this->command->info('  Administrador:           admin@camaracomercio.test / Admin#2026Local');
        $this->command->info('  Encargado de cobranzas:  cobranzas@camaracomercio.test / Cobranzas#2026Local');
        $this->command->info('  Gerencia:                gerencia@camaracomercio.test / Gerencia#2026Local');
        $this->command->info('  Logística:               logistica@camaracomercio.test / Logistica#2026Local');
        $this->command->info('  Gestión de Asociados:    asociados@camaracomercio.test / Asociados#2026Local');
        $this->command->info('  Marketing:               marketing@camaracomercio.test / Marketing#2026Local');
    }
}

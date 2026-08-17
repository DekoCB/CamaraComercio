<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development baseline (section 34 of the functional spec): roles,
 * permissions, modules, and the two starter accounts (Administrador,
 * Encargado de Cobranzas). Every write is an updateOrCreate/firstOrCreate,
 * so this seeder is safe to re-run.
 */
class RolesPermissionsModulesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'associates.manage' => 'Registrar y actualizar asociados',
            'billing.generate' => 'Generar la facturación mensual',
            'billing.view' => 'Consultar facturas',
            'payments.register' => 'Registrar pagos (totales y parciales)',
            'portfolio.view' => 'Consultar cartera, morosidad y estado de cuenta',
            'reports.view' => 'Ver reportes de cobranza y deuda',
            'reports.export' => 'Exportar reportes a Excel/PDF',
            'admin.users' => 'Gestionar usuarios',
            'admin.roles' => 'Gestionar roles, permisos y accesos a módulos',
            'admin.modules' => 'Gestionar módulos del sistema',
        ])->map(fn (string $description, string $code) => Permission::updateOrCreate(['code' => $code], ['description' => $description]));

        $modules = collect([
            'dashboard' => ['Dashboard', 'bi-speedometer2', '/dashboard', 1],
            'associates' => ['Asociados', 'bi-people', '/associates', 2],
            'billing' => ['Facturación', 'bi-receipt', '/invoices', 3],
            'payments' => ['Pagos', 'bi-cash-coin', '/payments', 4],
            'portfolio' => ['Cartera', 'bi-graph-up', '/portfolio', 5],
            'reports' => ['Reportes', 'bi-bar-chart', '/reports', 6],
            'administration' => ['Administración', 'bi-gear', '/admin/users', 7],
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
            'associates.manage', 'billing.generate', 'billing.view', 'payments.register',
            'portfolio.view', 'reports.view', 'reports.export',
        ])->pluck('id'));
        $collectorRole->modules()->sync($modules->only([
            'dashboard', 'associates', 'billing', 'payments', 'portfolio', 'reports',
        ])->pluck('id'));

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

        $this->command->info('Roles, permisos, módulos y usuarios de desarrollo listos:');
        $this->command->info('  Administrador:          admin@camaracomercio.test / Admin#2026Local');
        $this->command->info('  Encargado de cobranzas:  cobranzas@camaracomercio.test / Cobranzas#2026Local');
    }
}

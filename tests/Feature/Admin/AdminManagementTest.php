<?php

namespace Tests\Feature\Admin;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_admin_can_create_a_module(): void
    {
        $admin = $this->userWithPermissions(['admin.modules']);

        $response = $this->actingAs($admin)->post('/admin/modules', [
            'code' => 'reports',
            'name' => 'Reportes',
            'sort_order' => 6,
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/modules');
        $this->assertDatabaseHas('modules', ['code' => 'reports', 'is_active' => true]);
    }

    public function test_duplicate_module_code_is_rejected(): void
    {
        Module::factory()->create(['code' => 'billing']);
        $admin = $this->userWithPermissions(['admin.modules']);

        $response = $this->actingAs($admin)->post('/admin/modules', [
            'code' => 'billing',
            'name' => 'Otro nombre',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_deactivating_a_module_removes_it_from_the_sidebar(): void
    {
        $module = Module::factory()->create(['code' => 'portfolio', 'is_active' => true]);
        $admin = $this->userWithPermissions(['admin.modules', 'admin.users'], ['portfolio']);

        // Sanity check: the module shows in the sidebar while active.
        $this->actingAs($admin)->get('/dashboard')->assertSee('Cartera');

        $this->actingAs($admin)->put("/admin/modules/{$module->id}/toggle", ['is_active' => '0']);
        $this->assertFalse($module->fresh()->is_active);

        // A deactivated module must disappear from navigation for every
        // role, even one that still has it assigned (HU-22). Re-fetch the
        // user for this second request: actingAs() keeps the same PHP
        // object across simulated requests within a test, and its
        // role->modules relation was already cached by the request above
        // — a testing artifact only, since real requests always resolve
        // the user fresh from the database.
        $response = $this->actingAs($admin->fresh())->get('/dashboard');
        $response->assertDontSee('Cartera');
    }

    public function test_admin_can_create_a_role_and_is_redirected_to_assign_access(): void
    {
        $admin = $this->userWithPermissions(['admin.roles']);

        $response = $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Supervisor',
            'description' => 'Rol de prueba',
        ]);

        $role = Role::where('name', 'Supervisor')->firstOrFail();
        $response->assertRedirect("/admin/roles/{$role->id}/access");
    }

    public function test_admin_can_assign_permissions_and_modules_to_a_role(): void
    {
        $admin = $this->userWithPermissions(['admin.roles']);
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['code' => 'reports.view']);
        $module = Module::factory()->create(['code' => 'reports']);

        $this->actingAs($admin)->put("/admin/roles/{$role->id}/access", [
            'permissions' => [$permission->id],
            'modules' => [$module->id],
        ]);

        $this->assertTrue($role->fresh()->permissions->contains($permission));
        $this->assertTrue($role->fresh()->modules->contains($module));
    }

    public function test_admin_can_create_a_user_with_hashed_password(): void
    {
        $admin = $this->userWithPermissions(['admin.users']);
        $role = Role::factory()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'password' => 'Password#123',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect('/admin/users');
        $user = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertNotSame('Password#123', $user->password);
        $this->assertTrue(Hash::check('Password#123', $user->password));
    }

    public function test_duplicate_user_email_is_rejected(): void
    {
        $admin = $this->userWithPermissions(['admin.users']);
        $role = Role::factory()->create();
        $existing = User::factory()->for($role)->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Otro',
            'email' => $existing->email,
            'password' => 'Password#123',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('email');
    }
}

<?php

namespace Tests\Concerns;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Builds a role with a specific set of permission codes / module codes
 * and a user attached to it, so RBAC tests can express "a user who can
 * do X" without re-deriving the whole roles/permissions/modules schema
 * in every test.
 */
trait CreatesUsers
{
    protected function userWithPermissions(array $permissionCodes = [], array $moduleCodes = []): User
    {
        $role = Role::factory()->create();

        if ($permissionCodes !== []) {
            $permissions = collect($permissionCodes)->map(
                fn (string $code) => Permission::firstOrCreate(['code' => $code], ['description' => $code])
            );
            $role->permissions()->sync($permissions->pluck('id'));
        }

        if ($moduleCodes !== []) {
            $modules = collect($moduleCodes)->map(
                fn (string $code) => Module::firstOrCreate(['code' => $code], ['name' => $code, 'is_active' => true])
            );
            $role->modules()->sync($modules->pluck('id'));
        }

        return User::factory()->for($role)->create();
    }
}

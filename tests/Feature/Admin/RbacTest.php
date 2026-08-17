<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_user_without_admin_users_permission_gets_403_on_direct_url(): void
    {
        // This is the crux of HU-23's acceptance criteria: authorization
        // must be enforced server-side, not just by hiding the sidebar
        // link. A user with unrelated permissions still can't reach the
        // page by typing the URL directly.
        $user = $this->userWithPermissions(['associates.manage']);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_user_with_admin_users_permission_can_access(): void
    {
        $user = $this->userWithPermissions(['admin.users']);

        $this->actingAs($user)->get('/admin/users')->assertOk();
    }

    public function test_inactive_user_is_denied_every_permission_even_if_assigned(): void
    {
        $user = $this->userWithPermissions(['admin.users', 'associates.manage']);
        $user->update(['is_active' => false]);

        // Force-login (bypassing the login form, which already blocks
        // inactive accounts) to isolate the Gate::before check itself.
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_sidebar_only_shows_modules_assigned_to_the_role(): void
    {
        $user = $this->userWithPermissions(moduleCodes: ['dashboard']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Dashboard');
        $response->assertDontSee('Administración');
    }
}

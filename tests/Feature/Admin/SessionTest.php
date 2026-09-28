<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    /** Inserts a raw `sessions` row the way SESSION_DRIVER=database would, without going through a real HTTP login. */
    private function seedSession(User $user, string $id, int $lastActivity, ?string $ip = '127.0.0.1'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode(serialize([])),
            'last_activity' => $lastActivity,
        ]);
    }

    public function test_index_lists_active_sessions_with_user_role_and_login_time(): void
    {
        $admin = $this->userWithPermissions(['admin.sessions']);
        $other = $this->userWithPermissions([]);
        $other->update(['name' => 'Ana Torres']);
        $this->seedSession($other, 'sess-other', now()->getTimestamp());
        AuditLog::record('auth.login', 'user', (string) $other->id, 'success');

        $response = $this->actingAs($admin)->get('/admin/sessions');

        $response->assertOk()
            ->assertSee('Ana Torres')
            ->assertSee($other->role->name);
    }

    public function test_sessions_past_the_configured_lifetime_are_excluded(): void
    {
        $admin = $this->userWithPermissions(['admin.sessions']);
        $stale = $this->userWithPermissions([]);
        $stale->update(['name' => 'Sesión Vieja']);
        $lifetime = (int) config('session.lifetime');
        $this->seedSession($stale, 'sess-stale', now()->subMinutes($lifetime + 30)->getTimestamp());

        $response = $this->actingAs($admin)->get('/admin/sessions');

        $response->assertOk()->assertDontSee('Sesión Vieja');
    }

    public function test_closing_a_session_deletes_its_row(): void
    {
        $admin = $this->userWithPermissions(['admin.sessions']);
        $other = $this->userWithPermissions([]);
        $this->seedSession($other, 'sess-to-close', now()->getTimestamp());

        $response = $this->actingAs($admin)->delete('/admin/sessions/sess-to-close');

        $response->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-to-close']);
    }

    public function test_user_without_admin_sessions_permission_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/admin/sessions')->assertForbidden();
        $this->actingAs($user)->delete('/admin/sessions/whatever')->assertForbidden();
    }
}

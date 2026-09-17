<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials_and_matching_role(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create(['password' => bcrypt('Correcta#123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correcta#123',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create(['password' => bcrypt('Correcta#123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'clave-incorrecta',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $role = Role::factory()->create();

        $response = $this->post('/login', [
            'email' => 'no-existe@example.com',
            'password' => 'cualquier-cosa',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create([
            'password' => bcrypt('Correcta#123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correcta#123',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_requires_selecting_a_role(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create(['password' => bcrypt('Correcta#123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correcta#123',
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->assertGuest();
    }

    public function test_login_fails_when_the_selected_role_does_not_match_the_users_own_role(): void
    {
        $ownRole = Role::factory()->create();
        $otherRole = Role::factory()->create();
        $user = User::factory()->for($ownRole)->create(['password' => bcrypt('Correcta#123')]);

        // Right email, right password, wrong "hat" — must fail exactly
        // like a wrong password, not reveal that the credentials were
        // otherwise correct.
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correcta#123',
            'role_id' => $otherRole->id,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_from_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_page_lists_the_available_roles(): void
    {
        $role = Role::factory()->create(['name' => 'Gerencia']);

        $response = $this->get('/login');

        $response->assertOk()->assertSee('Gerencia');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create(['password' => bcrypt('Correcta#123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'clave-incorrecta', 'role_id' => $role->id]);
        }

        // The 6th attempt within a minute is throttled, even with the
        // correct password — a hand-rolled login controller has no
        // brute-force protection unless routed through throttle:5,1.
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Correcta#123', 'role_id' => $role->id]);

        $response->assertStatus(429);
        $this->assertGuest();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create(['password' => bcrypt('Correcta#123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Correcta#123',
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
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $response = $this->post('/login', [
            'email' => 'no-existe@example.com',
            'password' => 'cualquier-cosa',
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

    public function test_login_attempts_are_rate_limited(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create(['password' => bcrypt('Correcta#123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'clave-incorrecta']);
        }

        // The 6th attempt within a minute is throttled, even with the
        // correct password — a hand-rolled login controller has no
        // brute-force protection unless routed through throttle:5,1.
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Correcta#123']);

        $response->assertStatus(429);
        $this->assertGuest();
    }
}

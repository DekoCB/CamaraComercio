<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_request_returns_generic_message_for_existing_email(): void
    {
        Notification::fake();
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_request_returns_same_response_for_unknown_email(): void
    {
        // HU-03: must not reveal whether the account exists — same
        // session key/behavior whether or not the user is found.
        $response = $this->post('/forgot-password', ['email' => 'no-existe@example.com']);

        $response->assertSessionHas('status');
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create(['password' => bcrypt('ClaveVieja#123')]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ClaveNueva#456',
            'password_confirmation' => 'ClaveNueva#456',
        ]);

        $response->assertRedirect('/login');

        $this->post('/login', ['email' => $user->email, 'password' => 'ClaveNueva#456'])
            ->assertRedirect('/dashboard');
    }

    public function test_reset_token_is_single_use(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PrimeraClave#123',
            'password_confirmation' => 'PrimeraClave#123',
        ]);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SegundaClave#456',
            'password_confirmation' => 'SegundaClave#456',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create();

        $response = $this->post('/reset-password', [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'ClaveNueva#456',
            'password_confirmation' => 'ClaveNueva#456',
        ]);

        $response->assertSessionHasErrors('email');
    }
}

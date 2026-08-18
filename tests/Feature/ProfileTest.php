<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_their_own_profile_form(): void
    {
        $user = $this->userWithPermissions();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk()->assertSee($user->name)->assertSee($user->email);
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = $this->userWithPermissions();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Nombre Actualizado',
            'email' => 'actualizado@example.com',
        ]);

        $response->assertRedirect('/profile');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre Actualizado',
            'email' => 'actualizado@example.com',
        ]);
    }

    public function test_email_can_be_kept_unchanged_but_not_duplicate_another_user(): void
    {
        $user = $this->userWithPermissions();
        $other = $this->userWithPermissions();

        // keeping the same email is fine (unique rule ignores self)
        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
        ])->assertRedirect('/profile');

        // taking someone else's email is not
        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $other->email,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = $this->userWithPermissions();
        $user->update(['password' => Hash::make('OldPassword#1')]);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'OldPassword#1',
            'password' => 'NewPassword#2',
            'password_confirmation' => 'NewPassword#2',
        ]);

        $response->assertRedirect('/profile');
        $this->assertTrue(Hash::check('NewPassword#2', $user->fresh()->password));
    }

    public function test_password_change_is_rejected_with_wrong_current_password(): void
    {
        $user = $this->userWithPermissions();
        $user->update(['password' => Hash::make('OldPassword#1')]);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'WrongPassword',
            'password' => 'NewPassword#2',
            'password_confirmation' => 'NewPassword#2',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('OldPassword#1', $user->fresh()->password));
    }

    public function test_password_change_requires_confirmation_to_match(): void
    {
        $user = $this->userWithPermissions();
        $user->update(['password' => Hash::make('OldPassword#1')]);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'OldPassword#1',
            'password' => 'NewPassword#2',
            'password_confirmation' => 'DoesNotMatch',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_name_and_email_can_be_updated_without_touching_the_password(): void
    {
        $user = $this->userWithPermissions();
        $originalHash = $user->password;

        $this->actingAs($user)->put('/profile', [
            'name' => 'Otro Nombre',
            'email' => $user->email,
        ])->assertRedirect('/profile');

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    /**
     * A real browser form always submits every field, even ones the user
     * never touched (current_password="", password="", ...) — unlike
     * `$this->put('/profile', [...])` with a key simply omitted, which is
     * indistinguishable from "field absent". This caught a real bug:
     * current_password's hash-check rule ran even on that empty string and
     * always failed, rejecting every profile save that didn't also change
     * the password.
     */
    public function test_saving_the_profile_with_all_fields_present_but_password_fields_empty_succeeds(): void
    {
        $user = $this->userWithPermissions();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect('/profile')->assertSessionDoesntHaveErrors();
    }

    public function test_user_can_upload_an_avatar_photo(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermissions();
        $photo = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $photo,
        ]);

        $response->assertRedirect('/profile');
        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_uploading_a_new_avatar_replaces_the_old_one(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermissions();

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $firstPath = $user->fresh()->avatar_path;

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $secondPath = $user->fresh()->avatar_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_user_can_remove_their_avatar(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermissions();
        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);
        $path = $user->fresh()->avatar_path;

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => '1',
        ]);

        $response->assertRedirect('/profile');
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_image_avatar_is_rejected(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermissions();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

        $response->assertSessionHasErrors('avatar');
    }
}

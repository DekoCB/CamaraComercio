<?php

namespace Tests\Feature;

use App\Models\Associate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AssociateTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_authorized_user_can_register_an_associate(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Comercial Andina SAC',
            'company' => 'Comercial Andina',
            'contact_phone' => '555-0101',
            'email' => 'contacto@andina.example.com',
        ]);

        $response->assertRedirect('/associates');
        $this->assertDatabaseHas('associates', ['name' => 'Comercial Andina SAC', 'is_active' => true]);
    }

    public function test_invalid_email_is_rejected_and_nothing_is_persisted(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Sin Correo Valido',
            'email' => 'esto-no-es-un-correo',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('associates', ['name' => 'Sin Correo Valido']);
    }

    public function test_blank_name_is_rejected(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', ['name' => '   ']);

        $response->assertSessionHasErrors('name');
    }

    public function test_duplicate_email_is_rejected_on_manual_create(): void
    {
        Associate::factory()->create(['email' => 'ya-existe@example.com']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Otro Asociado',
            'email' => 'ya-existe@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('associates', ['name' => 'Otro Asociado']);
    }

    public function test_multiple_associates_without_an_email_do_not_collide(): void
    {
        Associate::factory()->create(['email' => null]);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Sin Correo Tambien',
        ]);

        $response->assertRedirect('/associates');
        $this->assertDatabaseHas('associates', ['name' => 'Sin Correo Tambien']);
    }

    public function test_editing_an_associate_keeping_its_own_email_is_not_a_duplicate(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $associate = Associate::factory()->create(['email' => 'propio@example.com']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}", [
            'name' => $associate->name,
            'email' => 'propio@example.com',
        ]);

        $response->assertRedirect('/associates');
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_user_without_permission_cannot_create_an_associate(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/associates/create')->assertForbidden();
        $this->actingAs($user)->post('/associates', ['name' => 'Cualquiera'])->assertForbidden();
    }

    public function test_any_authenticated_user_can_browse_the_associate_list(): void
    {
        Associate::factory()->create(['name' => 'Visible Para Todos']);
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/associates')
            ->assertOk()
            ->assertSee('Visible Para Todos');
    }

    public function test_updating_an_associate_can_toggle_active_state(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $associate = Associate::factory()->create(['is_active' => true]);

        $this->actingAs($user)->put("/associates/{$associate->id}", [
            'name' => $associate->name,
            'email' => $associate->email,
        ]);

        $this->assertFalse($associate->fresh()->is_active);
    }

    public function test_search_filters_by_name_company_phone_or_email(): void
    {
        Associate::factory()->create(['name' => 'Unico Buscable XYZ']);
        Associate::factory()->create(['name' => 'Otro Distinto']);
        $user = $this->userWithPermissions([]);

        $response = $this->actingAs($user)->get('/associates?q=Buscable+XYZ');

        $response->assertSee('Unico Buscable XYZ')->assertDontSee('Otro Distinto');
    }
}

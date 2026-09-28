<?php

namespace Tests\Feature;

use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SpaceTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_creating_a_space_lists_it_as_active(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);

        $response = $this->actingAs($user)->post('/rentals/spaces', [
            'name' => 'Sala de Reuniones',
            'description' => 'Sala chica, hasta 10 personas.',
            'default_rate' => '60.00',
        ]);

        $response->assertRedirect(route('spaces.index'));
        $space = Space::first();
        $this->assertSame('Sala de Reuniones', $space->name);
        $this->assertTrue($space->is_active);
    }

    public function test_two_spaces_cannot_share_the_same_name(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        Space::factory()->create(['name' => 'Auditorio']);

        $response = $this->actingAs($user)->post('/rentals/spaces', ['name' => 'Auditorio']);

        $response->assertSessionHasErrors('name');
        $this->assertSame(1, Space::count());
    }

    public function test_editing_can_toggle_a_space_inactive(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $space = Space::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->put("/rentals/spaces/{$space->id}", [
            'name' => $space->name,
            // is_active omitted — an unchecked checkbox submits nothing.
        ]);

        $response->assertRedirect(route('spaces.index'));
        $this->assertFalse($space->fresh()->is_active);
    }

    public function test_user_without_rentals_manage_cannot_access_spaces(): void
    {
        $user = $this->userWithPermissions(['rentals.view']);
        $space = Space::factory()->create();

        $this->actingAs($user)->get('/rentals/spaces')->assertForbidden();
        $this->actingAs($user)->get('/rentals/spaces/create')->assertForbidden();
        $this->actingAs($user)->post('/rentals/spaces', ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->get("/rentals/spaces/{$space->id}/edit")->assertForbidden();
    }

    public function test_rentals_index_links_to_space_management_for_managers_only(): void
    {
        $manager = $this->userWithPermissions(['rentals.view', 'rentals.manage']);
        $viewer = $this->userWithPermissions(['rentals.view']);

        $this->actingAs($manager)->get('/rentals')->assertSee(route('spaces.index'));
        $this->actingAs($viewer)->get('/rentals')->assertDontSee(route('spaces.index'));
    }
}

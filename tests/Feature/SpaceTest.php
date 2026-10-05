<?php

namespace Tests\Feature;

use App\Models\Setting;
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
        $space = Space::where('name', 'Sala de Reuniones')->firstOrFail();
        $this->assertTrue($space->is_active);
    }

    public function test_two_spaces_cannot_share_the_same_name(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        Space::factory()->create(['name' => 'Sala Piloto']);

        $response = $this->actingAs($user)->post('/rentals/spaces', ['name' => 'Sala Piloto']);

        $response->assertSessionHasErrors('name');
        $this->assertSame(1, Space::where('name', 'Sala Piloto')->count());
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

    public function test_the_three_real_auditoriums_are_seeded_with_their_hourly_rates(): void
    {
        $this->assertSame('250.00', Space::where('name', 'Auditorio Mayor')->value('default_rate'));
        $this->assertSame('180.00', Space::where('name', 'Auditorio Menor')->value('default_rate'));
        $this->assertSame('130.00', Space::where('name', 'Auditorio Junín')->value('default_rate'));
        $this->assertSame(0, Space::where('name', 'Auditorio')->count());
        $this->assertSame('30.00', Setting::get('rentals.projector_hourly_rate'));
    }

    public function test_rentals_index_links_to_space_management_for_managers_only(): void
    {
        $manager = $this->userWithPermissions(['rentals.view', 'rentals.manage']);
        $viewer = $this->userWithPermissions(['rentals.view']);

        $this->actingAs($manager)->get('/rentals')->assertSee(route('spaces.index'));
        $this->actingAs($viewer)->get('/rentals')->assertDontSee(route('spaces.index'));
    }
}

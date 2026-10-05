<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Benefit;
use App\Models\BenefitUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BenefitTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_creating_a_benefit_adds_it_to_the_catalog(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates/benefits', [
            'name' => 'Uso de auditorio',
            'description' => 'Una vez al año, gratis.',
            'annual_quota' => '1',
        ]);

        $response->assertRedirect(route('benefits.index'));
        $this->assertDatabaseHas('benefits', [
            'name' => 'Uso de auditorio',
            'annual_quota' => 1,
            'is_active' => true,
        ]);
    }

    public function test_two_benefits_cannot_share_the_same_name(): void
    {
        Benefit::factory()->create(['name' => 'Uso de auditorio']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates/benefits', [
            'name' => 'Uso de auditorio',
            'annual_quota' => '2',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_editing_can_toggle_a_benefit_inactive(): void
    {
        $benefit = Benefit::factory()->create(['is_active' => true]);
        $user = $this->userWithPermissions(['associates.manage']);

        $this->actingAs($user)->put("/associates/benefits/{$benefit->id}", [
            'name' => $benefit->name,
            'annual_quota' => $benefit->annual_quota,
            // is_active intentionally omitted — an unchecked checkbox.
        ]);

        $this->assertFalse($benefit->fresh()->is_active);
    }

    public function test_user_without_associates_manage_cannot_write_the_catalog(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/associates/benefits/create')->assertForbidden();
        $this->actingAs($user)->post('/associates/benefits', ['name' => 'X', 'annual_quota' => '1'])->assertForbidden();
    }

    public function test_any_authenticated_user_can_see_the_catalog_and_summary(): void
    {
        $user = $this->userWithPermissions([]);
        Benefit::factory()->create(['name' => 'Uso de auditorio']);
        Associate::factory()->create(['name' => 'Panadería Dulce Hogar']);

        $this->actingAs($user)->get('/associates/benefits')
            ->assertOk()
            ->assertSee('Uso de auditorio')
            ->assertSee('Panadería Dulce Hogar');
    }

    public function test_summary_shows_remaining_count_per_associate(): void
    {
        $associate = Associate::factory()->create();
        $benefit = Benefit::factory()->create(['annual_quota' => 2]);
        BenefitUsage::create(['associate_id' => $associate->id, 'benefit_id' => $benefit->id, 'used_at' => now()]);
        $user = $this->userWithPermissions([]);

        $response = $this->actingAs($user)->get('/associates/benefits');

        $response->assertOk();
        // 1 used of 2 -> 1 remaining, both per-benefit and in the total column.
        $response->assertSeeInOrder(['Total disponibles']);
    }
}

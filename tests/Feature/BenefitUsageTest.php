<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Benefit;
use App\Models\BenefitUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BenefitUsageTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_a_benefit_usage_can_be_registered(): void
    {
        $associate = Associate::factory()->create();
        $benefit = Benefit::factory()->create(['name' => 'Uso de auditorio', 'annual_quota' => 1]);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post("/associates/{$associate->id}/benefit-usages", [
            'benefit_id' => $benefit->id,
            'used_at' => now()->toDateString(),
            'notes' => 'Evento de fin de año',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('benefit_usages', [
            'associate_id' => $associate->id,
            'benefit_id' => $benefit->id,
            'notes' => 'Evento de fin de año',
        ]);
    }

    public function test_a_benefit_cannot_be_used_beyond_its_annual_quota(): void
    {
        $associate = Associate::factory()->create();
        $benefit = Benefit::factory()->create(['annual_quota' => 1]);
        BenefitUsage::create(['associate_id' => $associate->id, 'benefit_id' => $benefit->id, 'used_at' => '2026-03-01']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post("/associates/{$associate->id}/benefit-usages", [
            'benefit_id' => $benefit->id,
            'used_at' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('benefit_id');
        $this->assertSame(1, BenefitUsage::count());
    }

    public function test_the_quota_resets_the_following_calendar_year(): void
    {
        $associate = Associate::factory()->create();
        $benefit = Benefit::factory()->create(['annual_quota' => 1]);
        BenefitUsage::create(['associate_id' => $associate->id, 'benefit_id' => $benefit->id, 'used_at' => '2025-06-01']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post("/associates/{$associate->id}/benefit-usages", [
            'benefit_id' => $benefit->id,
            'used_at' => '2026-01-15',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, BenefitUsage::count());
    }

    public function test_a_benefit_usage_record_can_be_deleted(): void
    {
        $associate = Associate::factory()->create();
        $benefit = Benefit::factory()->create();
        $usage = BenefitUsage::create(['associate_id' => $associate->id, 'benefit_id' => $benefit->id, 'used_at' => now()]);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->delete("/benefit-usages/{$usage->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('benefit_usages', ['id' => $usage->id]);
    }

    public function test_user_without_associates_manage_permission_cannot_register_or_delete_usage(): void
    {
        $associate = Associate::factory()->create();
        $benefit = Benefit::factory()->create();
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->post("/associates/{$associate->id}/benefit-usages", [
            'benefit_id' => $benefit->id,
            'used_at' => now()->toDateString(),
        ])->assertForbidden();

        $usage = BenefitUsage::create(['associate_id' => $associate->id, 'benefit_id' => $benefit->id, 'used_at' => now()]);
        $this->actingAs($user)->delete("/benefit-usages/{$usage->id}")->assertForbidden();
    }

    public function test_associate_page_shows_benefit_usage_progress(): void
    {
        $associate = Associate::factory()->create(['name' => 'Usa Beneficios SAC']);
        $benefit = Benefit::factory()->create(['name' => 'Uso de auditorio', 'annual_quota' => 1, 'is_active' => true]);
        BenefitUsage::create(['associate_id' => $associate->id, 'benefit_id' => $benefit->id, 'used_at' => now()]);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->get("/associates/{$associate->id}");

        $response->assertOk()->assertSee('Uso de auditorio: 1/1 este año');
    }
}

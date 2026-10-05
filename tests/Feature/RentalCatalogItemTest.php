<?php

namespace Tests\Feature;

use App\Models\RentalCatalogItem;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class RentalCatalogItemTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_creating_an_item_adds_it_to_the_catalog(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);

        $response = $this->actingAs($user)->post('/rentals/equipment', [
            'name' => 'Mantel decorativo',
            'default_hourly_rate' => '',
        ]);

        $response->assertRedirect(route('rental-catalog-items.index'));
        $this->assertDatabaseHas('rental_catalog_items', ['name' => 'Mantel decorativo', 'is_active' => true]);
    }

    public function test_two_items_cannot_share_the_same_name(): void
    {
        RentalCatalogItem::factory()->create(['name' => 'Consola']);
        $user = $this->userWithPermissions(['rentals.manage']);

        $response = $this->actingAs($user)->post('/rentals/equipment', ['name' => 'Consola']);

        $response->assertSessionHasErrors('name');
    }

    public function test_editing_can_toggle_an_item_inactive(): void
    {
        $item = RentalCatalogItem::factory()->create(['is_active' => true]);
        $user = $this->userWithPermissions(['rentals.manage']);

        $this->actingAs($user)->put("/rentals/equipment/{$item->id}", [
            'name' => $item->name,
            // is_active omitido — checkbox sin marcar.
        ]);

        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_user_without_rentals_manage_cannot_write_the_catalog(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/rentals/equipment/create')->assertForbidden();
        $this->actingAs($user)->post('/rentals/equipment', ['name' => 'X'])->assertForbidden();
    }

    public function test_official_bank_account_can_be_updated(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);

        $response = $this->actingAs($user)->put('/rentals/equipment/bank-account', [
            'bank_account_official' => "CUENTA BBVA: 0011-0235-02019704-13\nCCI: 011-235-000201970413-95",
        ]);

        $response->assertRedirect(route('rental-catalog-items.index'));
        $this->assertStringContainsString('BBVA', Setting::get('rentals.bank_account_official'));
    }

    public function test_the_15_real_catalog_items_are_seeded(): void
    {
        $this->assertSame('30.00', RentalCatalogItem::where('name', 'Proyector multimedia - ecrán')->value('default_hourly_rate'));
        $this->assertSame('50.00', RentalCatalogItem::where('name', 'Vigilancia sábado o domingo')->value('default_hourly_rate'));
        $this->assertNull(RentalCatalogItem::where('name', 'Sillas plásticas sin fundas')->value('default_hourly_rate'));
        $this->assertSame(15, RentalCatalogItem::count());
    }
}

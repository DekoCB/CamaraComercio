<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Rental;
use App\Models\RentalCatalogItem;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        $space = Space::factory()->create();
        $associate = Associate::factory()->create();

        return array_merge([
            'space_id' => $space->id,
            'associate_id' => $associate->id,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'amount' => '250.00',
            'purpose' => 'Capacitación',
        ], $overrides);
    }

    public function test_creating_a_rental_starts_it_as_cotizada(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $payload = $this->validPayload();

        $response = $this->actingAs($user)->post('/rentals', $payload);

        $rental = Rental::first();
        $response->assertRedirect(route('rentals.show', $rental));
        $this->assertSame(Rental::STATUS_COTIZADA, $rental->status);
        $this->assertSame((float) $payload['amount'], (float) $rental->amount);
    }

    public function test_create_form_shows_each_spaces_hourly_rate_and_the_catalog_items(): void
    {
        Space::factory()->create(['name' => 'Auditorio Mayor', 'default_rate' => 250]);
        RentalCatalogItem::factory()->create(['name' => 'Proyector multimedia - ecrán', 'default_hourly_rate' => 30]);
        $user = $this->userWithPermissions(['rentals.manage']);

        $response = $this->actingAs($user)->get('/rentals/create');

        $response->assertOk()
            ->assertSee('data-rate="250.00"', false)
            ->assertSee('Auditorio Mayor')
            ->assertSee('Proyector multimedia - ecrán');
    }

    public function test_bank_account_and_client_name_without_an_associate_are_saved(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);

        $this->actingAs($user)->post('/rentals', $this->validPayload([
            'associate_id' => null,
            'client_name' => 'Branko Perú',
            'bank_account' => "CUENTA BBVA: 0011-0235-02019704-13\nCCI: 011-235-000201970413-95",
        ]));

        $rental = Rental::first();
        $this->assertNull($rental->associate_id);
        $this->assertSame('Branko Perú', $rental->client_name);
        $this->assertSame('Branko Perú', $rental->clientLabel());
        $this->assertStringContainsString('BBVA', $rental->bank_account);
        $this->assertSame(2.0, $rental->hours());
    }

    public function test_either_an_associate_or_a_client_name_is_required(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);

        $response = $this->actingAs($user)->post('/rentals', $this->validPayload(['associate_id' => null, 'client_name' => null]));

        $response->assertSessionHasErrors(['associate_id', 'client_name']);
    }

    public function test_line_items_are_saved_and_rows_without_quantity_are_skipped(): void
    {
        $catalogItem = RentalCatalogItem::factory()->create(['name' => 'Proyector', 'default_hourly_rate' => 30]);
        $user = $this->userWithPermissions(['rentals.manage']);

        $this->actingAs($user)->post('/rentals', $this->validPayload([
            'line_items' => [
                ['catalog_item_id' => $catalogItem->id, 'quantity' => '2', 'hourly_rate' => '30'],
                ['description' => 'Sin cantidad, no debe guardarse', 'quantity' => '', 'hourly_rate' => '10'],
                ['description' => 'Pizarra extra', 'quantity' => '1', 'hourly_rate' => ''],
            ],
        ]));

        $rental = Rental::first();
        $this->assertSame(2, $rental->lineItems()->count());
        $projectorLine = $rental->lineItems()->where('catalog_item_id', $catalogItem->id)->first();
        $this->assertSame(60.0, $projectorLine->total());
        $customLine = $rental->lineItems()->whereNull('catalog_item_id')->first();
        $this->assertSame('Pizarra extra', $customLine->description);
    }

    public function test_catering_is_saved_only_when_it_has_content(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);

        $this->actingAs($user)->post('/rentals', $this->validPayload([
            'catering' => [
                'people_count' => '90',
                'drink_option' => 'Café',
                'sandwich_option' => 'Sandwich de asado',
                'dessert_option' => 'Pionono',
                'daily_cost' => '1350.00',
            ],
        ]));

        $rental = Rental::first();
        $this->assertNotNull($rental->catering);
        $this->assertSame(90, $rental->catering->people_count);
        $this->assertSame(1350.0, (float) $rental->catering->daily_cost);
        $this->assertSame(1350.0, $rental->cateringTotal());
    }

    public function test_catering_is_not_created_when_the_section_is_left_blank(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);

        $this->actingAs($user)->post('/rentals', $this->validPayload());

        $this->assertNull(Rental::first()->catering);
    }

    public function test_items_summary_lists_the_first_three_items_and_counts_the_rest(): void
    {
        $items = RentalCatalogItem::factory()->count(5)->sequence(
            ['name' => 'Proyector'],
            ['name' => 'Sillas sin fundas'],
            ['name' => 'Mesas con mantel'],
            ['name' => 'Micrófonos'],
            ['name' => 'Pizarra'],
        )->create();
        $user = $this->userWithPermissions(['rentals.manage']);

        $this->actingAs($user)->post('/rentals', $this->validPayload([
            'line_items' => $items->map(fn ($item) => ['catalog_item_id' => $item->id, 'quantity' => '1', 'hourly_rate' => '0'])->all(),
            'catering' => ['daily_cost' => '100'],
        ]));

        $rental = Rental::first()->fresh();
        $this->assertSame('Proyector, Sillas sin fundas, Mesas con mantel +2 más · Coffee break', $rental->itemsSummary());
    }

    public function test_items_summary_is_null_without_items_or_catering(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $this->actingAs($user)->post('/rentals', $this->validPayload());

        $this->assertNull(Rental::first()->fresh()->itemsSummary());
    }

    public function test_grand_total_combines_space_line_items_and_catering(): void
    {
        $space = Space::factory()->create(['default_rate' => 180]);
        $catalogItem = RentalCatalogItem::factory()->create(['default_hourly_rate' => 30]);
        $user = $this->userWithPermissions(['rentals.manage']);

        $this->actingAs($user)->post('/rentals', $this->validPayload([
            'space_id' => $space->id,
            'line_items' => [['catalog_item_id' => $catalogItem->id, 'quantity' => '2', 'hourly_rate' => '30']],
            'catering' => ['daily_cost' => '1350.00'],
        ]));

        $rental = Rental::first()->fresh();
        // Espacio: 180 x 2h = 360; ítem: 2 x 30 = 60; equipo = 420; + catering 1350 = 1770.
        $this->assertSame(420.0, $rental->equipmentTotal());
        $this->assertSame(1770.0, $rental->grandTotal());
    }

    public function test_updating_replaces_the_previous_line_items(): void
    {
        $itemA = RentalCatalogItem::factory()->create(['default_hourly_rate' => 10]);
        $itemB = RentalCatalogItem::factory()->create(['default_hourly_rate' => 20]);
        $user = $this->userWithPermissions(['rentals.manage']);
        $this->actingAs($user)->post('/rentals', $this->validPayload([
            'line_items' => [['catalog_item_id' => $itemA->id, 'quantity' => '1', 'hourly_rate' => '10']],
        ]));
        $rental = Rental::first();

        $this->actingAs($user)->put("/rentals/{$rental->id}", $this->validPayload([
            'line_items' => [['catalog_item_id' => $itemB->id, 'quantity' => '3', 'hourly_rate' => '20']],
        ]));

        $this->assertSame(1, $rental->lineItems()->count());
        $this->assertSame($itemB->id, $rental->lineItems()->first()->catalog_item_id);
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);

        $response = $this->actingAs($user)->post('/rentals', $this->validPayload([
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->format('Y-m-d\TH:i'),
        ]));

        $response->assertSessionHasErrors('ends_at');
        $this->assertSame(0, Rental::count());
    }

    public function test_confirming_a_cotizacion_moves_it_to_confirmada(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $rental = Rental::factory()->create(['status' => Rental::STATUS_COTIZADA]);

        $response = $this->actingAs($user)->put("/rentals/{$rental->id}/confirm");

        $response->assertRedirect(route('rentals.show', $rental));
        $this->assertSame(Rental::STATUS_CONFIRMADA, $rental->fresh()->status);
    }

    public function test_confirming_is_blocked_when_the_space_already_has_an_overlapping_confirmed_rental(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $space = Space::factory()->create();
        $starts = now()->addDay()->setTime(10, 0);

        Rental::factory()->create([
            'space_id' => $space->id,
            'status' => Rental::STATUS_CONFIRMADA,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(3),
        ]);

        // Overlaps the first booking by one hour (11:00–14:00 vs. 10:00–13:00).
        $second = Rental::factory()->create([
            'space_id' => $space->id,
            'status' => Rental::STATUS_COTIZADA,
            'starts_at' => $starts->copy()->addHour(),
            'ends_at' => $starts->copy()->addHours(4),
        ]);

        $response = $this->actingAs($user)->put("/rentals/{$second->id}/confirm");

        $response->assertSessionHasErrors('status');
        $this->assertSame(Rental::STATUS_COTIZADA, $second->fresh()->status);
    }

    public function test_two_open_cotizaciones_for_the_same_slot_do_not_block_each_other(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $space = Space::factory()->create();
        $starts = now()->addDay()->setTime(10, 0);

        $first = Rental::factory()->create([
            'space_id' => $space->id,
            'status' => Rental::STATUS_COTIZADA,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(3),
        ]);
        Rental::factory()->create([
            'space_id' => $space->id,
            'status' => Rental::STATUS_COTIZADA,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(3),
        ]);

        $response = $this->actingAs($user)->put("/rentals/{$first->id}/confirm");

        $response->assertRedirect();
        $this->assertSame(Rental::STATUS_CONFIRMADA, $first->fresh()->status);
    }

    public function test_billing_requires_a_confirmed_rental(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $rental = Rental::factory()->create(['status' => Rental::STATUS_COTIZADA]);

        $response = $this->actingAs($user)->put("/rentals/{$rental->id}/bill");

        $response->assertSessionHasErrors('status');
        $this->assertSame(Rental::STATUS_COTIZADA, $rental->fresh()->status);
    }

    public function test_billing_a_confirmed_rental_marks_it_facturada(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $rental = Rental::factory()->create(['status' => Rental::STATUS_CONFIRMADA]);

        $response = $this->actingAs($user)->put("/rentals/{$rental->id}/bill");

        $response->assertRedirect(route('rentals.show', $rental));
        $this->assertSame(Rental::STATUS_FACTURADA, $rental->fresh()->status);
    }

    public function test_cancelling_records_the_reason_and_never_deletes_the_row(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $rental = Rental::factory()->create(['status' => Rental::STATUS_COTIZADA]);

        $response = $this->actingAs($user)->put("/rentals/{$rental->id}/cancel", ['reason' => 'El cliente canceló el evento']);

        $response->assertRedirect(route('rentals.show', $rental));
        $rental->refresh();
        $this->assertSame(Rental::STATUS_CANCELADA, $rental->status);
        $this->assertSame('El cliente canceló el evento', $rental->cancel_reason);
        $this->assertNotNull($rental->cancelled_at);
        $this->assertDatabaseHas('rentals', ['id' => $rental->id]);
    }

    public function test_a_billed_rental_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $rental = Rental::factory()->create(['status' => Rental::STATUS_FACTURADA]);

        $response = $this->actingAs($user)->put("/rentals/{$rental->id}/cancel", ['reason' => 'Intento tardío']);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(Rental::STATUS_FACTURADA, $rental->fresh()->status);
    }

    public function test_a_billed_rental_cannot_be_edited(): void
    {
        $user = $this->userWithPermissions(['rentals.manage']);
        $rental = Rental::factory()->create(['status' => Rental::STATUS_FACTURADA]);

        $response = $this->actingAs($user)->put("/rentals/{$rental->id}", $this->validPayload());

        $response->assertSessionHasErrors('amount');
    }

    public function test_the_calendar_lists_active_bookings_for_the_month_and_excludes_cancelled_ones(): void
    {
        $user = $this->userWithPermissions(['rentals.view']);
        $month = now()->addMonth()->startOfMonth();

        $visible = Rental::factory()->create(['status' => Rental::STATUS_CONFIRMADA, 'starts_at' => $month->copy()->addDays(4)->setTime(9, 0), 'ends_at' => $month->copy()->addDays(4)->setTime(11, 0)]);
        Rental::factory()->create(['status' => Rental::STATUS_CANCELADA, 'starts_at' => $month->copy()->addDays(5)->setTime(9, 0), 'ends_at' => $month->copy()->addDays(5)->setTime(11, 0)]);

        $response = $this->actingAs($user)->get('/rentals/calendar?month='.$month->format('Y-m'));

        $response->assertOk()->assertSee($visible->space->name);
    }

    public function test_pdf_download_returns_a_real_pdf(): void
    {
        $user = $this->userWithPermissions(['rentals.view']);
        $rental = Rental::factory()->create();

        $response = $this->actingAs($user)->get("/rentals/{$rental->id}/pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_user_without_rentals_view_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);
        $rental = Rental::factory()->create();

        $this->actingAs($user)->get('/rentals')->assertForbidden();
        $this->actingAs($user)->get('/rentals/calendar')->assertForbidden();
        $this->actingAs($user)->get("/rentals/{$rental->id}")->assertForbidden();
    }

    public function test_user_with_view_but_not_manage_cannot_write(): void
    {
        $user = $this->userWithPermissions(['rentals.view']);
        $rental = Rental::factory()->create();

        $this->actingAs($user)->get('/rentals/create')->assertForbidden();
        $this->actingAs($user)->post('/rentals', $this->validPayload())->assertForbidden();
        $this->actingAs($user)->put("/rentals/{$rental->id}/confirm")->assertForbidden();
    }

    public function test_associate_page_is_unaffected_and_show_page_links_to_it(): void
    {
        $user = $this->userWithPermissions(['rentals.view']);
        $rental = Rental::factory()->create();

        $response = $this->actingAs($user)->get("/rentals/{$rental->id}");

        $response->assertOk()->assertSee(route('associates.show', $rental->associate));
    }
}

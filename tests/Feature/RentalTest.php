<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Rental;
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

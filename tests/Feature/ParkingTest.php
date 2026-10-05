<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\ParkingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ParkingTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'plate' => 'abc-123',
            'owner_name' => 'Visitante de prueba',
            'entered_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    public function test_registering_an_entry_creates_an_open_session(): void
    {
        $user = $this->userWithPermissions(['parking.manage']);

        $response = $this->actingAs($user)->post('/parking', $this->validPayload());

        $response->assertRedirect(route('parking.index'));
        $session = ParkingSession::first();
        $this->assertSame('ABC-123', $session->plate);
        $this->assertTrue($session->isParked());
    }

    public function test_owner_name_is_required_when_no_associate_is_linked(): void
    {
        $user = $this->userWithPermissions(['parking.manage']);

        $response = $this->actingAs($user)->post('/parking', $this->validPayload(['owner_name' => null]));

        $response->assertSessionHasErrors('owner_name');
    }

    public function test_an_associate_can_be_linked_instead_of_a_free_text_owner(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['parking.manage']);

        $this->actingAs($user)->post('/parking', $this->validPayload([
            'associate_id' => $associate->id,
            'owner_name' => null,
        ]));

        $session = ParkingSession::first();
        $this->assertSame($associate->id, $session->associate_id);
        $this->assertSame($associate->name, $session->ownerLabel());
    }

    public function test_entered_at_cannot_be_in_the_future(): void
    {
        $user = $this->userWithPermissions(['parking.manage']);

        $response = $this->actingAs($user)->post('/parking', $this->validPayload([
            'entered_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ]));

        $response->assertSessionHasErrors('entered_at');
    }

    public function test_checking_out_closes_the_session_and_never_deletes_it(): void
    {
        $user = $this->userWithPermissions(['parking.manage']);
        $this->actingAs($user)->post('/parking', $this->validPayload());
        $session = ParkingSession::first();

        $response = $this->actingAs($user)->put("/parking/{$session->id}/checkout", [
            'exited_at' => now()->format('Y-m-d\TH:i'),
            'amount' => '12.50',
        ]);

        $response->assertRedirect(route('parking.index'));
        $fresh = $session->fresh();
        $this->assertFalse($fresh->isParked());
        $this->assertSame('12.50', $fresh->amount);
        $this->assertSame(1, ParkingSession::count());
    }

    public function test_a_session_cannot_be_checked_out_twice(): void
    {
        $user = $this->userWithPermissions(['parking.manage']);
        $this->actingAs($user)->post('/parking', $this->validPayload());
        $session = ParkingSession::first();
        $this->actingAs($user)->put("/parking/{$session->id}/checkout", ['exited_at' => now()->format('Y-m-d\TH:i')]);

        $response = $this->actingAs($user)->put("/parking/{$session->id}/checkout", ['exited_at' => now()->format('Y-m-d\TH:i')]);

        $response->assertSessionHasErrors('exited_at');
    }

    public function test_an_open_session_can_be_edited_but_a_closed_one_cannot(): void
    {
        $user = $this->userWithPermissions(['parking.manage']);
        $this->actingAs($user)->post('/parking', $this->validPayload());
        $session = ParkingSession::first();

        $this->actingAs($user)->put("/parking/{$session->id}", $this->validPayload(['plate' => 'xyz-999']))
            ->assertRedirect(route('parking.index'));
        $this->assertSame('XYZ-999', $session->fresh()->plate);

        $this->actingAs($user)->put("/parking/{$session->id}/checkout", ['exited_at' => now()->format('Y-m-d\TH:i')]);

        $response = $this->actingAs($user)->put("/parking/{$session->id}", $this->validPayload(['plate' => 'nope-1']));
        $response->assertSessionHasErrors('plate');
        $this->assertSame('XYZ-999', $session->fresh()->plate);
    }

    public function test_index_can_filter_by_parked_or_exited(): void
    {
        $user = $this->userWithPermissions(['parking.manage']);
        $this->actingAs($user)->post('/parking', $this->validPayload(['plate' => 'par-001']));
        $this->actingAs($user)->post('/parking', $this->validPayload(['plate' => 'par-002']));
        $exited = ParkingSession::where('plate', 'PAR-002')->first();
        $this->actingAs($user)->put("/parking/{$exited->id}/checkout", ['exited_at' => now()->format('Y-m-d\TH:i')]);

        $this->actingAs($user)->get('/parking?status=parked')->assertOk()->assertSee('PAR-001')->assertDontSee('PAR-002');
        $this->actingAs($user)->get('/parking?status=exited')->assertOk()->assertSee('PAR-002')->assertDontSee('PAR-001');
    }

    public function test_index_shows_the_monthly_summary(): void
    {
        $user = $this->userWithPermissions(['parking.manage']);

        $this->actingAs($user)->get('/parking')
            ->assertOk()
            ->assertSee('entradas')
            ->assertSee('estacionados ahora');
    }

    public function test_user_without_parking_manage_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/parking')->assertForbidden();
        $this->actingAs($user)->post('/parking', $this->validPayload())->assertForbidden();
    }
}

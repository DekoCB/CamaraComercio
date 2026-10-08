<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\PlateIssuance;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PlateIssuanceTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'procedure_type' => PlateIssuance::PROCEDURE_NUEVA,
            'plate_number' => 'abc-123',
            'client_name' => 'Juan Pérez',
            'vehicle_description' => 'Toyota Yaris',
            'receipt_type' => PlateIssuance::RECEIPT_BOLETA,
            'amount' => '45.00',
            'issued_at' => now()->toDateString(),
        ], $overrides);
    }

    public function test_registering_a_plate_issuance_creates_it(): void
    {
        $user = $this->userWithPermissions(['plates.manage']);

        $response = $this->actingAs($user)->post('/plates', $this->validPayload());

        $record = PlateIssuance::first();
        $response->assertRedirect(route('plates.show', $record));
        $this->assertSame('Juan Pérez', $record->client_name);
        $this->assertSame('ABC-123', $record->plate_number);
    }

    public function test_an_associate_can_be_linked_as_the_requester(): void
    {
        $user = $this->userWithPermissions(['plates.manage']);
        $associate = Associate::factory()->create();

        $this->actingAs($user)->post('/plates', $this->validPayload(['associate_id' => $associate->id, 'client_name' => null]));

        $this->assertSame($associate->id, PlateIssuance::first()->associate_id);
    }

    public function test_either_an_associate_or_a_client_name_is_required(): void
    {
        $user = $this->userWithPermissions(['plates.manage']);

        $response = $this->actingAs($user)->post('/plates', $this->validPayload(['client_name' => null]));

        $response->assertSessionHasErrors('client_name');
        $this->assertSame(0, PlateIssuance::count());
    }

    public function test_other_description_is_required_when_procedure_is_otros(): void
    {
        $user = $this->userWithPermissions(['plates.manage']);

        $response = $this->actingAs($user)->post('/plates', $this->validPayload(['procedure_type' => PlateIssuance::PROCEDURE_OTROS]));

        $response->assertSessionHasErrors('other_description');
    }

    public function test_other_description_is_discarded_unless_procedure_is_otros(): void
    {
        $user = $this->userWithPermissions(['plates.manage']);

        $this->actingAs($user)->post('/plates', $this->validPayload(['other_description' => 'Trámite especial']));

        $this->assertNull(PlateIssuance::first()->other_description);
    }

    public function test_issued_at_cannot_be_in_the_future(): void
    {
        $user = $this->userWithPermissions(['plates.manage']);

        $response = $this->actingAs($user)->post('/plates', $this->validPayload(['issued_at' => now()->addDays(3)->toDateString()]));

        $response->assertSessionHasErrors('issued_at');
        $this->assertSame(0, PlateIssuance::count());
    }

    public function test_index_shows_the_monthly_summary(): void
    {
        $user = $this->userWithPermissions(['plates.manage']);
        PlateIssuance::factory()->count(3)->create(['issued_at' => now()]);

        $response = $this->actingAs($user)->get('/plates');

        $response->assertOk();
        $response->assertSee('3', false);
    }

    public function test_rates_can_be_updated_and_are_prefilled_on_the_form(): void
    {
        $user = $this->userWithPermissions(['plates.manage']);

        $this->actingAs($user)->put('/plates/rates', [
            'rates' => [PlateIssuance::PROCEDURE_NUEVA => '45.00'],
        ]);

        $this->assertSame('45.00', Setting::get(PlateIssuance::rateSettingKey(PlateIssuance::PROCEDURE_NUEVA)));

        $response = $this->actingAs($user)->get('/plates/create');
        $response->assertOk()->assertSee('data-rate="45"', false);
    }

    public function test_user_without_plates_manage_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);
        $record = PlateIssuance::factory()->create();

        $this->actingAs($user)->get('/plates')->assertForbidden();
        $this->actingAs($user)->get('/plates/create')->assertForbidden();
        $this->actingAs($user)->post('/plates', $this->validPayload())->assertForbidden();
        $this->actingAs($user)->get("/plates/{$record->id}")->assertForbidden();
        $this->actingAs($user)->put('/plates/rates', ['rates' => []])->assertForbidden();
    }
}

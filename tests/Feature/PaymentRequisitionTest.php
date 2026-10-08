<?php

namespace Tests\Feature;

use App\Models\PaymentRequisition;
use App\Models\Role;
use Database\Seeders\RolesPermissionsModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PaymentRequisitionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => PaymentRequisition::TYPE_REEMBOLSO,
            'issued_at' => now()->toDateString(),
            'requester_area' => 'Logística y Operaciones',
            'recipient_name' => 'Klaus Castro Pimentel',
            'recipient_role' => 'Gerente General',
            'subject' => 'Reembolso de útiles de escritorio',
            'beneficiary_name' => 'Johonny Gonzales',
            'bank_details' => "Banco: BBVA\nCuenta: 0011-0804-0203281650",
            'items' => [
                ['item_date' => now()->toDateString(), 'reference' => '003646', 'description' => 'Desatorador', 'quantity' => '1', 'unit_price' => '7.00', 'amount' => '7.00'],
            ],
        ], $overrides);
    }

    public function test_registering_a_requisition_assigns_the_first_sequence_of_the_year(): void
    {
        $user = $this->userWithPermissions(['rentals.requisitions.manage']);

        $response = $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload());

        $record = PaymentRequisition::first();
        $response->assertRedirect(route('rentals.requisitions.show', $record));
        $this->assertSame(1, $record->sequence);
        $this->assertSame((int) now()->year, $record->year);
    }

    public function test_sequences_increment_within_the_same_year(): void
    {
        $user = $this->userWithPermissions(['rentals.requisitions.manage']);

        $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload());
        $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload());

        $this->assertSame([1, 2], PaymentRequisition::orderBy('sequence')->pluck('sequence')->all());
    }

    public function test_items_with_a_blank_amount_are_skipped(): void
    {
        $user = $this->userWithPermissions(['rentals.requisitions.manage']);

        $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload([
            'items' => [
                ['description' => 'Con monto', 'amount' => '10.00'],
                ['description' => 'Sin monto', 'amount' => ''],
            ],
        ]));

        $this->assertSame(1, PaymentRequisition::first()->items()->count());
    }

    public function test_at_least_one_item_is_required(): void
    {
        $user = $this->userWithPermissions(['rentals.requisitions.manage']);

        $response = $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload(['items' => []]));

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, PaymentRequisition::count());
    }

    public function test_total_sums_every_item_amount(): void
    {
        $user = $this->userWithPermissions(['rentals.requisitions.manage']);

        $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload([
            'items' => [
                ['description' => 'Ítem 1', 'amount' => '10.00'],
                ['description' => 'Ítem 2', 'amount' => '15.50'],
            ],
        ]));

        $this->assertSame(25.5, PaymentRequisition::first()->total());
    }

    public function test_document_number_is_formatted_with_the_lgo_cch_suffix(): void
    {
        $record = PaymentRequisition::factory()->create(['year' => 2026, 'sequence' => 63]);

        $this->assertSame('000063-2026 LGO/CCH', $record->documentNumber());
    }

    public function test_create_form_prefills_the_default_recipient(): void
    {
        $user = $this->userWithPermissions(['rentals.requisitions.manage']);

        $response = $this->actingAs($user)->get('/rentals/requisitions/create');

        $response->assertOk()->assertSee('Klaus Castro Pimentel');
    }

    public function test_pdf_download_returns_a_real_pdf(): void
    {
        $user = $this->userWithPermissions(['rentals.requisitions.manage']);
        $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload());
        $record = PaymentRequisition::first();

        $response = $this->actingAs($user)->get(route('rentals.requisitions.pdf', $record));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_user_without_the_permission_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/rentals/requisitions')->assertForbidden();
        $this->actingAs($user)->get('/rentals/requisitions/create')->assertForbidden();
        $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload())->assertForbidden();
    }

    public function test_seeded_logistica_role_can_manage_requisitions_but_not_full_rentals(): void
    {
        $this->seed(RolesPermissionsModulesSeeder::class);
        $user = Role::where('name', 'Logística')->first()->users()->first();

        $this->actingAs($user)->get('/rentals/requisitions')->assertOk();
        $this->actingAs($user)->post('/rentals/requisitions', $this->validPayload())->assertRedirect();
        $this->actingAs($user)->get('/rentals/create')->assertForbidden();
    }
}

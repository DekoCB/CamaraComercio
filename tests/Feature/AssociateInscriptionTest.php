<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\AssociateDocument;
use App\Models\AssociateExecutive;
use App\Models\AssociateProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AssociateInscriptionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dermo8 Plástica S.R.L.',
            'ruc' => '20610881274',
            'legal_rep_name' => 'David Elías Yauri Larrazábal',
            'legal_rep_position' => 'Gerente',
            'main_activity' => 'COMERCIALIZADOR',
            'complementary_activities' => ['SERVICIOS'],
        ], $overrides);
    }

    public function test_the_form_opens_prefilled_with_the_associates_own_data(): void
    {
        $associate = Associate::factory()->create(['name' => 'Dermo8 Plástica S.R.L.', 'legal_rep_name' => 'David Elías Yauri']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->get("/associates/{$associate->id}/ficha-inscripcion");

        $response->assertOk()
            ->assertSee('value="Dermo8 Plástica S.R.L."', false)
            ->assertSee('value="David Elías Yauri"', false);
    }

    public function test_generating_the_ficha_updates_the_associate_and_creates_a_document(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create(['name' => 'Nombre Viejo SAC']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}/ficha-inscripcion", $this->validPayload([
            'name' => 'Dermo8 Plástica S.R.L.',
        ]));

        $response->assertRedirect(route('associates.show', $associate));
        $associate->refresh();
        $this->assertSame('Dermo8 Plástica S.R.L.', $associate->name);
        $this->assertSame('Gerente', $associate->legal_rep_position);
        $this->assertSame('COMERCIALIZADOR', $associate->main_activity);
        $this->assertSame(['SERVICIOS'], $associate->complementary_activities);

        $document = AssociateDocument::first();
        $this->assertNotNull($document);
        $this->assertSame(AssociateDocument::TYPE_FICHA_INSCRIPCION, $document->type);
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($document->file_path));
    }

    public function test_executives_and_products_are_saved_from_the_dynamic_rows(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $this->actingAs($user)->put("/associates/{$associate->id}/ficha-inscripcion", $this->validPayload([
            'executives' => [
                ['name' => 'Jonathan Josué Yauri', 'position' => 'Socio', 'phone' => '974772041', 'email' => 'jonathan@dermo8.pe'],
                ['name' => '', 'position' => 'fila vacía, se ignora'],
            ],
            'products' => [
                ['description' => 'Venta de productos farmacéuticos', 'is_comercializa' => '1', 'is_servicios' => '1'],
            ],
        ]));

        $this->assertSame(1, AssociateExecutive::count());
        $executive = AssociateExecutive::first();
        $this->assertSame('Jonathan Josué Yauri', $executive->name);
        $this->assertSame('Socio', $executive->position);
        $this->assertSame('jonathan@dermo8.pe', $executive->email);

        $product = AssociateProduct::first();
        $this->assertSame('Venta de productos farmacéuticos', $product->description);
        $this->assertTrue($product->is_comercializa);
        $this->assertTrue($product->is_servicios);
        $this->assertFalse($product->is_fabrica);
    }

    public function test_the_new_business_profile_and_internal_use_fields_are_saved(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}/ficha-inscripcion", $this->validPayload([
            'internal_code' => 'A-102',
            'anniversary_date' => '2015-03-20',
            'address_number' => '634',
            'address_lot_interior' => 'Int. 2',
            'address_reference' => 'Frente a la plaza principal',
            'mobile_phone' => '974772041',
            'fax' => '064-231432',
            'sector_economico' => 'Farmacéutico',
            'main_inputs' => 'Insumos químicos, envases',
            'main_suppliers' => 'Droguería Central',
            'employee_count_range' => '11 a 50',
            'assets_range' => 'De 25,001 a 50,000',
            'monthly_sales_range' => 'De 101 a 200',
            'annual_sales_range' => 'De 25,001 a 50,000',
            'trade_associations' => ['ADEX', 'CONFIEP'],
            'interested_services' => 'Capacitaciones',
            'category' => 'B',
            'registration_fee' => '150.00',
            'monthly_fee' => '80.00',
            'annual_fee' => '900.00',
            'registration_payment_method' => 'DEPÓSITO',
        ]));

        $response->assertRedirect(route('associates.show', $associate));
        $associate->refresh();
        $this->assertSame('A-102', $associate->internal_code);
        $this->assertSame('2015-03-20', $associate->anniversary_date->toDateString());
        $this->assertSame('634', $associate->address_number);
        $this->assertSame('11 a 50', $associate->employee_count_range);
        $this->assertSame(['ADEX', 'CONFIEP'], $associate->trade_associations);
        $this->assertSame(150.0, (float) $associate->registration_fee);
        $this->assertSame('DEPÓSITO', $associate->registration_payment_method);
    }

    public function test_range_and_trade_association_fields_reject_values_outside_the_closed_lists(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}/ficha-inscripcion", $this->validPayload([
            'employee_count_range' => 'un montón',
            'trade_associations' => ['NO_EXISTE'],
        ]));

        $response->assertSessionHasErrors(['employee_count_range', 'trade_associations.0']);
    }

    public function test_regenerating_the_ficha_replaces_the_previous_executives_and_products(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $this->actingAs($user)->put("/associates/{$associate->id}/ficha-inscripcion", $this->validPayload([
            'executives' => [['name' => 'Primero']],
        ]));
        $this->assertSame(1, AssociateExecutive::count());

        $this->actingAs($user)->put("/associates/{$associate->id}/ficha-inscripcion", $this->validPayload([
            'executives' => [['name' => 'Segundo'], ['name' => 'Tercero']],
        ]));

        $this->assertSame(2, AssociateExecutive::count());
        $this->assertSame(['Segundo', 'Tercero'], AssociateExecutive::orderBy('id')->pluck('name')->all());
    }

    public function test_ruc_must_stay_unique_when_generating_the_ficha(): void
    {
        Associate::factory()->create(['ruc' => '20610881274']);
        $associate = Associate::factory()->create(['ruc' => null]);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}/ficha-inscripcion", $this->validPayload([
            'ruc' => '20610881274',
        ]));

        $response->assertSessionHasErrors('ruc');
        $this->assertNull($associate->fresh()->ruc);
    }

    public function test_user_without_associates_manage_permission_is_forbidden(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get("/associates/{$associate->id}/ficha-inscripcion")->assertForbidden();
        $this->actingAs($user)->put("/associates/{$associate->id}/ficha-inscripcion", $this->validPayload())->assertForbidden();
    }

    public function test_associate_page_links_to_the_ficha_generator(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->get("/associates/{$associate->id}");

        $response->assertOk()->assertSee(route('associates.inscripcion.edit', $associate));
    }
}

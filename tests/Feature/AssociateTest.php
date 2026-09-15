<?php

namespace Tests\Feature;

use App\Models\Associate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AssociateTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_authorized_user_can_register_an_associate(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Comercial Andina SAC',
            'company' => 'Comercial Andina',
            'contact_phone' => '555-0101',
            'email' => 'contacto@andina.example.com',
        ]);

        $response->assertRedirect('/associates');
        $this->assertDatabaseHas('associates', ['name' => 'Comercial Andina SAC', 'is_active' => true]);
    }

    public function test_invalid_email_is_rejected_and_nothing_is_persisted(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Sin Correo Valido',
            'email' => 'esto-no-es-un-correo',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('associates', ['name' => 'Sin Correo Valido']);
    }

    public function test_blank_name_is_rejected(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', ['name' => '   ']);

        $response->assertSessionHasErrors('name');
    }

    public function test_duplicate_email_is_rejected_on_manual_create(): void
    {
        Associate::factory()->create(['email' => 'ya-existe@example.com']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Otro Asociado',
            'email' => 'ya-existe@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('associates', ['name' => 'Otro Asociado']);
    }

    public function test_multiple_associates_without_an_email_do_not_collide(): void
    {
        Associate::factory()->create(['email' => null]);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Sin Correo Tambien',
        ]);

        $response->assertRedirect('/associates');
        $this->assertDatabaseHas('associates', ['name' => 'Sin Correo Tambien']);
    }

    public function test_editing_an_associate_keeping_its_own_email_is_not_a_duplicate(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $associate = Associate::factory()->create(['email' => 'propio@example.com']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}", [
            'name' => $associate->name,
            'email' => 'propio@example.com',
        ]);

        $response->assertRedirect('/associates');
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_user_without_permission_cannot_create_an_associate(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/associates/create')->assertForbidden();
        $this->actingAs($user)->post('/associates', ['name' => 'Cualquiera'])->assertForbidden();
    }

    public function test_any_authenticated_user_can_browse_the_associate_list(): void
    {
        Associate::factory()->create(['name' => 'Visible Para Todos']);
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/associates')
            ->assertOk()
            ->assertSee('Visible Para Todos');
    }

    public function test_updating_an_associate_status_toggles_active_flag(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $associate = Associate::factory()->create(['is_active' => true]);

        $this->actingAs($user)->put("/associates/{$associate->id}", [
            'name' => $associate->name,
            'email' => $associate->email,
            'status' => Associate::STATUS_SUSPENDIDO,
        ]);

        $fresh = $associate->fresh();
        $this->assertSame(Associate::STATUS_SUSPENDIDO, $fresh->status);
        $this->assertFalse($fresh->is_active);

        $this->actingAs($user)->put("/associates/{$associate->id}", [
            'name' => $associate->name,
            'email' => $associate->email,
            'status' => Associate::STATUS_ACTIVO,
        ]);

        $this->assertTrue($associate->fresh()->is_active);
    }

    public function test_factory_flag_and_status_stay_coherent(): void
    {
        $inactive = Associate::factory()->create(['is_active' => false]);
        $unaffiliated = Associate::factory()->create(['status' => Associate::STATUS_DESAFILIADO]);

        $this->assertSame(Associate::STATUS_SUSPENDIDO, $inactive->fresh()->status);
        $this->assertFalse($unaffiliated->fresh()->is_active);
    }

    public function test_all_master_data_fields_are_persisted(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $payload = [
            'name' => '4OS GROUP ARQUITECTURA Y DESARROLLO S.A.C.',
            'status' => Associate::STATUS_ACTIVO,
            'sectorista' => 'ROSA',
            'category' => 'D',
            'monthly_fee' => '75.00',
            'joined_at' => '2018-09-25',
            'person_type' => 'PERSONA JURÍDICA',
            'anniversary_date' => '2017-07-01',
            'ruc' => '20602485146',
            'company' => '4OS GROUP',
            'email' => 'empresa@4os.example.com',
            'billing_address' => 'JR HUANCAS 269 SAN CARLOS',
            'billing_district' => 'EL TAMBO',
            'mailing_address' => 'JR. HUANCAS Y URUGUAY',
            'mailing_district' => 'HUANCAYO',
            'company_size' => 'MICROEMPRESAS',
            'activity_type' => 'SERVICIO',
            'sector_committee' => 'CONSTRUCCION E INMOBILIARIA / SALUD',
            'ciiu' => 'ACTIVIDADES DE MÉDICOS Y ODONTÓLOGOS',
            'sub_sector' => 'ACTIVIDADES DE ARQUITECTURA E INGENIERÍA',
            'legal_rep_name' => 'SAPAICO VARGAS MARIO OSMAN',
            'legal_rep_dni' => '20019748',
            'legal_rep_gender' => 'MASCULINO',
            'legal_rep_birthday' => '1967-05-10',
            'legal_rep_phone' => '954436988',
            'legal_rep_email' => 'mario@4os.example.com',
            'cch_rep_name' => 'OTRA PERSONA',
            'cch_rep_dni' => '11223344',
            'cch_rep_gender' => 'FEMENINO',
            'cch_rep_birthday' => '1980-01-15',
            'cch_rep_phone' => '999888777',
            'cch_rep_email' => 'otra@4os.example.com',
            'notes' => 'Paga siempre a inicio de mes.',
        ];

        $this->actingAs($user)->post('/associates', $payload)->assertRedirect('/associates');

        $associate = Associate::where('ruc', '20602485146')->firstOrFail();
        foreach ($payload as $field => $expected) {
            $actual = $associate->{$field};
            if ($actual instanceof \Carbon\CarbonInterface) {
                $actual = $actual->format('Y-m-d');
            }
            $this->assertEquals($expected, $actual, "Campo {$field}");
        }
        $this->assertTrue($associate->is_active);
    }

    public function test_unknown_catalog_values_are_rejected(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Catálogos Inválidos',
            'status' => 'BORRADO',
            'person_type' => 'ROBOT',
            'legal_rep_gender' => 'OTRO',
        ]);

        $response->assertSessionHasErrors(['status', 'person_type', 'legal_rep_gender']);
    }

    public function test_detail_page_shows_every_section(): void
    {
        $user = $this->userWithPermissions([]);
        $associate = Associate::factory()->create([
            'name' => 'Ficha Completa SAC',
            'legal_rep_name' => 'REPRESENTANTE LEGAL UNO',
            'cch_rep_name' => 'REPRESENTANTE CCH DOS',
            'sector_committee' => 'COMITE DE PRUEBA',
        ]);

        $this->actingAs($user)->get("/associates/{$associate->id}")
            ->assertOk()
            ->assertSee('Ficha Completa SAC')
            ->assertSee('REPRESENTANTE LEGAL UNO')
            ->assertSee('REPRESENTANTE CCH DOS')
            ->assertSee('COMITE DE PRUEBA')
            ->assertSee('Representante ante la CCH');
    }

    public function test_list_can_be_filtered_by_status(): void
    {
        $user = $this->userWithPermissions([]);
        Associate::factory()->create(['ruc' => '20100000111', 'status' => Associate::STATUS_ACTIVO]);
        Associate::factory()->create(['ruc' => '20100000222', 'status' => Associate::STATUS_SUSPENDIDO]);

        // RUC only appears in the table rows (the "Todos los asociados"
        // picker lists every associate by name regardless of the filter).
        $this->actingAs($user)->get('/associates?status=ACTIVO')
            ->assertOk()
            ->assertSee('20100000111')
            ->assertDontSee('20100000222');
    }

    public function test_list_can_be_filtered_by_sectorista_category_person_type_and_district(): void
    {
        $user = $this->userWithPermissions([]);
        Associate::factory()->create(['ruc' => '20100000001', 'sectorista' => 'ROSA', 'category' => 'D', 'person_type' => 'PERSONA JURÍDICA', 'billing_district' => 'EL TAMBO']);
        Associate::factory()->create(['ruc' => '20100000002', 'sectorista' => 'CARMEN', 'category' => 'E', 'person_type' => 'PERSONA NATURAL', 'billing_district' => 'HUANCAYO']);

        $this->actingAs($user)->get('/associates?sectorista=ROSA')->assertOk()->assertSee('20100000001')->assertDontSee('20100000002');
        $this->actingAs($user)->get('/associates?category=E')->assertOk()->assertSee('20100000002')->assertDontSee('20100000001');
        $this->actingAs($user)->get('/associates?person_type=PERSONA+NATURAL')->assertOk()->assertSee('20100000002')->assertDontSee('20100000001');
        $this->actingAs($user)->get('/associates?billing_district=EL+TAMBO')->assertOk()->assertSee('20100000001')->assertDontSee('20100000002');

        // Filters combine (AND) and an impossible combination yields the
        // filtered empty state rather than the "no associates yet" one.
        $this->actingAs($user)->get('/associates?sectorista=ROSA&category=E')
            ->assertOk()
            ->assertSee('No se encontraron resultados para los filtros seleccionados');
    }

    public function test_dropdown_options_and_active_chips_reflect_stored_values(): void
    {
        $user = $this->userWithPermissions([]);
        Associate::factory()->create(['sectorista' => 'ROSA', 'billing_district' => 'EL TAMBO']);
        Associate::factory()->create(['sectorista' => 'CARMEN']);

        $response = $this->actingAs($user)->get('/associates?sectorista=ROSA&status=ACTIVO');

        $response->assertOk()
            ->assertSee('Sectorista: todos')
            ->assertSee('<option value="CARMEN"', false)
            ->assertSee('<option value="EL TAMBO"', false)
            // one chip per active filter, each linking to the listing without it
            ->assertSee('1 resultado con:')
            ->assertSee(route('associates.index', ['status' => 'ACTIVO']), false)
            ->assertSee(route('associates.index', ['sectorista' => 'ROSA']), false);
    }

    public function test_associate_without_invoices_can_be_deleted(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $associate = Associate::factory()->create(['name' => 'Para Borrar SAC']);

        $response = $this->actingAs($user)->delete("/associates/{$associate->id}");

        $response->assertRedirect('/associates')->assertSessionHas('success');
        $this->assertDatabaseMissing('associates', ['id' => $associate->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'associate.delete', 'entity_id' => (string) $associate->id, 'result' => 'success']);
    }

    public function test_associate_with_invoices_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $associate = Associate::factory()->create();
        \App\Models\Invoice::factory()->for($associate)->create();

        $response = $this->actingAs($user)->from('/associates')->delete("/associates/{$associate->id}");

        $response->assertRedirect('/associates')->assertSessionHas('error');
        $this->assertDatabaseHas('associates', ['id' => $associate->id]);
    }

    public function test_deleting_requires_manage_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $associate = Associate::factory()->create();

        $this->actingAs($user)->delete("/associates/{$associate->id}")->assertForbidden();
        $this->assertDatabaseHas('associates', ['id' => $associate->id]);
    }

    public function test_search_filters_by_name_company_phone_or_email(): void
    {
        Associate::factory()->create(['name' => 'Unico Buscable XYZ']);
        Associate::factory()->create(['name' => 'Otro Distinto']);
        $user = $this->userWithPermissions([]);

        $response = $this->actingAs($user)->get('/associates?q=Buscable+XYZ');

        // assertDontSee against the whole page would also fail on the
        // filter's "Todos los asociados" dropdown, which always lists
        // every associate regardless of the current search — so this
        // checks the actual table results instead.
        $response->assertOk();
        $names = $response->viewData('associates')->pluck('name');
        $this->assertTrue($names->contains('Unico Buscable XYZ'));
        $this->assertFalse($names->contains('Otro Distinto'));
    }

    public function test_associate_can_be_filtered_by_selecting_it_from_the_list(): void
    {
        // Two associates can legitimately share a name (no uniqueness rule
        // beyond RUC — docs/OPEN_BUSINESS_DECISIONS.md pregunta 12), so
        // typing alone can't isolate one; selecting by id must.
        Associate::factory()->create(['name' => 'Comercial Andina SAC', 'company' => 'Comercial Andina']);
        $second = Associate::factory()->create(['name' => 'Comercial Andina SAC', 'company' => 'Andina']);
        $user = $this->userWithPermissions([]);

        $response = $this->actingAs($user)->get("/associates?associate_id={$second->id}");

        $response->assertOk();
        $this->assertSame(1, $response->viewData('associates')->total());
        $this->assertSame($second->id, $response->viewData('associates')->first()->id);
    }

    public function test_associate_can_be_registered_with_a_valid_ruc(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Comercial Andina SAC',
            'ruc' => '20123456789',
        ]);

        $response->assertRedirect('/associates');
        $this->assertDatabaseHas('associates', ['name' => 'Comercial Andina SAC', 'ruc' => '20123456789']);
    }

    public function test_ruc_is_optional(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', ['name' => 'Sin RUC Todavia']);

        $response->assertRedirect('/associates');
        $this->assertDatabaseHas('associates', ['name' => 'Sin RUC Todavia', 'ruc' => null]);
    }

    public function test_ruc_must_have_exactly_eleven_digits(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'RUC Invalido',
            'ruc' => '12345',
        ]);

        $response->assertSessionHasErrors('ruc');
        $this->assertDatabaseMissing('associates', ['name' => 'RUC Invalido']);
    }

    public function test_duplicate_ruc_is_rejected(): void
    {
        Associate::factory()->create(['ruc' => '20999999999']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', [
            'name' => 'Otro Asociado Con RUC Repetido',
            'ruc' => '20999999999',
        ]);

        $response->assertSessionHasErrors('ruc');
    }

    public function test_multiple_associates_without_a_ruc_do_not_collide(): void
    {
        Associate::factory()->create(['ruc' => null]);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates', ['name' => 'Sin RUC Tambien']);

        $response->assertRedirect('/associates');
        $response->assertSessionDoesntHaveErrors();
    }
}

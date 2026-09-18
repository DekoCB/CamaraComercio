<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\AssociateDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AssociateDeclarationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dermo8 Plástica S.R.L.',
            'legal_rep_name' => 'David Elías Yauri Larrazábal',
            'legal_rep_dni' => '70245917',
            'membership_status' => 'ASOCIADO',
            'declaration_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_the_form_opens_prefilled_and_warns_it_is_for_printing(): void
    {
        $associate = Associate::factory()->create(['name' => 'Dermo8 Plástica S.R.L.', 'legal_rep_name' => 'David Elías Yauri']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->get("/associates/{$associate->id}/declaracion-jurada");

        $response->assertOk()
            ->assertSee('value="Dermo8 Plástica S.R.L."', false)
            ->assertSee('value="David Elías Yauri"', false)
            ->assertSee('se genera para imprimir y firmar');
    }

    public function test_generating_the_declaration_updates_the_associate_and_creates_a_document(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create(['name' => 'Nombre Viejo SAC']);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}/declaracion-jurada", $this->validPayload([
            'name' => 'Dermo8 Plástica S.R.L.',
            'billing_district' => 'San Carlos',
            'billing_department' => 'Junín',
        ]));

        $response->assertRedirect(route('associates.show', $associate));
        $associate->refresh();
        $this->assertSame('Dermo8 Plástica S.R.L.', $associate->name);
        $this->assertSame('San Carlos', $associate->billing_district);
        $this->assertSame('Junín', $associate->billing_department);

        $document = AssociateDocument::first();
        $this->assertNotNull($document);
        $this->assertSame(AssociateDocument::TYPE_DECLARACION_JURADA, $document->type);
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($document->file_path));
    }

    public function test_membership_status_must_be_aspirante_or_asociado(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}/declaracion-jurada", $this->validPayload([
            'membership_status' => 'NO_ES_VALIDO',
        ]));

        $response->assertSessionHasErrors('membership_status');
    }

    public function test_declaration_date_cannot_be_in_the_future(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->put("/associates/{$associate->id}/declaracion-jurada", $this->validPayload([
            'declaration_date' => now()->addDays(5)->toDateString(),
        ]));

        $response->assertSessionHasErrors('declaration_date');
    }

    public function test_signature_and_fingerprint_are_optional_but_get_embedded_when_provided(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        // Without either — still generates successfully.
        $response = $this->actingAs($user)->put("/associates/{$associate->id}/declaracion-jurada", $this->validPayload());
        $response->assertRedirect();
        $this->assertSame(1, AssociateDocument::count());

        // With both provided.
        $response = $this->actingAs($user)->put("/associates/{$associate->id}/declaracion-jurada", array_merge(
            $this->validPayload(),
            [
                'signature' => UploadedFile::fake()->image('firma.png'),
                'fingerprint' => UploadedFile::fake()->image('huella.png'),
            ]
        ));
        $response->assertRedirect();
        $this->assertSame(2, AssociateDocument::count());
    }

    public function test_user_without_associates_manage_permission_is_forbidden(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get("/associates/{$associate->id}/declaracion-jurada")->assertForbidden();
        $this->actingAs($user)->put("/associates/{$associate->id}/declaracion-jurada", $this->validPayload())->assertForbidden();
    }

    public function test_associate_page_links_to_the_declaration_generator(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->get("/associates/{$associate->id}");

        $response->assertOk()->assertSee(route('associates.declaracion.edit', $associate));
    }
}

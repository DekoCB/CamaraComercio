<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Protest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ProtestTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => Protest::TYPE_PROTESTO,
            'channel' => Protest::CHANNEL_NOTARIAL,
            'instrument_type' => Protest::INSTRUMENT_LETRA_CAMBIO,
            'debtor_name' => 'Comercial El Rápido SAC',
            'debtor_document' => '20123456789',
            'creditor_name' => 'Distribuidora El Sol',
            'creditor_document' => '10456789123',
            'amount' => '85.00',
            'registered_at' => now()->toDateString(),
        ], $overrides);
    }

    public function test_registering_a_protest_creates_it_with_registrado_status(): void
    {
        $user = $this->userWithPermissions(['protests.manage']);

        $response = $this->actingAs($user)->post('/protests', $this->validPayload());

        $record = Protest::first();
        $response->assertRedirect(route('protests.show', $record));
        $this->assertSame(Protest::STATUS_REGISTRADO, $record->status);
        $this->assertSame('Comercial El Rápido SAC', $record->debtor_name);
    }

    public function test_an_associate_can_be_linked_as_the_requesting_creditor(): void
    {
        $user = $this->userWithPermissions(['protests.manage']);
        $associate = Associate::factory()->create();

        $this->actingAs($user)->post('/protests', $this->validPayload(['associate_id' => $associate->id]));

        $this->assertSame($associate->id, Protest::first()->associate_id);
    }

    public function test_the_requesting_associate_is_optional(): void
    {
        $user = $this->userWithPermissions(['protests.manage']);

        $response = $this->actingAs($user)->post('/protests', $this->validPayload());

        $response->assertRedirect();
        $this->assertNull(Protest::first()->associate_id);
    }

    public function test_registered_at_cannot_be_in_the_future(): void
    {
        $user = $this->userWithPermissions(['protests.manage']);

        $response = $this->actingAs($user)->post('/protests', $this->validPayload([
            'registered_at' => now()->addDays(3)->toDateString(),
        ]));

        $response->assertSessionHasErrors('registered_at');
        $this->assertSame(0, Protest::count());
    }

    public function test_regularizing_marks_it_regularizado_and_never_deletes_it(): void
    {
        $user = $this->userWithPermissions(['protests.manage']);
        $record = Protest::factory()->create();

        $response = $this->actingAs($user)->put("/protests/{$record->id}/regularize", ['notes' => 'Pagado el 20/09']);

        $response->assertRedirect(route('protests.show', $record));
        $record->refresh();
        $this->assertSame(Protest::STATUS_REGULARIZADO, $record->status);
        $this->assertSame('Pagado el 20/09', $record->regularization_notes);
        $this->assertNotNull($record->regularized_at);
        $this->assertDatabaseHas('protests', ['id' => $record->id]);
    }

    public function test_an_already_regularized_record_cannot_be_regularized_again(): void
    {
        $user = $this->userWithPermissions(['protests.manage']);
        $record = Protest::factory()->create(['status' => Protest::STATUS_REGULARIZADO]);

        $response = $this->actingAs($user)->put("/protests/{$record->id}/regularize");

        $response->assertSessionHasErrors('status');
    }

    public function test_index_shows_the_monthly_count_summary(): void
    {
        $user = $this->userWithPermissions(['protests.view']);
        Protest::factory()->count(3)->create(['registered_at' => now()]);
        Protest::factory()->create(['registered_at' => now()->subMonths(2)]);

        $response = $this->actingAs($user)->get('/protests');

        $response->assertOk();
        $response->assertSee('3', false);
    }

    public function test_user_without_protests_view_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);
        $record = Protest::factory()->create();

        $this->actingAs($user)->get('/protests')->assertForbidden();
        $this->actingAs($user)->get("/protests/{$record->id}")->assertForbidden();
    }

    public function test_user_with_view_but_not_manage_cannot_write(): void
    {
        $user = $this->userWithPermissions(['protests.view']);
        $record = Protest::factory()->create();

        $this->actingAs($user)->get('/protests/create')->assertForbidden();
        $this->actingAs($user)->post('/protests', $this->validPayload())->assertForbidden();
        $this->actingAs($user)->put("/protests/{$record->id}/regularize")->assertForbidden();
    }
}

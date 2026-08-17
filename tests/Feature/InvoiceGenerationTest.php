<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'period' => '2026-08',
            'amount' => '150.00',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'confirm' => '1',
        ], $overrides);
    }

    public function test_generates_one_invoice_per_active_associate(): void
    {
        Associate::factory()->count(3)->create(['is_active' => true]);
        Associate::factory()->create(['is_active' => false]);
        $user = $this->userWithPermissions(['billing.generate']);

        $response = $this->actingAs($user)->post('/invoices/generate', $this->validPayload());

        $response->assertRedirect();
        $this->assertSame(3, Invoice::forPeriod('2026-08')->count());
    }

    public function test_generating_the_same_period_twice_skips_existing_invoices_without_duplicating(): void
    {
        $associate = Associate::factory()->create(['is_active' => true]);
        $user = $this->userWithPermissions(['billing.generate']);

        $this->actingAs($user)->post('/invoices/generate', $this->validPayload());
        $this->actingAs($user)->post('/invoices/generate', $this->validPayload(['amount' => '999.00']));

        // Still exactly one invoice for that associate/period — the second
        // run must not duplicate it nor overwrite its original amount.
        $invoices = Invoice::where('associate_id', $associate->id)->forPeriod('2026-08')->get();
        $this->assertCount(1, $invoices);
        $this->assertSame('150.00', $invoices->first()->amount);
    }

    public function test_new_associates_get_invoiced_on_a_later_run_for_the_same_period(): void
    {
        Associate::factory()->create(['is_active' => true]);
        $user = $this->userWithPermissions(['billing.generate']);
        $this->actingAs($user)->post('/invoices/generate', $this->validPayload());

        Associate::factory()->create(['is_active' => true]);
        $this->actingAs($user)->post('/invoices/generate', $this->validPayload());

        $this->assertSame(2, Invoice::forPeriod('2026-08')->count());
    }

    public function test_user_without_billing_generate_permission_is_forbidden(): void
    {
        $user = $this->userWithPermissions(['billing.view']);

        $this->actingAs($user)->get('/invoices/generate')->assertForbidden();
        $this->actingAs($user)->post('/invoices/generate', $this->validPayload())->assertForbidden();
    }

    public function test_rejects_non_positive_amount(): void
    {
        $user = $this->userWithPermissions(['billing.generate']);

        $response = $this->actingAs($user)->post('/invoices/generate', $this->validPayload(['amount' => '0']));

        $response->assertSessionHasErrors('amount');
    }

    public function test_rejects_due_date_before_issue_date(): void
    {
        $user = $this->userWithPermissions(['billing.generate']);

        $response = $this->actingAs($user)->post('/invoices/generate', $this->validPayload([
            'issue_date' => '2026-08-15',
            'due_date' => '2026-08-01',
        ]));

        $response->assertSessionHasErrors('due_date');
    }

    public function test_requires_explicit_confirmation(): void
    {
        $user = $this->userWithPermissions(['billing.generate']);

        $response = $this->actingAs($user)->post('/invoices/generate', $this->validPayload(['confirm' => null]));

        $response->assertSessionHasErrors('confirm');
    }
}

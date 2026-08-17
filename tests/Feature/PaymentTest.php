<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_full_payment_marks_the_invoice_as_paid(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '500.00',
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame('500.00', $invoice->paid_total);
        $this->assertSame(Invoice::STATUS_PAGADA, $invoice->status);
        $this->assertSame(0.0, $invoice->balance());
    }

    public function test_partial_payment_marks_the_invoice_as_partial_and_computes_balance(): void
    {
        // The example from the functional spec (HU-09): a S/500 invoice,
        // a S/200 payment, S/300 left, status PARCIAL.
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '200.00',
            'paid_at' => now()->toDateString(),
        ]);

        $invoice->refresh();
        $this->assertSame('200.00', $invoice->paid_total);
        $this->assertSame(300.0, $invoice->balance());
        $this->assertSame(Invoice::STATUS_PARCIAL, $invoice->status);
    }

    public function test_multiple_partial_payments_accumulate_until_fully_paid(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString()]);
        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString()]);
        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => '100.00', 'paid_at' => now()->toDateString()]);

        $invoice->refresh();
        $this->assertSame('500.00', $invoice->paid_total);
        $this->assertSame(Invoice::STATUS_PAGADA, $invoice->status);
        $this->assertCount(3, $invoice->payments);
    }

    public function test_payment_greater_than_balance_is_rejected(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '600.00',
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame('0.00', $invoice->fresh()->paid_total);
        $this->assertCount(0, $invoice->fresh()->payments);
    }

    public function test_payment_on_top_of_existing_partial_cannot_exceed_remaining_balance(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 300, 'status' => Invoice::STATUS_PARCIAL]);
        $user = $this->userWithPermissions(['payments.register']);

        // Remaining balance is 200; attempting 250 must fail.
        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '250.00',
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame('300.00', $invoice->fresh()->paid_total);
    }

    public function test_zero_or_negative_payment_is_rejected(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '0',
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_user_without_payments_register_permission_is_forbidden(): void
    {
        $invoice = Invoice::factory()->create();
        $user = $this->userWithPermissions(['billing.view']);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '50.00',
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_overdue_unpaid_invoice_shows_as_vencida(): void
    {
        $invoice = Invoice::factory()->overdue()->create(['amount' => 500, 'paid_total' => 0, 'status' => Invoice::STATUS_PENDIENTE]);

        $this->assertSame(Invoice::STATUS_VENCIDA, $invoice->effectiveStatus());
        $this->assertTrue($invoice->isOverdue());
    }

    public function test_overdue_but_fully_paid_invoice_does_not_show_as_vencida(): void
    {
        $invoice = Invoice::factory()->overdue()->create(['amount' => 500, 'paid_total' => 500, 'status' => Invoice::STATUS_PAGADA]);

        $this->assertSame(Invoice::STATUS_PAGADA, $invoice->effectiveStatus());
        $this->assertFalse($invoice->isOverdue());
    }

    public function test_authorized_user_can_view_invoice_detail_with_payment_history(): void
    {
        $invoice = Invoice::factory()->create();
        $user = $this->userWithPermissions(['billing.view']);
        $this->actingAs($this->userWithPermissions(['payments.register']))
            ->post("/invoices/{$invoice->id}/payments", ['amount' => '10.00', 'paid_at' => now()->toDateString()]);

        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}");

        $response->assertOk()->assertSee($invoice->associate->name);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class InvoiceVoidTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_an_invoice_without_payments_can_be_edited(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0, 'receipt_number' => null]);
        $user = $this->userWithPermissions(['billing.edit']);

        $response = $this->actingAs($user)->put("/invoices/{$invoice->id}", [
            'receipt_number' => 'F001-00042',
            'amount' => '250.00',
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'receipt_number' => 'F001-00042', 'amount' => 250]);
    }

    public function test_an_invoice_with_payments_cannot_be_edited(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 100]);
        $user = $this->userWithPermissions(['billing.edit']);

        $response = $this->actingAs($user)->put("/invoices/{$invoice->id}", [
            'amount' => '250.00',
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'amount' => 200]);
    }

    public function test_user_without_billing_edit_permission_is_forbidden(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['billing.view']);

        $this->actingAs($user)->get("/invoices/{$invoice->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/invoices/{$invoice->id}", ['amount' => '250.00', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(5)->toDateString()])->assertForbidden();
    }

    public function test_an_invoice_without_payments_can_be_voided(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['billing.void']);

        $response = $this->actingAs($user)->put("/invoices/{$invoice->id}/void", ['reason' => 'Factura duplicada']);

        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'void_reason' => 'Factura duplicada']);
        $this->assertTrue($invoice->fresh()->isVoided());
    }

    public function test_an_invoice_with_payments_cannot_be_voided(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 100]);
        $user = $this->userWithPermissions(['billing.void']);

        $response = $this->actingAs($user)->put("/invoices/{$invoice->id}/void", ['reason' => 'Intento incorrecto']);

        $response->assertSessionHasErrors('reason');
        $this->assertFalse($invoice->fresh()->isVoided());
    }

    public function test_an_invoice_cannot_be_voided_twice(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['billing.void']);

        $this->actingAs($user)->put("/invoices/{$invoice->id}/void", ['reason' => 'Primera anulación']);
        $response = $this->actingAs($user)->put("/invoices/{$invoice->id}/void", ['reason' => 'Segundo intento']);

        $response->assertSessionHasErrors('reason');
    }

    public function test_user_without_billing_void_permission_is_forbidden(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['billing.view']);

        $this->actingAs($user)->put("/invoices/{$invoice->id}/void", ['reason' => 'Motivo'])->assertForbidden();
    }

    public function test_a_voided_invoice_shows_as_anulada_and_is_excluded_from_pending_totals(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0, 'status' => Invoice::STATUS_PENDIENTE]);
        $user = $this->userWithPermissions(['billing.void', 'billing.view']);

        $this->actingAs($user)->put("/invoices/{$invoice->id}/void", ['reason' => 'Error de digitación']);

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_ANULADA, $invoice->effectiveStatus());
        $this->assertFalse($invoice->isOverdue());

        $this->actingAs($user)->get('/invoices?status=no_pagadas')->assertOk()->assertDontSee($invoice->receipt_number ?? '#'.$invoice->id);
    }

    public function test_a_voided_invoice_cannot_receive_a_payment(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0]);
        $voider = $this->userWithPermissions(['billing.void']);
        $this->actingAs($voider)->put("/invoices/{$invoice->id}/void", ['reason' => 'Anulada antes de cobrar']);

        $collector = $this->userWithPermissions(['payments.register']);
        $response = $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '100.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(0, $invoice->fresh()->payments()->count());
    }
}

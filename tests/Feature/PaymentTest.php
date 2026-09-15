<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
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
            'method' => 'EFECTIVO',
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
            'method' => 'EFECTIVO',
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

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => '100.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);

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
            'method' => 'EFECTIVO',
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
            'method' => 'EFECTIVO',
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
            'method' => 'EFECTIVO',
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
            'method' => 'EFECTIVO',
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
            ->post("/invoices/{$invoice->id}/payments", ['amount' => '10.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);

        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}");

        $response->assertOk()->assertSee($invoice->associate->name);
    }

    public function test_voiding_a_payment_excludes_it_from_the_invoice_balance(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $collector = $this->userWithPermissions(['payments.register']);
        $admin = $this->userWithPermissions(['payments.void']);

        $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $payment = $invoice->fresh()->payments->first();

        $response = $this->actingAs($admin)->put("/payments/{$payment->id}/void", ['reason' => 'Monto ingresado por error']);

        $response->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $payment->refresh();
        $this->assertTrue($payment->isVoided());
        $this->assertSame('Monto ingresado por error', $payment->void_reason);
        $this->assertSame($admin->id, $payment->voided_by);
        $this->assertSame('0.00', $invoice->paid_total);
        $this->assertSame(Invoice::STATUS_PENDIENTE, $invoice->status);
    }

    public function test_voided_payment_recalculates_status_back_from_pagada_to_parcial(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $collector = $this->userWithPermissions(['payments.register']);
        $admin = $this->userWithPermissions(['payments.void']);

        $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", ['amount' => '300.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $this->assertSame(Invoice::STATUS_PAGADA, $invoice->fresh()->status);

        $lastPayment = $invoice->fresh()->payments->sortByDesc('id')->first();
        $this->actingAs($admin)->put("/payments/{$lastPayment->id}/void", ['reason' => 'Duplicado']);

        $invoice->refresh();
        $this->assertSame('300.00', $invoice->paid_total);
        $this->assertSame(Invoice::STATUS_PARCIAL, $invoice->status);
    }

    public function test_a_payment_cannot_be_voided_twice(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $collector = $this->userWithPermissions(['payments.register']);
        $admin = $this->userWithPermissions(['payments.void']);

        $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $payment = $invoice->fresh()->payments->first();

        $this->actingAs($admin)->put("/payments/{$payment->id}/void", ['reason' => 'Primera anulación']);
        $response = $this->actingAs($admin)->put("/payments/{$payment->id}/void", ['reason' => 'Segundo intento']);

        $response->assertSessionHasErrors('reason');
    }

    public function test_voiding_a_payment_requires_a_reason(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $collector = $this->userWithPermissions(['payments.register']);
        $admin = $this->userWithPermissions(['payments.void']);

        $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $payment = $invoice->fresh()->payments->first();

        $response = $this->actingAs($admin)->put("/payments/{$payment->id}/void", []);

        $response->assertSessionHasErrors('reason');
        $this->assertFalse($payment->fresh()->isVoided());
    }

    public function test_user_without_payments_void_permission_is_forbidden(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $collector = $this->userWithPermissions(['payments.register']);

        $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $payment = $invoice->fresh()->payments->first();

        $response = $this->actingAs($collector)->put("/payments/{$payment->id}/void", ['reason' => 'Intento no autorizado']);

        $response->assertForbidden();
    }

    public function test_voided_payments_are_excluded_from_dashboard_and_report_collections(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $collector = $this->userWithPermissions(['payments.register']);
        $admin = $this->userWithPermissions(['payments.void', 'reports.view']);

        $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $payment = $invoice->fresh()->payments->first();
        $this->actingAs($admin)->put("/payments/{$payment->id}/void", ['reason' => 'Error de digitación']);

        $collected = Payment::active()->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $this->assertSame(0.0, (float) $collected);
    }

    public function test_payment_can_be_registered_from_the_payments_tab_by_picking_an_invoice(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $response = $this->actingAs($user)->post('/payments', [
            'invoice_id' => $invoice->id,
            'amount' => '200.00',
            'paid_at' => now()->toDateString(),
            'method' => 'EFECTIVO',
        ]);

        $response->assertRedirect(route('payments.index'));
        $invoice->refresh();
        $this->assertSame('200.00', $invoice->paid_total);
        $this->assertCount(1, $invoice->payments);
    }

    public function test_quick_payment_requires_an_invoice(): void
    {
        $user = $this->userWithPermissions(['payments.register']);

        $response = $this->actingAs($user)->post('/payments', [
            'amount' => '200.00',
            'paid_at' => now()->toDateString(),
            'method' => 'EFECTIVO',
        ]);

        $response->assertSessionHasErrors('invoice_id');
    }

    public function test_quick_payment_form_only_lists_invoices_with_a_pending_balance(): void
    {
        $pending = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $paid = Invoice::factory()->create(['amount' => 500, 'paid_total' => 500, 'status' => Invoice::STATUS_PAGADA]);
        $user = $this->userWithPermissions(['payments.register']);

        $response = $this->actingAs($user)->get('/payments/create');

        $response->assertOk();
        $invoices = $response->viewData('invoices');
        $this->assertTrue($invoices->contains('id', $pending->id));
        $this->assertFalse($invoices->contains('id', $paid->id));
    }

    public function test_payments_index_lists_cuotas_and_filters_paid_versus_unpaid(): void
    {
        $paidAssociate = Associate::factory()->create(['name' => 'MINERA CENTRO S.A.C.', 'ruc' => '20130330054', 'sectorista' => 'CARMEN']);
        $unpaidAssociate = Associate::factory()->create(['name' => 'Ferretería Central', 'ruc' => '20100000009', 'sectorista' => 'ROSA']);
        $paid = Invoice::factory()->create([
            'associate_id' => $paidAssociate->id, 'period' => '2026-08', 'receipt_number' => 'FE01-001236',
            'amount' => 50, 'paid_total' => 50, 'status' => Invoice::STATUS_PAGADA,
        ]);
        Payment::factory()->create(['invoice_id' => $paid->id, 'amount' => 50, 'paid_at' => '2026-08-01']);
        $unpaid = Invoice::factory()->create(['associate_id' => $unpaidAssociate->id, 'period' => '2026-08', 'amount' => 250, 'paid_total' => 0]);
        $partial = Invoice::factory()->create(['associate_id' => $unpaidAssociate->id, 'period' => '2026-07', 'amount' => 250, 'paid_total' => 100, 'status' => Invoice::STATUS_PARCIAL]);
        $user = $this->userWithPermissions(['payments.register', 'billing.view']);

        $all = $this->actingAs($user)->get('/payments');
        $all->assertOk()->assertSee('MINERA CENTRO S.A.C.')->assertSee('Ferretería Central')->assertSee('FE01-001236');
        $this->assertSame(3, $all->viewData('summary')['total']);
        $this->assertSame(1, $all->viewData('summary')['paid_count']);
        $this->assertSame(2, $all->viewData('summary')['unpaid_count']);
        $this->assertSame(400.0, $all->viewData('summary')['balance']);

        $onlyPaid = $this->actingAs($user)->get('/payments?status=pagadas');
        $this->assertSame([$paid->id], $onlyPaid->viewData('invoices')->pluck('id')->all());

        $onlyUnpaid = $this->actingAs($user)->get('/payments?status=no_pagadas');
        $this->assertEqualsCanonicalizing([$unpaid->id, $partial->id], $onlyUnpaid->viewData('invoices')->pluck('id')->all());

        $onlyPartial = $this->actingAs($user)->get('/payments?status=parciales');
        $this->assertSame([$partial->id], $onlyPartial->viewData('invoices')->pluck('id')->all());

        $byMonth = $this->actingAs($user)->get('/payments?year=2026&month=7');
        $this->assertSame([$partial->id], $byMonth->viewData('invoices')->pluck('id')->all());

        $bySectorista = $this->actingAs($user)->get('/payments?sectorista=CARMEN');
        $this->assertSame([$paid->id], $bySectorista->viewData('invoices')->pluck('id')->all());
    }

    public function test_payments_index_searches_associates_by_name_ruc_or_receipt_number(): void
    {
        $minera = Associate::factory()->create(['name' => 'MINERA CENTRO S.A.C.', 'ruc' => '20130330054', 'company' => 'MICENSAC']);
        $other = Associate::factory()->create(['name' => 'Comercial Andina SAC', 'ruc' => '20100000001']);
        $target = Invoice::factory()->create(['associate_id' => $minera->id, 'period' => '2026-08', 'receipt_number' => 'FE01-001236', 'amount' => 50]);
        Invoice::factory()->create(['associate_id' => $other->id, 'period' => '2026-08', 'receipt_number' => 'F020-00000001', 'amount' => 50]);
        $user = $this->userWithPermissions(['payments.register']);

        foreach (['MINERA', '20130330054', 'MICENSAC', 'FE01-001236'] as $term) {
            $response = $this->actingAs($user)->get('/payments?q='.$term);
            $this->assertSame([$target->id], $response->viewData('invoices')->pluck('id')->all(), "search term: {$term}");
        }

        $this->assertCount(0, $this->actingAs($user)->get('/payments?q=inexistente')->viewData('invoices'));
    }

    public function test_payments_index_movements_tab_lists_payments_with_their_state(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 100, 'paid_total' => 50, 'status' => Invoice::STATUS_PARCIAL]);
        $valid = Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 50, 'paid_at' => '2026-08-10']);
        $voided = Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 20, 'paid_at' => '2026-07-10', 'voided_at' => now(), 'void_reason' => 'Error']);
        $user = $this->userWithPermissions(['payments.register']);

        $all = $this->actingAs($user)->get('/payments?tab=movimientos');
        $all->assertOk()->assertSee('Válido')->assertSee('Anulado');
        $this->assertEqualsCanonicalizing([$valid->id, $voided->id], $all->viewData('payments')->pluck('id')->all());

        $onlyValid = $this->actingAs($user)->get('/payments?tab=movimientos&state=validos');
        $this->assertSame([$valid->id], $onlyValid->viewData('payments')->pluck('id')->all());

        $byDate = $this->actingAs($user)->get('/payments?tab=movimientos&date_from=2026-08-01');
        $this->assertSame([$valid->id], $byDate->viewData('payments')->pluck('id')->all());
    }

    public function test_quick_payment_form_preselects_the_invoice_from_the_cuotas_list(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $response = $this->actingAs($user)->get('/payments/create?invoice_id='.$invoice->id);

        $response->assertOk();
        $this->assertSame($invoice->id, $response->viewData('selectedInvoiceId'));
    }

    public function test_user_without_payments_register_permission_cannot_use_the_quick_payment_form(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['billing.view']);

        $this->actingAs($user)->get('/payments/create')->assertForbidden();
        $this->actingAs($user)->post('/payments', [
            'invoice_id' => $invoice->id,
            'amount' => '200.00',
            'paid_at' => now()->toDateString(),
            'method' => 'EFECTIVO',
        ])->assertForbidden();
    }
}

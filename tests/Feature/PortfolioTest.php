<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_portfolio_index_shows_invoiced_paid_and_pending_totals_per_associate(): void
    {
        $associate = Associate::factory()->create(['name' => 'Deudor Parcial']);
        $invoice = Invoice::factory()->for($associate)->create(['amount' => 500, 'paid_total' => 200, 'status' => Invoice::STATUS_PARCIAL]);
        Payment::factory()->for($invoice)->create(['amount' => 200]);

        $user = $this->userWithPermissions(['portfolio.view']);

        $response = $this->actingAs($user)->get('/portfolio');

        $response->assertOk()
            ->assertSee('Deudor Parcial')
            ->assertSee('S/ 500.00')
            ->assertSee('S/ 200.00')
            ->assertSee('S/ 300.00');
    }

    public function test_debtors_list_only_includes_associates_with_pending_balance(): void
    {
        $debtor = Associate::factory()->create(['name' => 'Debe Todavia']);
        Invoice::factory()->for($debtor)->create(['amount' => 300, 'paid_total' => 100, 'status' => Invoice::STATUS_PARCIAL]);

        $paidUp = Associate::factory()->create(['name' => 'Al Dia']);
        Invoice::factory()->for($paidUp)->create(['amount' => 300, 'paid_total' => 300, 'status' => Invoice::STATUS_PAGADA]);

        $withoutInvoices = Associate::factory()->create(['name' => 'Sin Facturas']);

        $user = $this->userWithPermissions(['portfolio.view']);

        $response = $this->actingAs($user)->get('/portfolio/debtors');

        $response->assertOk()
            ->assertSee('Debe Todavia')
            ->assertDontSee('Al Dia')
            ->assertDontSee('Sin Facturas');
    }

    public function test_debtors_list_reports_the_oldest_unpaid_period(): void
    {
        $associate = Associate::factory()->create();
        Invoice::factory()->for($associate)->create(['period' => '2026-05', 'amount' => 100, 'paid_total' => 0, 'status' => Invoice::STATUS_PENDIENTE]);
        Invoice::factory()->for($associate)->create(['period' => '2026-07', 'amount' => 100, 'paid_total' => 0, 'status' => Invoice::STATUS_PENDIENTE]);

        $user = $this->userWithPermissions(['portfolio.view']);

        $response = $this->actingAs($user)->get('/portfolio/debtors');

        $response->assertOk()->assertSee('2026-05');
    }

    public function test_statement_shows_associate_totals_and_invoice_history(): void
    {
        $associate = Associate::factory()->create(['name' => 'Historial Asociado']);
        Invoice::factory()->for($associate)->create(['period' => '2026-06', 'amount' => 100, 'paid_total' => 100, 'status' => Invoice::STATUS_PAGADA]);
        Invoice::factory()->for($associate)->create(['period' => '2026-07', 'amount' => 150, 'paid_total' => 0, 'status' => Invoice::STATUS_PENDIENTE]);

        $user = $this->userWithPermissions(['portfolio.view']);

        $response = $this->actingAs($user)->get("/associates/{$associate->id}/statement");

        $response->assertOk()
            ->assertSee('Historial Asociado')
            ->assertSee('S/ 250.00') // total facturado
            ->assertSee('2026-06')
            ->assertSee('2026-07');
    }

    public function test_portfolio_routes_require_portfolio_view_permission(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['billing.view']);

        $this->actingAs($user)->get('/portfolio')->assertForbidden();
        $this->actingAs($user)->get('/portfolio/debtors')->assertForbidden();
        $this->actingAs($user)->get("/associates/{$associate->id}/statement")->assertForbidden();
    }
}

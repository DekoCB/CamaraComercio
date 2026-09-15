<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PortfolioLedgerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function seedLedger(): array
    {
        $rosa = Associate::factory()->create(['name' => 'Cliente Yape SAC', 'ruc' => '20100000001', 'sectorista' => 'ROSA']);
        $carmen = Associate::factory()->create(['name' => 'Cliente Efectivo SAC', 'ruc' => '20100000002', 'sectorista' => 'CARMEN']);
        $i1 = Invoice::factory()->for($rosa)->create(['period' => '2026-08', 'amount' => 100, 'paid_total' => 100, 'status' => Invoice::STATUS_PAGADA, 'receipt_number' => 'FE01-000111']);
        $i2 = Invoice::factory()->for($carmen)->create(['period' => '2026-07', 'amount' => 100, 'paid_total' => 60, 'status' => Invoice::STATUS_PARCIAL, 'due_date' => '2026-07-31']);
        Payment::factory()->create(['invoice_id' => $i1->id, 'amount' => 100, 'paid_at' => '2026-08-10 10:00:00', 'method' => 'YAPE']);
        Payment::factory()->create(['invoice_id' => $i2->id, 'amount' => 60, 'paid_at' => '2026-07-05 10:00:00', 'method' => 'EFECTIVO']);
        Payment::factory()->create(['invoice_id' => $i2->id, 'amount' => 40, 'paid_at' => '2026-07-06 10:00:00', 'method' => 'EFECTIVO', 'voided_at' => now(), 'void_reason' => 'Duplicado']);

        return [$rosa, $carmen];
    }

    public function test_ledger_tab_lists_payments_with_totals_and_method_breakdown(): void
    {
        $this->seedLedger();
        $user = $this->userWithPermissions(['portfolio.view']);

        $this->actingAs($user)->get('/portfolio/payments')
            ->assertOk()
            ->assertSee('Historial de pagos')
            ->assertSee('Cliente Yape SAC')
            ->assertSee('Cliente Efectivo SAC')
            ->assertSee('S/ 160.00')   // valid total (voided excluded)
            ->assertSee('2 pago(s) válido(s)')
            ->assertSee('Duplicado');  // voided row shows its reason
    }

    public function test_ledger_filters_by_method_month_dates_state_and_search(): void
    {
        $this->seedLedger();
        $user = $this->userWithPermissions(['portfolio.view']);

        $this->actingAs($user)->get('/portfolio/payments?method=YAPE')->assertOk()->assertSee('Cliente Yape SAC')->assertDontSee('Cliente Efectivo SAC');
        $this->actingAs($user)->get('/portfolio/payments?year=2026&month=7')->assertOk()->assertSee('Cliente Efectivo SAC')->assertDontSee('Cliente Yape SAC');
        $this->actingAs($user)->get('/portfolio/payments?date_from=2026-08-01&date_to=2026-08-31')->assertOk()->assertSee('Cliente Yape SAC')->assertDontSee('Cliente Efectivo SAC');
        $this->actingAs($user)->get('/portfolio/payments?state=anulados')->assertOk()->assertSee('1 movimiento(s) con:')->assertSee('Anulado');
        $this->actingAs($user)->get('/portfolio/payments?q=FE01-000111')->assertOk()->assertSee('Cliente Yape SAC')->assertDontSee('Cliente Efectivo SAC');
        $this->actingAs($user)->get('/portfolio/payments?sectorista=CARMEN')->assertOk()->assertSee('Cliente Efectivo SAC')->assertDontSee('Cliente Yape SAC');

        // Live filtering returns just the results block
        $this->actingAs($user)->get('/portfolio/payments?method=YAPE', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertDontSee('<html', false)->assertSee('Cliente Yape SAC');
    }

    public function test_portfolio_index_filters_by_situation_and_shows_kpis(): void
    {
        $this->seedLedger();
        $user = $this->userWithPermissions(['portfolio.view']);

        $this->actingAs($user)->get('/portfolio?status=con_deuda')->assertOk()->assertSee('20100000002')->assertDontSee('20100000001');
        $this->actingAs($user)->get('/portfolio?status=al_dia')->assertOk()->assertSee('20100000001')->assertDontSee('20100000002');
        $this->actingAs($user)->get('/portfolio?status=con_vencidas')->assertOk()->assertSee('20100000002')->assertDontSee('20100000001');
        $this->actingAs($user)->get('/portfolio')->assertOk()->assertSee('S/ 200.00')->assertSee('S/ 160.00')->assertSee('S/ 40.00')->assertSee('80.0 % de lo facturado');
    }

    public function test_statement_shows_payment_history_and_filters_by_year(): void
    {
        [, $carmen] = $this->seedLedger();
        Invoice::factory()->for($carmen)->create(['period' => '2025-12', 'amount' => 50, 'paid_total' => 50, 'status' => Invoice::STATUS_PAGADA]);
        $user = $this->userWithPermissions(['portfolio.view']);

        $this->actingAs($user)->get("/associates/{$carmen->id}/statement")
            ->assertOk()->assertSee('Historial de pagos')->assertSee('Efectivo')->assertSee('Anulado')->assertSee('Dic 2025');

        $this->actingAs($user)->get("/associates/{$carmen->id}/statement?year=2026")
            ->assertOk()->assertSee('Año 2026')->assertDontSee('Dic 2025');
    }

    public function test_ledger_requires_portfolio_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $this->actingAs($user)->get('/portfolio/payments')->assertForbidden();
    }
}

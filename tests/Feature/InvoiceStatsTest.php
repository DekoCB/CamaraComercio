<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Services\InvoiceStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class InvoiceStatsTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function seedInvoices(): void
    {
        $rosa = Associate::factory()->create(['name' => 'Deudor Grande SAC', 'sectorista' => 'ROSA', 'category' => 'D']);
        $carmen = Associate::factory()->create(['name' => 'Al Dia SAC', 'sectorista' => 'CARMEN', 'category' => 'E']);

        // 2026: one paid, one partial (overdue), one pending (not yet due)
        Invoice::factory()->for($carmen)->create(['period' => '2026-07', 'amount' => 100, 'paid_total' => 100, 'status' => Invoice::STATUS_PAGADA, 'due_date' => '2026-07-31']);
        Invoice::factory()->for($rosa)->create(['period' => '2026-08', 'amount' => 250, 'paid_total' => 50, 'status' => Invoice::STATUS_PARCIAL, 'due_date' => '2026-08-31']);
        Invoice::factory()->for($rosa)->create(['period' => '2026-09', 'amount' => 250, 'paid_total' => 0, 'status' => Invoice::STATUS_PENDIENTE, 'due_date' => now()->addDays(10)->toDateString()]);
        // 2025: paid — must be excluded when filtering by year 2026
        Invoice::factory()->for($rosa)->create(['period' => '2025-12', 'amount' => 80, 'paid_total' => 80, 'status' => Invoice::STATUS_PAGADA, 'due_date' => '2025-12-31']);
    }

    public function test_service_aggregates_the_selected_year(): void
    {
        $this->seedInvoices();

        $stats = app(InvoiceStatsService::class)->build(['year' => '2026']);

        $this->assertSame(3, $stats['summary']['total']);
        $this->assertSame(600.0, $stats['summary']['billed']);
        $this->assertSame(150.0, $stats['summary']['paid']);
        $this->assertSame(450.0, $stats['summary']['balance']);
        $this->assertSame(1, $stats['summary']['overdue_count']);
        $this->assertSame(200.0, $stats['summary']['overdue_balance']);
        $this->assertSame(25.0, $stats['summary']['collection_rate']);

        $this->assertCount(12, $stats['monthly']);
        $august = collect($stats['monthly'])->firstWhere('period', '2026-08');
        $this->assertSame(250.0, $august['billed']);
        $this->assertSame(50.0, $august['paid']);

        $this->assertSame(1, $stats['by_status']['PAGADA']['count']);
        $this->assertSame(1, $stats['by_status']['VENCIDA']['count']);
        $this->assertSame(1, $stats['by_status']['PENDIENTE']['count']);
        $this->assertSame(0, $stats['by_status']['PARCIAL']['count']); // the partial one is overdue → VENCIDA

        $this->assertSame('Deudor Grande SAC', $stats['top_debtors'][0]['name']);
        $this->assertSame(450.0, $stats['top_debtors'][0]['balance']);

        $rosa = collect($stats['by_sectorista'])->firstWhere('name', 'ROSA');
        $this->assertSame(500.0, $rosa['billed']);
        $this->assertSame(450.0, $rosa['balance']);
    }

    public function test_service_filters_by_month_and_sectorista(): void
    {
        $this->seedInvoices();
        $service = app(InvoiceStatsService::class);

        $august = $service->build(['year' => '2026', 'month' => 8]);
        $this->assertSame(1, $august['summary']['total']);
        $this->assertSame(250.0, $august['summary']['billed']);

        $carmen = $service->build(['sectorista' => 'CARMEN']);
        $this->assertSame(1, $carmen['summary']['total']);
        $this->assertSame(100.0, $carmen['summary']['collection_rate']);
        $this->assertSame([], $carmen['top_debtors']);
    }

    public function test_stats_page_renders_with_current_year_by_default_and_partial_for_ajax(): void
    {
        $this->seedInvoices();
        $user = $this->userWithPermissions(['billing.view']);

        $this->actingAs($user)->get('/invoices/stats')
            ->assertOk()
            ->assertSee('Estadísticas de facturación')
            ->assertSee('Año '.now()->format('Y'))
            ->assertSee('id="invoice-stats-data"', false)
            ->assertSee('statsMonthlyChart');

        $this->actingAs($user)->get('/invoices/stats?year=2025', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Año 2025')
            ->assertDontSee('<html', false);
    }

    public function test_invoices_list_links_to_stats_and_stats_requires_billing_view(): void
    {
        $viewer = $this->userWithPermissions(['billing.view']);
        $this->actingAs($viewer)->get('/invoices')->assertOk()->assertSee('Ver estadísticas');

        $stranger = $this->userWithPermissions([]);
        $this->actingAs($stranger)->get('/invoices/stats')->assertForbidden();
    }
}

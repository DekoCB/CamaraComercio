<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_collections_report_totals_payments_within_the_calendar_month(): void
    {
        $associateA = Associate::factory()->create();
        $associateB = Associate::factory()->create();
        $invoiceA = Invoice::factory()->for($associateA)->create(['period' => '2026-08', 'amount' => 300]);
        $invoiceB = Invoice::factory()->for($associateB)->create(['period' => '2026-08', 'amount' => 200]);

        // Two payments inside August, one outside — only the August ones
        // should count toward "cobrado del mes".
        Payment::factory()->for($invoiceA)->create(['amount' => 100, 'paid_at' => '2026-08-05']);
        Payment::factory()->for($invoiceB)->create(['amount' => 50, 'paid_at' => '2026-08-20']);
        Payment::factory()->for($invoiceA)->create(['amount' => 999, 'paid_at' => '2026-09-01']);

        $user = $this->userWithPermissions(['reports.view']);

        $response = $this->actingAs($user)->get('/reports/collections?period=2026-08');

        $response->assertOk()
            ->assertSee('S/ 150.00') // collected: 100 + 50, excludes the September payment
            ->assertSee('S/ 500.00'); // invoiced for the period: 300 + 200
    }

    public function test_collections_report_counts_distinct_paying_associates(): void
    {
        $associate = Associate::factory()->create();
        $invoice = Invoice::factory()->for($associate)->create(['period' => '2026-08', 'amount' => 300]);
        Payment::factory()->for($invoice)->create(['amount' => 50, 'paid_at' => '2026-08-05']);
        Payment::factory()->for($invoice)->create(['amount' => 50, 'paid_at' => '2026-08-10']);

        $user = $this->userWithPermissions(['reports.view']);

        $response = $this->actingAs($user)->get('/reports/collections?period=2026-08');

        // 2 payments, but only 1 distinct associate paid.
        $response->assertOk();
        $response->assertSeeInOrder(['Pagos registrados', '2']);
        $response->assertSeeInOrder(['Asociados que pagaron', '1']);
    }

    public function test_debt_report_shows_pending_total_and_distribution(): void
    {
        $associate = Associate::factory()->create();
        Invoice::factory()->for($associate)->create(['period' => '2026-06', 'amount' => 300, 'paid_total' => 100, 'status' => Invoice::STATUS_PARCIAL, 'due_date' => now()->addDays(10)]);
        Invoice::factory()->overdue()->for($associate)->create(['period' => '2026-05', 'amount' => 200, 'paid_total' => 0, 'status' => Invoice::STATUS_PENDIENTE]);
        Invoice::factory()->for($associate)->create(['period' => '2026-04', 'amount' => 100, 'paid_total' => 100, 'status' => Invoice::STATUS_PAGADA]);

        $user = $this->userWithPermissions(['reports.view']);

        $response = $this->actingAs($user)->get('/reports/debt');

        $response->assertOk()
            ->assertSee('S/ 400.00') // 200 (parcial) + 200 (vencida) pending, paid invoice excluded
            ->assertSee('VENCIDA')
            ->assertSee('PARCIAL');
    }

    public function test_report_routes_require_reports_view_permission(): void
    {
        $user = $this->userWithPermissions(['portfolio.view']);

        $this->actingAs($user)->get('/reports')->assertForbidden();
        $this->actingAs($user)->get('/reports/collections')->assertForbidden();
        $this->actingAs($user)->get('/reports/debt')->assertForbidden();
    }

    public function test_export_requires_reports_export_permission_even_with_reports_view(): void
    {
        $user = $this->userWithPermissions(['reports.view']);

        $this->actingAs($user)->get('/reports/collections/export/excel')->assertForbidden();
        $this->actingAs($user)->get('/reports/debt/export/pdf')->assertForbidden();
    }

    public function test_excel_export_returns_spreadsheet_content_type(): void
    {
        $user = $this->userWithPermissions(['reports.view', 'reports.export']);

        $response = $this->actingAs($user)->get('/reports/debt/export/excel');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_pdf_export_returns_pdf_content_type(): void
    {
        $user = $this->userWithPermissions(['reports.view', 'reports.export']);

        $response = $this->actingAs($user)->get('/reports/debt/export/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_collections_excel_export_works_for_a_given_period(): void
    {
        $associate = Associate::factory()->create();
        $invoice = Invoice::factory()->for($associate)->create(['period' => '2026-08']);
        Payment::factory()->for($invoice)->create(['paid_at' => '2026-08-10']);

        $user = $this->userWithPermissions(['reports.view', 'reports.export']);

        $response = $this->actingAs($user)->get('/reports/collections/export/excel?period=2026-08');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}

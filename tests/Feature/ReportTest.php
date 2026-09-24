<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Protest;
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

    public function test_collections_report_supports_a_date_range_spanning_multiple_months(): void
    {
        $associate = Associate::factory()->create();
        $invoiceAug = Invoice::factory()->for($associate)->create(['period' => '2026-08', 'amount' => 300]);
        $invoiceSep = Invoice::factory()->for($associate)->create(['period' => '2026-09', 'amount' => 200]);

        Payment::factory()->for($invoiceAug)->create(['amount' => 100, 'paid_at' => '2026-08-20']);
        Payment::factory()->for($invoiceSep)->create(['amount' => 50, 'paid_at' => '2026-09-05']);
        Payment::factory()->for($invoiceAug)->create(['amount' => 999, 'paid_at' => '2026-07-01']);

        $user = $this->userWithPermissions(['reports.view']);

        $response = $this->actingAs($user)->get('/reports/collections?date_from=2026-08-15&date_to=2026-09-10');

        $response->assertOk()
            ->assertSee('S/ 150.00') // collected: 100 + 50, excludes the July payment
            ->assertSee('S/ 500.00'); // invoiced across both periods touched: 300 + 200
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

    public function test_collector_productivity_report_groups_payments_by_who_registered_them(): void
    {
        $collectorA = tap($this->userWithPermissions(['payments.register']))->update(['name' => 'Cobrador A']);
        $collectorB = tap($this->userWithPermissions(['payments.register']))->update(['name' => 'Cobrador B']);
        $associate = Associate::factory()->create();
        $invoice = Invoice::factory()->for($associate)->create(['period' => '2026-08', 'amount' => 500]);

        Payment::factory()->for($invoice)->create(['amount' => 100, 'paid_at' => '2026-08-05', 'registered_by' => $collectorA->id]);
        Payment::factory()->for($invoice)->create(['amount' => 50, 'paid_at' => '2026-08-10', 'registered_by' => $collectorA->id]);
        Payment::factory()->for($invoice)->create(['amount' => 200, 'paid_at' => '2026-08-15', 'registered_by' => $collectorB->id]);
        Payment::factory()->for($invoice)->create(['amount' => 999, 'paid_at' => '2026-09-01', 'registered_by' => $collectorB->id]);

        $user = $this->userWithPermissions(['reports.view']);

        $response = $this->actingAs($user)->get('/reports/collectors?period=2026-08');

        $response->assertOk()
            ->assertSeeInOrder(['Cobrador B', 'Cobrador A']) // biggest total first
            ->assertSee('S/ 350.00') // total collected in August: 100 + 50 + 200
            ->assertDontSee('999.00'); // September payment excluded
    }

    public function test_protests_report_groups_registrations_by_type_and_channel(): void
    {
        Protest::factory()->create(['type' => Protest::TYPE_PROTESTO, 'channel' => Protest::CHANNEL_NOTARIAL, 'amount' => 80, 'registered_at' => '2026-08-05']);
        Protest::factory()->create(['type' => Protest::TYPE_PROTESTO, 'channel' => Protest::CHANNEL_JUDICIAL, 'amount' => 60, 'registered_at' => '2026-08-10']);
        Protest::factory()->create(['type' => Protest::TYPE_MORA, 'channel' => Protest::CHANNEL_BANCARIO, 'amount' => 40, 'registered_at' => '2026-08-15']);
        Protest::factory()->create(['amount' => 999, 'registered_at' => '2026-07-01']); // outside the period

        $user = $this->userWithPermissions(['reports.view', 'protests.view']);

        $response = $this->actingAs($user)->get('/reports/protests?period=2026-08');

        $response->assertOk()
            ->assertSeeInOrder(['Registros', '3'])
            ->assertSee('S/ 180.00') // 80 + 60 + 40, excludes the July record
            ->assertDontSee('999.00');
    }

    public function test_protests_report_requires_both_reports_view_and_protests_view(): void
    {
        $onlyReports = $this->userWithPermissions(['reports.view']);
        $onlyProtests = $this->userWithPermissions(['protests.view']);

        $this->actingAs($onlyReports)->get('/reports/protests')->assertForbidden();
        $this->actingAs($onlyProtests)->get('/reports/protests')->assertForbidden();
    }

    public function test_protests_export_requires_reports_export_and_protests_view(): void
    {
        $user = $this->userWithPermissions(['reports.view', 'reports.export']);

        $this->actingAs($user)->get('/reports/protests/export/excel')->assertForbidden();
    }

    public function test_protests_excel_and_pdf_export_work(): void
    {
        Protest::factory()->create(['registered_at' => now()]);
        $user = $this->userWithPermissions(['reports.view', 'reports.export', 'protests.view']);

        $excel = $this->actingAs($user)->get('/reports/protests/export/excel');
        $pdf = $this->actingAs($user)->get('/reports/protests/export/pdf');

        $excel->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_report_routes_require_reports_view_permission(): void
    {
        $user = $this->userWithPermissions(['portfolio.view']);

        $this->actingAs($user)->get('/reports')->assertForbidden();
        $this->actingAs($user)->get('/reports/collections')->assertForbidden();
        $this->actingAs($user)->get('/reports/debt')->assertForbidden();
        $this->actingAs($user)->get('/reports/collectors')->assertForbidden();
    }

    public function test_export_requires_reports_export_permission_even_with_reports_view(): void
    {
        $user = $this->userWithPermissions(['reports.view']);

        $this->actingAs($user)->get('/reports/collections/export/excel')->assertForbidden();
        $this->actingAs($user)->get('/reports/debt/export/pdf')->assertForbidden();
        $this->actingAs($user)->get('/reports/collectors/export/excel')->assertForbidden();
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

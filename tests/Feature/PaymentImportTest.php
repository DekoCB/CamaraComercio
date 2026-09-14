<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PaymentImportTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    /**
     * @param  array<int, array<int, string>>  $rows  including the header row
     */
    private function makeXlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'pagos.xlsx', null, null, true);
    }

    public function test_valid_file_shows_a_preview_with_the_row_ready_to_import(): void
    {
        $associate = Associate::factory()->create(['name' => 'Comercial Andina SAC']);
        $invoice = Invoice::factory()->create(['associate_id' => $associate->id, 'period' => '2026-08', 'amount' => 250, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo de factura', 'Monto', 'Fecha de pago', 'Notas'],
            [$associate->name, '2026-08', '250.00', '2026-08-15', 'Pago de prueba'],
        ]);

        $response = $this->actingAs($user)->post('/payments/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('Comercial Andina SAC')->assertSee('OK');
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('0.00', $invoice->fresh()->paid_total);
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        $user = $this->userWithPermissions(['payments.register']);
        $file = $this->makeXlsx([
            ['Empresa', 'Correo'],
            ['Andina SAC', 'andina@example.com'],
        ]);

        $response = $this->actingAs($user)->post('/payments/import/preview', ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    public function test_row_referencing_a_nonexistent_invoice_is_flagged(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['payments.register']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo de factura', 'Monto', 'Fecha de pago'],
            [$associate->name, '2026-08', '100.00', '2026-08-15'],
        ]);

        $response = $this->actingAs($user)->post('/payments/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('No existe una factura para ese asociado en ese período');
    }

    public function test_row_with_amount_greater_than_the_invoice_balance_is_flagged(): void
    {
        $associate = Associate::factory()->create();
        Invoice::factory()->create(['associate_id' => $associate->id, 'period' => '2026-08', 'amount' => 250, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo de factura', 'Monto', 'Fecha de pago'],
            [$associate->name, '2026-08', '300.00', '2026-08-15'],
        ]);

        $response = $this->actingAs($user)->post('/payments/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('El monto supera el saldo pendiente de la factura');
    }

    public function test_two_rows_for_the_same_invoice_cannot_jointly_exceed_its_balance(): void
    {
        $associate = Associate::factory()->create();
        Invoice::factory()->create(['associate_id' => $associate->id, 'period' => '2026-08', 'amount' => 250, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo de factura', 'Monto', 'Fecha de pago'],
            [$associate->name, '2026-08', '200.00', '2026-08-15'],
            [$associate->name, '2026-08', '100.00', '2026-08-16'],
        ]);

        $response = $this->actingAs($user)->post('/payments/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('considerando otros pagos de este mismo archivo');
    }

    public function test_row_with_future_payment_date_is_flagged(): void
    {
        $associate = Associate::factory()->create();
        Invoice::factory()->create(['associate_id' => $associate->id, 'period' => '2026-08', 'amount' => 250, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo de factura', 'Monto', 'Fecha de pago'],
            [$associate->name, '2026-08', '100.00', now()->addDays(5)->toDateString()],
        ]);

        $response = $this->actingAs($user)->post('/payments/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('La fecha de pago no puede ser futura');
    }

    public function test_confirm_registers_payments_through_the_payment_service_keeping_balances_in_sync(): void
    {
        $associate = Associate::factory()->create(['name' => 'Valido SAC']);
        $invoice = Invoice::factory()->create(['associate_id' => $associate->id, 'period' => '2026-08', 'amount' => 250, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo de factura', 'Monto', 'Fecha de pago'],
            [$associate->name, '2026-08', '250.00', '2026-08-15'],
            ['Asociado Inexistente', '2026-08', '50.00', '2026-08-15'],
        ]);

        $this->actingAs($user)->post('/payments/import/preview', ['file' => $file]);
        $response = $this->actingAs($user)->post('/payments/import/confirm');

        $response->assertRedirect('/payments');
        $this->assertDatabaseCount('payments', 1);
        $invoice->refresh();
        $this->assertSame('250.00', $invoice->paid_total);
        $this->assertSame(Invoice::STATUS_PAGADA, $invoice->status);
    }

    public function test_confirm_without_a_prior_preview_redirects_back_to_the_upload_form(): void
    {
        $user = $this->userWithPermissions(['payments.register']);

        $response = $this->actingAs($user)->post('/payments/import/confirm');

        $response->assertRedirect('/payments/import');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_import_requires_payments_register_permission(): void
    {
        $user = $this->userWithPermissions(['billing.view']);
        $file = $this->makeXlsx([['Asociado', 'Periodo de factura', 'Monto'], ['Alguien', '2026-08', '100.00']]);

        $this->actingAs($user)->get('/payments/import')->assertForbidden();
        $this->actingAs($user)->post('/payments/import/preview', ['file' => $file])->assertForbidden();
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('imports');
        parent::tearDown();
    }
}

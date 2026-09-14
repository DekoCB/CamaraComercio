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

class InvoiceImportTest extends TestCase
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

        return new UploadedFile($path, 'facturas.xlsx', null, null, true);
    }

    public function test_valid_file_shows_a_preview_with_the_row_ready_to_import(): void
    {
        $associate = Associate::factory()->create(['name' => 'Comercial Andina SAC']);
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo', 'Monto', 'Fecha de emision', 'Fecha de vencimiento'],
            [$associate->name, '2026-08', '250.00', '2026-08-01', '2026-09-10'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('Comercial Andina SAC')->assertSee('OK');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Empresa', 'Correo'],
            ['Andina SAC', 'andina@example.com'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    public function test_row_referencing_an_unknown_ruc_is_flagged(): void
    {
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['RUC', 'Periodo', 'Monto'],
            ['20999999999', '2026-08', '250.00'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('No se encontró un asociado con ese RUC');
    }

    public function test_row_with_ambiguous_associate_name_is_flagged(): void
    {
        Associate::factory()->create(['name' => 'Comercial Andina SAC', 'company' => 'Uno']);
        Associate::factory()->create(['name' => 'Comercial Andina SAC', 'company' => 'Dos']);
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo', 'Monto'],
            ['Comercial Andina SAC', '2026-08', '250.00'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('Hay más de un asociado con ese nombre');
    }

    public function test_row_with_invalid_period_format_is_flagged(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo', 'Monto'],
            [$associate->name, 'agosto-2026', '250.00'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('El período debe tener el formato AAAA-MM');
    }

    public function test_row_with_non_positive_amount_is_flagged(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo', 'Monto'],
            [$associate->name, '2026-08', '0'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('El monto debe ser un número mayor a cero');
    }

    public function test_row_with_due_date_before_issue_date_is_flagged(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo', 'Monto', 'Fecha de emision', 'Fecha de vencimiento'],
            [$associate->name, '2026-08', '250.00', '2026-08-20', '2026-08-01'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('La fecha de vencimiento no puede ser anterior a la de emisión');
    }

    public function test_row_duplicating_an_existing_invoice_is_flagged(): void
    {
        $associate = Associate::factory()->create();
        Invoice::factory()->create(['associate_id' => $associate->id, 'period' => '2026-08']);
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo', 'Monto'],
            [$associate->name, '2026-08', '250.00'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('Ya existe una factura para este asociado en este período');
    }

    public function test_row_duplicating_another_row_in_the_same_file_is_flagged(): void
    {
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo', 'Monto'],
            [$associate->name, '2026-08', '250.00'],
            [$associate->name, '2026-08', '300.00'],
        ]);

        $response = $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('ya aparecen en otra fila de este archivo');
    }

    public function test_confirm_imports_only_the_valid_rows(): void
    {
        $ok = Associate::factory()->create(['name' => 'Valido SAC']);
        $user = $this->userWithPermissions(['billing.generate']);
        $file = $this->makeXlsx([
            ['Asociado', 'Periodo', 'Monto', 'Fecha de emision', 'Fecha de vencimiento'],
            [$ok->name, '2026-08', '250.00', '2026-08-01', '2026-09-10'],
            ['Asociado Inexistente', '2026-08', '100.00', '2026-08-01', '2026-09-10'],
        ]);

        $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file]);
        $response = $this->actingAs($user)->post('/invoices/import/confirm');

        $response->assertRedirect('/invoices');
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'associate_id' => $ok->id,
            'period' => '2026-08',
            'amount' => 250,
            'paid_total' => 0,
            'status' => Invoice::STATUS_PENDIENTE,
        ]);
    }

    public function test_confirm_without_a_prior_preview_redirects_back_to_the_upload_form(): void
    {
        $user = $this->userWithPermissions(['billing.generate']);

        $response = $this->actingAs($user)->post('/invoices/import/confirm');

        $response->assertRedirect('/invoices/import');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_import_requires_billing_generate_permission(): void
    {
        $user = $this->userWithPermissions(['billing.view']);
        $file = $this->makeXlsx([['Asociado', 'Periodo', 'Monto'], ['Alguien', '2026-08', '250.00']]);

        $this->actingAs($user)->get('/invoices/import')->assertForbidden();
        $this->actingAs($user)->post('/invoices/import/preview', ['file' => $file])->assertForbidden();
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('imports');
        parent::tearDown();
    }
}

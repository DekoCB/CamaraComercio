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

class AssociateImportTest extends TestCase
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

        return new UploadedFile($path, 'asociados.xlsx', null, null, true);
    }

    public function test_valid_file_shows_a_preview_with_all_rows_ready_to_import(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Nombre', 'Empresa', 'Contacto', 'Correo'],
            ['Comercial Andina', 'Andina SAC', '555-0101', 'andina@example.com'],
            ['Ferretería Central', '', '', ''],
        ]);

        $response = $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('Comercial Andina')->assertSee('Ferretería Central');
        $this->assertDatabaseCount('associates', 0);
    }

    public function test_file_without_a_name_column_is_rejected(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Empresa', 'Correo'],
            ['Andina SAC', 'andina@example.com'],
        ]);

        $response = $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    public function test_row_with_blank_name_is_flagged_as_an_error(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Nombre', 'Correo'],
            ['', 'sin-nombre@example.com'],
        ]);

        $response = $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('La razón social es obligatoria');
    }

    public function test_row_with_invalid_email_is_flagged(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Nombre', 'Correo'],
            ['Nombre Valido', 'esto-no-es-un-correo'],
        ]);

        $response = $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('El correo no es válido');
    }

    public function test_row_with_email_already_registered_is_flagged(): void
    {
        Associate::factory()->create(['email' => 'ya-existe@example.com']);
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Nombre', 'Correo'],
            ['Duplicado', 'ya-existe@example.com'],
        ]);

        $response = $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);

        $response->assertOk()->assertSee('Ya existe un asociado con ese correo');
    }

    public function test_confirm_imports_only_the_valid_rows(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Nombre', 'Correo'],
            ['Valido Uno', 'valido1@example.com'],
            ['', 'sin-nombre@example.com'],
            ['Valido Dos', 'valido2@example.com'],
        ]);

        $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);
        $response = $this->actingAs($user)->post('/associates/import/confirm');

        $response->assertRedirect('/associates');
        $this->assertDatabaseCount('associates', 2);
        $this->assertDatabaseHas('associates', ['name' => 'Valido Uno', 'email' => 'valido1@example.com']);
        $this->assertDatabaseHas('associates', ['name' => 'Valido Dos', 'email' => 'valido2@example.com']);
        $this->assertDatabaseMissing('associates', ['email' => 'sin-nombre@example.com']);
    }

    /**
     * The Cámara's master workbook: headers on row 1, the month-label row
     * of the payment grid on row 2, then each associate spanning several
     * rows (master data on the first, invoice numbers/amounts under the
     * month columns on the rest). Repeated headers ("DNI N°" / "DNI N°4",
     * two "GENERO", "CORREO" / "CORREO 6") distinguish the legal
     * representative from the representative before the CCH.
     */
    public function test_master_workbook_layout_is_parsed_into_every_field(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $headers = [
            '', 'zor', 'ESTADO', "ULT.\nMES\nPAGO", 'SECTORISTA', 'CAT.', 'MONTO A PAGAR', 'FECHA DE INGRESO', 'TIPO DE PERSONA',
            'FECHA DE ANIVERSARIO', 'RUC', 'RAZON SOCIAL', 'NOMBRE COMERCIAL', 'DIRECCIÓN DE FACTURACIÓN', 'DISTRITO',
            'DIRECCION DE CORRESPONDENCIA', 'DISTRITO DE CORRESPONDENCIA', 'SEGÚN SU TAMAÑO', 'SEGÚN SU ACTIVIDAD',
            'COMITÉ SECTORIAL', 'CIIU', 'SUB SECTOR', 'CORREO DE LA EMPRESA', 'REPRESENTANTE LEGAL', 'DNI N°', 'GENERO',
            'CUMPLEAÑOS', 'CELULAR', 'CORREO', 'RESPRESENTANTE ANTE LA CCH', 'DNI N°4', 'GENERO', 'CUMPLEAÑOS', 'CELULAR',
            'CORREO 6', 'IMAGENES', 'AÑO 2024-APORTES', '', 'OBSERVACIONES',
        ];
        $monthRow = array_fill(0, 36, '') + [36 => 'Jan-24', 37 => 'Feb-24'];
        $associate = [
            '', '1', 'ACTIVO', 'Aug-26', 'ROSA', 'D', 'S/  75.00', '25/9/2018', 'PERSONA JURÍDICA',
            '1/7/2017', '20602485146', '4OS GROUP ARQUITECTURA S.A.C.', '4OS GROUP', 'JR HUANCAS 269', 'EL TAMBO',
            'JR. HUANCAS Y URUGUAY', 'HUANCAYO', 'MICROEMPRESAS', 'SERVICIO',
            'CONSTRUCCION E INMOBILIARIA / SALUD', 'ACTIVIDADES DE MÉDICOS', 'VENTA AL POR MENOR', 'empresa@4os.example.com;', 'SAPAICO VARGAS MARIO', '20019748', 'MASCULINO',
            '10/5/1967', '954436988', 'mario@4os.example.com', 'PEREZ ROJAS ANA', '44537443', 'FEMENINO', '11/7/1980', '999888777',
            'ana@4os.example.com', '', 'F010-00000329', 'F010-00000621', 'Paga puntual',
        ];
        $continuation = array_fill(0, 36, '') + [36 => 'S/75.00', 37 => 'S/75.00'];
        $suspended = $associate;
        $suspended[2] = 'SUSPENDIDO';
        $suspended[10] = '20130330054';
        $suspended[11] = 'MINERA CENTRO S.A.C.';
        $suspended[22] = '';
        $suspended[28] = '';
        $suspended[34] = '';

        $file = $this->makeXlsx([$headers, $monthRow, $associate, $continuation, $continuation, $suspended]);

        $this->actingAs($user)->post('/associates/import/preview', ['file' => $file])
            ->assertOk()
            ->assertSee('2 listos para importar')
            ->assertSee('4 aportes mensuales se registrarán como pagos')
            ->assertSee('2 meses')
            ->assertSee('4OS GROUP ARQUITECTURA S.A.C.')
            ->assertSee('MINERA CENTRO S.A.C.');

        $this->actingAs($user)->post('/associates/import/confirm')->assertRedirect('/associates');

        $this->assertDatabaseCount('associates', 2);

        $imported = Associate::where('ruc', '20602485146')->firstOrFail();
        $this->assertSame('4OS GROUP ARQUITECTURA S.A.C.', $imported->name);
        $this->assertSame('4OS GROUP', $imported->company);
        $this->assertSame(Associate::STATUS_ACTIVO, $imported->status);
        $this->assertSame('ROSA', $imported->sectorista);
        $this->assertSame('D', $imported->category);
        $this->assertSame('75.00', $imported->monthly_fee);
        $this->assertSame('2018-09-25', $imported->joined_at->format('Y-m-d'));
        $this->assertSame('2017-07-01', $imported->anniversary_date->format('Y-m-d'));
        $this->assertSame('PERSONA JURÍDICA', $imported->person_type);
        $this->assertSame('JR HUANCAS 269', $imported->billing_address);
        $this->assertSame('EL TAMBO', $imported->billing_district);
        $this->assertSame('JR. HUANCAS Y URUGUAY', $imported->mailing_address);
        $this->assertSame('HUANCAYO', $imported->mailing_district);
        $this->assertSame('MICROEMPRESAS', $imported->company_size);
        $this->assertSame('SERVICIO', $imported->activity_type);
        $this->assertSame('CONSTRUCCION E INMOBILIARIA / SALUD', $imported->sector_committee);
        $this->assertSame('ACTIVIDADES DE MÉDICOS', $imported->ciiu);
        $this->assertSame('VENTA AL POR MENOR', $imported->sub_sector);
        $this->assertSame('empresa@4os.example.com', $imported->email); // trailing ";" tolerated
        $this->assertSame('SAPAICO VARGAS MARIO', $imported->legal_rep_name);
        $this->assertSame('20019748', $imported->legal_rep_dni);
        $this->assertSame('MASCULINO', $imported->legal_rep_gender);
        $this->assertSame('1967-05-10', $imported->legal_rep_birthday->format('Y-m-d'));
        $this->assertSame('954436988', $imported->legal_rep_phone);
        $this->assertSame('mario@4os.example.com', $imported->legal_rep_email);
        $this->assertSame('PEREZ ROJAS ANA', $imported->cch_rep_name);
        $this->assertSame('44537443', $imported->cch_rep_dni);
        $this->assertSame('FEMENINO', $imported->cch_rep_gender);
        $this->assertSame('1980-07-11', $imported->cch_rep_birthday->format('Y-m-d'));
        $this->assertSame('999888777', $imported->cch_rep_phone);
        $this->assertSame('ana@4os.example.com', $imported->cch_rep_email);
        $this->assertSame('Paga puntual', $imported->notes);

        $second = Associate::where('ruc', '20130330054')->firstOrFail();
        $this->assertSame(Associate::STATUS_SUSPENDIDO, $second->status);
        $this->assertFalse($second->is_active);

        // Contributions grid: the associate row carries the comprobante
        // per month, the row below the amount → one paid invoice each.
        $this->assertDatabaseCount('invoices', 4);
        $this->assertDatabaseCount('payments', 4);
        $january = Invoice::where('associate_id', $imported->id)->where('period', '2024-01')->firstOrFail();
        $this->assertSame('F010-00000329', $january->receipt_number);
        $this->assertSame('75.00', $january->amount);
        $this->assertSame('75.00', $january->paid_total);
        $this->assertSame(Invoice::STATUS_PAGADA, $january->status);
        $this->assertSame('2024-01-01', $january->payments()->first()->paid_at->format('Y-m-d'));
        $this->assertSame('2024-02', $imported->lastPaidPeriod());

        // The suspended row has no amounts row of its own: its monthly
        // fee is taken as the amount of each comprobante.
        $this->assertSame('75.00', Invoice::where('associate_id', $second->id)->where('period', '2024-02')->value('amount'));
    }

    public function test_existing_ruc_is_updated_and_unknown_catalog_values_are_flagged(): void
    {
        Associate::factory()->create(['ruc' => '20100000001', 'name' => 'Nombre Viejo SAC']);
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Razon social', 'RUC', 'Tipo de persona', 'Estado'],
            ['Repetido SAC', '20100000001', 'PERSONA JURÍDICA', 'ACTIVO'],
            ['Catalogo Raro', '20100000002', 'ROBOT', 'ACTIVO'],
            ['Ruc Corto', '123', '', 'ACTIVO'],
        ]);

        $response = $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);

        $response->assertOk()
            ->assertSee('Actualizar')
            ->assertSee('1 ya existen (se actualizarán)')
            ->assertSee('Tipo de persona no reconocido')
            ->assertSee('El RUC debe tener 11 dígitos')
            ->assertSee('1 listos para importar');

        $this->actingAs($user)->post('/associates/import/confirm')->assertRedirect('/associates');

        $this->assertDatabaseCount('associates', 1);
        $this->assertDatabaseHas('associates', ['ruc' => '20100000001', 'name' => 'Repetido SAC']);
    }

    public function test_same_ruc_twice_in_one_file_is_flagged(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Razon social', 'RUC'],
            ['Primero SAC', '20100000001'],
            ['Segundo SAC', '20100000001'],
        ]);

        $response = $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);

        $response->assertOk()
            ->assertSee('se repite en el archivo')
            ->assertSee('1 listos para importar');
    }

    public function test_reimporting_the_workbook_updates_the_associate_without_duplicating_its_payments(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $headers = ['RAZON SOCIAL', 'RUC', 'MONTO A PAGAR', 'SECTORISTA', 'AÑO 2024-APORTES', '', ''];
        $monthRow = ['', '', '', '', 'ene-24', 'feb-24', 'mar-24'];
        $associate = ['MINERA CENTRO S.A.C.', '20130330054', '50', 'CARMEN', 'F010-1', 'F010-2', ''];
        $amounts = ['', '', '', '', '50', '50', ''];

        $this->actingAs($user)->post('/associates/import/preview', ['file' => $this->makeXlsx([$headers, $monthRow, $associate, $amounts])]);
        $this->actingAs($user)->post('/associates/import/confirm');

        $this->assertDatabaseCount('associates', 1);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('payments', 2);

        // Next month's workbook: same associate (new sectorista), one more
        // paid month, one month marked as not collected.
        $associate = ['MINERA CENTRO S.A.C.', '20130330054', '50', 'ROSA', 'F010-1', 'NOTA DE CREDITO', 'F010-3'];
        $amounts = ['', '', '', '', '50', '', 'S/ 50.00'];

        $this->actingAs($user)->post('/associates/import/preview', ['file' => $this->makeXlsx([$headers, $monthRow, $associate, $amounts])])
            ->assertOk()
            ->assertSee('Actualizar');
        $this->actingAs($user)->post('/associates/import/confirm')
            ->assertRedirect('/associates')
            ->assertSessionHas('success', 'Importación completa: 0 asociados creados, 1 actualizados, 1 pagos registrados (1 cuotas nuevas).');

        $this->assertDatabaseCount('associates', 1);
        $this->assertDatabaseCount('invoices', 3);
        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseHas('associates', ['ruc' => '20130330054', 'sectorista' => 'ROSA']);
        $this->assertDatabaseHas('invoices', ['period' => '2024-03', 'receipt_number' => 'F010-3', 'status' => Invoice::STATUS_PAGADA]);
    }

    public function test_confirm_without_a_prior_preview_redirects_back_to_the_upload_form(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post('/associates/import/confirm');

        $response->assertRedirect('/associates/import');
        $this->assertDatabaseCount('associates', 0);
    }

    public function test_cancel_discards_the_pending_import_without_creating_anything(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);
        $file = $this->makeXlsx([
            ['Nombre'],
            ['Alguien'],
        ]);

        $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);
        $this->actingAs($user)->post('/associates/import/cancel');
        $response = $this->actingAs($user)->post('/associates/import/confirm');

        $response->assertRedirect('/associates/import');
        $this->assertDatabaseCount('associates', 0);
    }

    public function test_import_requires_associates_manage_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $file = $this->makeXlsx([['Nombre'], ['Alguien']]);

        $this->actingAs($user)->get('/associates/import')->assertForbidden();
        $this->actingAs($user)->post('/associates/import/preview', ['file' => $file])->assertForbidden();
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('imports');
        parent::tearDown();
    }
}

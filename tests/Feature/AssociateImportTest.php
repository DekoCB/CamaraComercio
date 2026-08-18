<?php

namespace Tests\Feature;

use App\Models\Associate;
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

        $response->assertOk()->assertSee('El nombre es obligatorio');
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

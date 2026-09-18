<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\AssociateDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AssociateDocumentTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_uploading_a_pdf_document_stores_it_as_is(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post("/associates/{$associate->id}/documents", [
            'type' => AssociateDocument::TYPE_LICENCIA_FUNCIONAMIENTO,
            'file' => UploadedFile::fake()->create('licencia.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('associate_documents', [
            'associate_id' => $associate->id,
            'type' => AssociateDocument::TYPE_LICENCIA_FUNCIONAMIENTO,
            'original_name' => 'licencia.pdf',
        ]);
        $document = AssociateDocument::first();
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertStringEndsWith('.pdf', $document->file_path);
    }

    public function test_uploading_an_image_document_converts_it_to_pdf(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post("/associates/{$associate->id}/documents", [
            'type' => AssociateDocument::TYPE_FICHA_RUC,
            'file' => UploadedFile::fake()->image('titulo.jpg'),
        ]);

        $response->assertRedirect();
        $document = AssociateDocument::first();
        $this->assertNotNull($document);
        $this->assertStringEndsWith('.pdf', $document->file_path);
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($document->file_path));
    }

    public function test_document_upload_rejects_an_unknown_type(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post("/associates/{$associate->id}/documents", [
            'type' => 'NO_EXISTE',
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_document_upload_rejects_files_over_10mb(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->post("/associates/{$associate->id}/documents", [
            'type' => AssociateDocument::TYPE_COPIA_PRIMER_PAGO,
            'file' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_a_document_can_be_deleted(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions(['associates.manage']);
        $this->actingAs($user)->post("/associates/{$associate->id}/documents", [
            'type' => AssociateDocument::TYPE_VIGENCIA_PODER,
            'file' => UploadedFile::fake()->create('convenio.pdf', 100, 'application/pdf'),
        ]);
        $document = AssociateDocument::first();

        $response = $this->actingAs($user)->delete("/associates/documents/{$document->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('associate_documents', ['id' => $document->id]);
        Storage::disk('public')->assertMissing($document->file_path);
    }

    public function test_user_without_associates_manage_permission_cannot_upload_or_delete_documents(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create();
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->post("/associates/{$associate->id}/documents", [
            'type' => AssociateDocument::TYPE_COPIA_PRIMER_PAGO,
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertForbidden();

        $document = AssociateDocument::create([
            'associate_id' => $associate->id,
            'type' => AssociateDocument::TYPE_COPIA_PRIMER_PAGO,
            'original_name' => 'doc.pdf',
            'file_path' => 'associates/1/documents/doc.pdf',
            'size' => 100,
        ]);
        $this->actingAs($user)->delete("/associates/documents/{$document->id}")->assertForbidden();
    }

    public function test_associate_page_lists_its_documents(): void
    {
        Storage::fake('public');
        $associate = Associate::factory()->create(['name' => 'Con Documentos SAC']);
        AssociateDocument::create([
            'associate_id' => $associate->id,
            'type' => AssociateDocument::TYPE_LICENCIA_FUNCIONAMIENTO,
            'original_name' => 'mi-licencia.pdf',
            'file_path' => 'associates/x/documents/mi-licencia.pdf',
            'size' => 12345,
        ]);
        $user = $this->userWithPermissions(['associates.manage']);

        $response = $this->actingAs($user)->get("/associates/{$associate->id}");

        $response->assertOk()->assertSee('mi-licencia.pdf')->assertSee('Licencia de Funcionamiento');
    }
}

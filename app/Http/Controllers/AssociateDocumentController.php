<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssociateDocumentRequest;
use App\Models\Associate;
use App\Models\AssociateDocument;
use App\Models\AuditLog;
use App\Services\AssociateDocumentService;
use Illuminate\Http\RedirectResponse;

class AssociateDocumentController extends Controller
{
    public function store(AssociateDocumentRequest $request, Associate $associate, AssociateDocumentService $service): RedirectResponse
    {
        $data = $request->validated();

        $document = $service->upload($associate, $data['file'], $data['type'], $request->user()->id);

        AuditLog::record('associate.document.upload', 'associate', (string) $associate->id, 'success', [
            'document_id' => $document->id,
            'type' => $document->type,
        ]);

        return back()->with('success', 'Documento subido correctamente.');
    }

    public function destroy(AssociateDocument $document, AssociateDocumentService $service): RedirectResponse
    {
        $associateId = $document->associate_id;
        AuditLog::record('associate.document.delete', 'associate', (string) $associateId, 'success', [
            'document_id' => $document->id,
            'type' => $document->type,
        ]);

        $service->delete($document);

        return back()->with('success', 'Documento eliminado.');
    }
}

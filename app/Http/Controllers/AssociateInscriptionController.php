<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssociateInscriptionRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Services\AssociateInscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssociateInscriptionController extends Controller
{
    public function edit(Associate $associate): View
    {
        $associate->load(['executives', 'products']);

        return view('associates.ficha-inscripcion', compact('associate'));
    }

    public function update(AssociateInscriptionRequest $request, Associate $associate, AssociateInscriptionService $service): RedirectResponse
    {
        $document = $service->generate($associate, $request->validated(), $request->user()->id);

        AuditLog::record('associate.inscripcion.generate', 'associate', (string) $associate->id, 'success', [
            'document_id' => $document->id,
        ]);

        return redirect()->route('associates.show', $associate)->with('success', 'Ficha de Inscripción generada correctamente.');
    }
}

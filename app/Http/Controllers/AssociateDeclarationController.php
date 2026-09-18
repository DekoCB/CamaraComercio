<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssociateDeclarationRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Services\AssociateDeclarationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssociateDeclarationController extends Controller
{
    public function edit(Associate $associate): View
    {
        return view('associates.declaracion-jurada', compact('associate'));
    }

    public function update(AssociateDeclarationRequest $request, Associate $associate, AssociateDeclarationService $service): RedirectResponse
    {
        $data = $request->validated();

        $document = $service->generate(
            $associate,
            $data,
            $request->file('signature'),
            $request->file('fingerprint'),
            $request->user()->id
        );

        AuditLog::record('associate.declaration.generate', 'associate', (string) $associate->id, 'success', [
            'document_id' => $document->id,
        ]);

        return redirect()->route('associates.show', $associate)->with('success', 'Declaración Jurada generada correctamente.');
    }
}

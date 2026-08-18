<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssociateRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssociateController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $associates = Associate::query()
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%")
                        ->orWhere('contact_phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('associates.index', [
            'associates' => $associates,
            'term' => $term,
        ]);
    }

    public function create(Request $request): View
    {
        $data = [
            'associate' => null,
            'action' => route('associates.store'),
            'method' => 'POST',
        ];

        // The "Nuevo asociado" link opens this as a modal overlay instead of
        // navigating away from the list (js-modal-link in app.js); ajax
        // requests get just the <form>, everything else gets the full page
        // (direct URL access, no-JS fallback).
        return $request->ajax() ? view('associates._form', $data) : view('associates.form', $data);
    }

    public function store(AssociateRequest $request): RedirectResponse
    {
        $associate = Associate::create($request->validated() + ['is_active' => true]);

        AuditLog::record('associate.create', 'associate', (string) $associate->id);

        return redirect()->route('associates.index')->with('success', 'Asociado registrado correctamente.');
    }

    public function edit(Request $request, Associate $associate): View
    {
        $data = [
            'associate' => $associate,
            'action' => route('associates.update', $associate),
            'method' => 'PUT',
        ];

        return $request->ajax() ? view('associates._form', $data) : view('associates.form', $data);
    }

    public function update(AssociateRequest $request, Associate $associate): RedirectResponse
    {
        $associate->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        AuditLog::record('associate.update', 'associate', (string) $associate->id);

        return redirect()->route('associates.index')->with('success', 'Asociado actualizado correctamente.');
    }
}

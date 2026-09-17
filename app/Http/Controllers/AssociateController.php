<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssociateRequest;
use App\Models\Associate;
use App\Models\AssociateDocument;
use App\Models\AuditLog;
use App\Models\Benefit;
use App\Services\BenefitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssociateController extends Controller
{
    /**
     * Toolbar filters besides the free-text search. Each maps to one
     * associates.* column and is matched exactly (they come from a
     * dropdown fed with the distinct values already stored).
     */
    private const COLUMN_FILTERS = ['status', 'sectorista', 'category', 'person_type', 'billing_district'];

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $associateId = $request->query('associate_id');

        $filters = ['q' => $term, 'associate_id' => $associateId];
        foreach (self::COLUMN_FILTERS as $column) {
            $value = trim((string) $request->query($column, ''));
            $filters[$column] = $value !== '' ? $value : null;
        }

        $associates = Associate::query()
            // Direct link by id (kept for deep links even though the toolbar
            // no longer lists every associate — searching by RUC is the way
            // to isolate one of two associates sharing a name).
            ->when($associateId, fn ($query) => $query->where('id', $associateId))
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('ruc', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%")
                        ->orWhere('contact_phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('sectorista', 'like', "%{$term}%")
                        ->orWhere('legal_rep_name', 'like', "%{$term}%")
                        ->orWhere('cch_rep_name', 'like', "%{$term}%");
                });
            });

        foreach (self::COLUMN_FILTERS as $column) {
            if ($filters[$column] !== null) {
                $associates->where($column, $filters[$column]);
            }
        }

        $associates = $associates->orderBy('name')->paginate(15)->withQueryString();

        // Dropdown options come from what is actually stored so the user
        // never picks a value that returns nothing.
        $distinct = fn (string $column) => Associate::query()
            ->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->all();

        return view('associates.index', [
            'associates' => $associates,
            'term' => $term,
            'filters' => $filters,
            'filterOptions' => [
                'status' => Associate::STATUSES,
                'sectorista' => $distinct('sectorista'),
                'category' => $distinct('category'),
                'person_type' => Associate::PERSON_TYPES,
                'billing_district' => $distinct('billing_district'),
            ],
        ]);
    }

    public function show(Associate $associate, BenefitService $benefitService): View
    {
        $benefits = Benefit::where('is_active', true)->orderBy('name')->get();

        return view('associates.show', [
            'associate' => $associate,
            'lastPaidPeriod' => $associate->lastPaidPeriod(),
            'documentTypes' => AssociateDocument::TYPES,
            'documents' => $associate->documents()->with('uploadedBy')->latest()->get(),
            'benefits' => $benefits,
            'benefitUsage' => $benefits->mapWithKeys(fn (Benefit $b) => [
                $b->id => $benefitService->usageThisYear($associate, $b),
            ]),
            'benefitUsages' => $associate->benefitUsages()->with(['benefit', 'registeredBy'])->orderByDesc('used_at')->get(),
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
        $data = $request->safe()->except(['image', 'remove_image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('associates', 'public');
        }

        $associate = Associate::create($data + ['status' => Associate::STATUS_ACTIVO]);

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
        $data = $request->safe()->except(['image', 'remove_image']);

        if ($request->boolean('remove_image') && $associate->image_path) {
            Storage::disk('public')->delete($associate->image_path);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            if ($associate->image_path) {
                Storage::disk('public')->delete($associate->image_path);
            }
            $data['image_path'] = $request->file('image')->store('associates', 'public');
        }

        $associate->update($data);

        AuditLog::record('associate.update', 'associate', (string) $associate->id);

        return redirect()->route('associates.index')->with('success', 'Asociado actualizado correctamente.');
    }

    public function destroy(Associate $associate): RedirectResponse
    {
        // invoices.associate_id is RESTRICT on delete: an associate with
        // billing history is never removed (the Cámara would lose its
        // collection records). "Desafiliado" is the way to retire them.
        $invoiceCount = $associate->invoices()->count();
        if ($invoiceCount > 0) {
            AuditLog::record('associate.delete', 'associate', (string) $associate->id, 'failure', ['invoices' => $invoiceCount]);

            return back()->with('error', "No se puede eliminar \"{$associate->name}\": tiene {$invoiceCount} factura(s) registrada(s). Cámbielo a estado Desafiliado si ya no pertenece a la Cámara.");
        }

        if ($associate->image_path) {
            Storage::disk('public')->delete($associate->image_path);
        }

        $name = $associate->name;
        $associate->delete();

        AuditLog::record('associate.delete', 'associate', (string) $associate->id, 'success', ['name' => $name]);

        return redirect()->route('associates.index')->with('success', "Asociado \"{$name}\" eliminado.");
    }
}

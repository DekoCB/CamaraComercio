<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentalCatalogItemRequest;
use App\Models\AuditLog;
use App\Models\RentalCatalogItem;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "BIENES DE CCH" — catálogo de equipos/servicios cotizables junto con un
 * espacio (ver RentalCatalogItem). Pantalla chica anidada en Alquileres,
 * mismo permiso rentals.manage, igual criterio que Espacios.
 */
class RentalCatalogItemController extends Controller
{
    public function index(): View
    {
        return view('rental-catalog-items.index', [
            'items' => RentalCatalogItem::orderBy('sort_order')->orderBy('name')->get(),
            'bankAccountDefault' => Setting::get('rentals.bank_account_official'),
        ]);
    }

    public function create(Request $request): View
    {
        return $request->ajax() ? view('rental-catalog-items._form') : view('rental-catalog-items.create');
    }

    public function store(RentalCatalogItemRequest $request): RedirectResponse
    {
        $item = RentalCatalogItem::create($request->validated() + ['is_active' => true]);

        AuditLog::record('rental_catalog_item.create', 'rental_catalog_item', (string) $item->id, 'success');

        return redirect()->route('rental-catalog-items.index')->with('success', 'Ítem creado.');
    }

    public function edit(RentalCatalogItem $catalogItem, Request $request): View
    {
        return $request->ajax()
            ? view('rental-catalog-items._form', ['item' => $catalogItem])
            : view('rental-catalog-items.edit', ['item' => $catalogItem]);
    }

    public function update(RentalCatalogItemRequest $request, RentalCatalogItem $catalogItem): RedirectResponse
    {
        $catalogItem->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        AuditLog::record('rental_catalog_item.update', 'rental_catalog_item', (string) $catalogItem->id, 'success');

        return redirect()->route('rental-catalog-items.index')->with('success', 'Ítem actualizado.');
    }

    public function updateBankAccount(Request $request): RedirectResponse
    {
        $data = $request->validate(['bank_account_official' => ['nullable', 'string', 'max:500']]);

        Setting::set('rentals.bank_account_official', $data['bank_account_official'] ?? null);

        AuditLog::record('rental.bank_account.update', 'setting', 'rentals.bank_account_official', 'success');

        return redirect()->route('rental-catalog-items.index')->with('success', 'Cuenta oficial actualizada.');
    }
}

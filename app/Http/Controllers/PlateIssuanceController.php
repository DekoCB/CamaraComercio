<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlateIssuanceRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\PlateIssuance;
use App\Models\Setting;
use App\Services\PlateIssuanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlateIssuanceController extends Controller
{
    public function __construct(private readonly PlateIssuanceService $plates) {}

    public function index(Request $request): View
    {
        $procedureType = $request->query('procedure_type');
        $receiptType = $request->query('receipt_type');

        $records = PlateIssuance::query()
            ->with(['associate'])
            ->when($procedureType && array_key_exists($procedureType, PlateIssuance::PROCEDURE_TYPES), fn ($q) => $q->where('procedure_type', $procedureType))
            ->when($receiptType && array_key_exists($receiptType, PlateIssuance::RECEIPT_TYPES), fn ($q) => $q->where('receipt_type', $receiptType))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('plates.index', [
            'records' => $records,
            'filters' => ['procedure_type' => $procedureType, 'receipt_type' => $receiptType],
            'monthly' => $this->plates->monthlySummary(),
            'rates' => $this->plates->rates(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = ['associates' => Associate::where('is_active', true)->orderBy('name')->get(), 'rates' => $this->plates->rates()];

        return $request->ajax() ? view('plates._form', $data) : view('plates.create', $data);
    }

    public function store(PlateIssuanceRequest $request): RedirectResponse
    {
        $record = $this->plates->register($request->validated(), $request->user()->id);

        AuditLog::record('plate_issuance.register', 'plate_issuance', (string) $record->id, 'success');

        return redirect()->route('plates.show', $record)->with('success', 'Trámite registrado.');
    }

    public function show(PlateIssuance $plate): View
    {
        $plate->load(['associate', 'registeredBy']);

        return view('plates.show', ['plate' => $plate]);
    }

    public function updateRates(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        foreach (array_keys(PlateIssuance::PROCEDURE_TYPES) as $type) {
            $value = $data['rates'][$type] ?? null;
            Setting::set(PlateIssuance::rateSettingKey($type), $value !== null && $value !== '' ? (string) $value : null);
        }

        AuditLog::record('plates.rates.update', 'setting', 'plates.rate', 'success');

        return redirect()->route('plates.index')->with('success', 'Tarifas actualizadas.');
    }
}

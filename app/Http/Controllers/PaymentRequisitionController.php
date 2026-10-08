<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequisitionRequest;
use App\Models\AuditLog;
use App\Models\PaymentRequisition;
use App\Models\Setting;
use App\Services\PaymentRequisitionService;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PaymentRequisitionController extends Controller
{
    public function __construct(private readonly PaymentRequisitionService $requisitions) {}

    public function index(Request $request): View
    {
        $type = $request->query('type');

        $records = PaymentRequisition::query()
            ->when($type && array_key_exists($type, PaymentRequisition::TYPES), fn ($q) => $q->where('type', $type))
            ->orderByDesc('year')
            ->orderByDesc('sequence')
            ->paginate(20)
            ->withQueryString();

        return view('rentals.requisitions.index', [
            'records' => $records,
            'filters' => ['type' => $type],
        ]);
    }

    public function create(): View
    {
        return view('rentals.requisitions.create', [
            'defaultRecipientName' => Setting::get('rentals.requisitions.recipient_name'),
            'defaultRecipientRole' => Setting::get('rentals.requisitions.recipient_role'),
        ]);
    }

    public function store(PaymentRequisitionRequest $request): RedirectResponse
    {
        $requisition = $this->requisitions->register($request->validated(), $request->user()->id);

        AuditLog::record('payment_requisition.register', 'payment_requisition', (string) $requisition->id, 'success');

        return redirect()->route('rentals.requisitions.show', $requisition)->with('success', 'Requerimiento registrado.');
    }

    public function show(PaymentRequisition $requisition): View
    {
        $requisition->load(['items', 'creator']);

        return view('rentals.requisitions.show', ['requisition' => $requisition]);
    }

    public function pdf(PaymentRequisition $requisition): Response
    {
        $requisition->load(['items', 'creator']);

        $options = new DompdfOptions;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('rentals.requisitions.pdf.detail', ['requisition' => $requisition])->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="requerimiento-'.$requisition->year.'-'.$requisition->sequence.'.pdf"',
        ]);
    }
}

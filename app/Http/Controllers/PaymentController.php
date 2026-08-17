<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function index(): View
    {
        $payments = Payment::query()
            ->with(['invoice.associate', 'registeredBy'])
            ->when(request('associate_id'), fn ($q) => $q->whereHas('invoice', fn ($i) => $i->where('associate_id', request('associate_id'))))
            ->when(request('invoice_id'), fn ($q) => $q->where('invoice_id', request('invoice_id')))
            ->when(request('date_from'), fn ($q) => $q->whereDate('paid_at', '>=', request('date_from')))
            ->when(request('date_to'), fn ($q) => $q->whereDate('paid_at', '<=', request('date_to')))
            ->orderByDesc('paid_at')
            ->paginate(20)
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'associates' => Associate::orderBy('name')->get(['id', 'name']),
            'filters' => request()->only(['associate_id', 'invoice_id', 'date_from', 'date_to']),
        ]);
    }

    public function store(PaymentRequest $request, Invoice $invoice, PaymentService $service): RedirectResponse
    {
        $data = $request->validated();

        try {
            $payment = $service->register(
                invoice: $invoice,
                amount: (float) $data['amount'],
                paidAt: Carbon::parse($data['paid_at']),
                registeredBy: $request->user()->id,
                notes: $data['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        AuditLog::record('payment.register', 'payment', (string) $payment->id, 'success', [
            'invoice_id' => $invoice->id,
            'amount' => $data['amount'],
        ]);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Pago registrado correctamente.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceGenerateRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\InvoiceGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(): View
    {
        $invoices = Invoice::query()
            ->with('associate')
            ->when(request('associate_id'), fn ($q) => $q->where('associate_id', request('associate_id')))
            ->when(request('period'), fn ($q) => $q->forPeriod(request('period')))
            ->when(request('status'), function ($q) {
                if (request('status') === Invoice::STATUS_VENCIDA) {
                    $q->overdue();
                } else {
                    $q->where('status', request('status'));
                }
            })
            ->orderByDesc('period')
            ->orderBy('associate_id')
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'associates' => Associate::orderBy('name')->get(['id', 'name']),
            'filters' => request()->only(['associate_id', 'period', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('invoices.generate', [
            'defaultPeriod' => now()->format('Y-m'),
        ]);
    }

    public function store(InvoiceGenerateRequest $request, InvoiceGenerationService $service): RedirectResponse
    {
        $data = $request->validated();

        $summary = $service->generateForPeriod(
            period: $data['period'],
            amount: (float) $data['amount'],
            issueDate: CarbonImmutable::parse($data['issue_date']),
            dueDate: CarbonImmutable::parse($data['due_date']),
            createdBy: $request->user()->id,
        );

        AuditLog::record('invoice.generate_batch', 'invoice', $data['period'], 'success', $summary);

        $message = "Facturación de {$data['period']}: {$summary['created']} facturas creadas, {$summary['skipped']} omitidas (ya existían)";
        if ($summary['errors'] !== []) {
            $message .= ', '.count($summary['errors']).' con error.';
        } else {
            $message .= '.';
        }

        return redirect()->route('invoices.index', ['period' => $data['period']])
            ->with($summary['errors'] !== [] ? 'error' : 'success', $message)
            ->with('generationSummary', $summary);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['associate', 'payments.registeredBy', 'creator']);

        return view('invoices.show', ['invoice' => $invoice]);
    }
}

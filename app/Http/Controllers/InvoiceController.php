<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceGenerateRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Notification;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public const STATUS_FILTERS = [
        'no_pagadas' => 'No pagadas',
        'pendientes' => 'Pendientes',
        'parciales' => 'Pago parcial',
        'pagadas' => 'Pagadas',
        'vencidas' => 'Vencidas',
    ];

    public const MONTHS = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filtersFrom($request);
        $term = $filters['q'];

        $invoices = Invoice::query()
            ->with('associate')
            // Deep links from the associates list / dashboard keep working.
            ->when($filters['associate_id'], fn ($q) => $q->where('associate_id', $filters['associate_id']))
            ->when($filters['period'], fn ($q) => $q->forPeriod($filters['period']))
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('receipt_number', 'like', "%{$term}%")
                    ->orWhereHas('associate', fn ($a) => $a->where('name', 'like', "%{$term}%")
                        ->orWhere('ruc', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%"));
            }))
            ->when($filters['year'], fn ($q) => $q->where('period', 'like', $filters['year'].'-%'))
            ->when($filters['month'], fn ($q) => $q->where('period', 'like', '%-'.str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT)))
            ->when($filters['sectorista'] || $filters['category'], fn ($q) => $q->whereHas('associate', fn ($a) => $a
                ->when($filters['sectorista'], fn ($a) => $a->where('sectorista', $filters['sectorista']))
                ->when($filters['category'], fn ($a) => $a->where('category', $filters['category']))))
            ->when($filters['status'], fn ($q) => match ($filters['status']) {
                'no_pagadas' => $q->unpaid(),
                'pendientes' => $q->where('status', Invoice::STATUS_PENDIENTE),
                'parciales' => $q->where('status', Invoice::STATUS_PARCIAL),
                'pagadas' => $q->paid(),
                'vencidas' => $q->overdue(),
            })
            ->orderByDesc('period')
            ->orderBy(Associate::select('name')->whereColumn('associates.id', 'invoices.associate_id'))
            ->paginate(20)
            ->withQueryString();

        $distinct = fn (string $column) => Associate::query()
            ->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->all();

        $data = [
            'invoices' => $invoices,
            'filters' => $filters,
            'statusFilters' => self::STATUS_FILTERS,
            'months' => self::MONTHS,
            'filterOptions' => [
                'years' => Invoice::query()->selectRaw('DISTINCT SUBSTR(period, 1, 4) AS year')->orderByDesc('year')->pluck('year')->all(),
                'sectorista' => $distinct('sectorista'),
                'category' => $distinct('category'),
            ],
            'filteredAssociate' => $filters['associate_id'] ? Associate::find($filters['associate_id']) : null,
        ];

        // Live filtering (app.js data-live-filter): only the results block
        // is re-rendered while the user types.
        return $request->ajax() ? view('invoices._results', $data) : view('invoices.index', $data);
    }

    /**
     * "Ver estadísticas": charts over the same billing data, with their
     * own live filters (year defaults to the current one so the monthly
     * chart opens on something meaningful).
     */
    public function stats(Request $request, InvoiceStatsService $stats): View
    {
        $filters = $this->filtersFrom($request);
        if (! $request->has('year')) {
            $filters['year'] = now()->format('Y');
        }
        $filters = array_intersect_key($filters, array_flip(['year', 'month', 'sectorista', 'category']));

        $data = [
            'filters' => $filters,
            'stats' => $stats->build($filters),
            'months' => self::MONTHS,
            'filterOptions' => [
                'years' => InvoiceStatsService::availableYears(),
                'sectorista' => InvoiceStatsService::distinctAssociateValues('sectorista'),
                'category' => InvoiceStatsService::distinctAssociateValues('category'),
            ],
        ];

        return $request->ajax() ? view('invoices._stats', $data) : view('invoices.stats', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFrom(Request $request): array
    {
        $text = fn (string $key) => trim((string) $request->query($key, '')) !== '' ? trim((string) $request->query($key)) : null;
        $status = (string) $request->query('status', '');
        // Legacy links used the raw column value (PENDIENTE, PAGADA…).
        $status = match (strtoupper($status)) {
            Invoice::STATUS_PENDIENTE => 'pendientes',
            Invoice::STATUS_PARCIAL => 'parciales',
            Invoice::STATUS_PAGADA => 'pagadas',
            Invoice::STATUS_VENCIDA => 'vencidas',
            default => $status,
        };
        $year = (string) $request->query('year', '');
        $month = (string) $request->query('month', '');

        return [
            'q' => trim((string) $request->query('q', '')),
            'associate_id' => $request->integer('associate_id') ?: null,
            'period' => preg_match('/^\d{4}-\d{2}$/', (string) $request->query('period', '')) ? (string) $request->query('period') : null,
            'status' => array_key_exists($status, self::STATUS_FILTERS) ? $status : null,
            'year' => preg_match('/^\d{4}$/', $year) ? $year : null,
            'month' => ctype_digit($month) && (int) $month >= 1 && (int) $month <= 12 ? (int) $month : null,
            'sectorista' => $text('sectorista'),
            'category' => $text('category'),
        ];
    }

    public function create(Request $request): View
    {
        $data = [
            'defaultPeriod' => now()->format('Y-m'),
            'activeAssociatesCount' => Associate::where('is_active', true)->count(),
        ];

        return $request->ajax() ? view('invoices._generate', $data) : view('invoices.generate', $data);
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

        if ($summary['created'] > 0) {
            Notification::record(
                type: Notification::TYPE_INVOICE_GENERATED,
                title: "Facturación generada — {$summary['created']} facturas",
                message: "Período {$data['period']}",
                entityType: 'invoice',
                link: route('invoices.index', ['period' => $data['period']]),
            );
        }

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
        $invoice->load(['associate', 'payments.registeredBy', 'payments.voidedBy', 'creator']);

        // Sibling cuotas of the same associate: previous/next navigation
        // and a summary of what else they owe, so a collector calling
        // about this invoice sees the whole picture.
        $siblings = Invoice::query()
            ->where('associate_id', $invoice->associate_id)
            ->orderBy('period')
            ->get(['id', 'period', 'amount', 'paid_total', 'status', 'due_date']);

        $position = $siblings->search(fn (Invoice $i) => $i->id === $invoice->id);
        $pending = $siblings->filter(fn (Invoice $i) => $i->id !== $invoice->id && $i->balance() > 0);

        return view('invoices.show', [
            'invoice' => $invoice,
            'previousInvoice' => $position > 0 ? $siblings[$position - 1] : null,
            'nextInvoice' => $position !== false && $position < $siblings->count() - 1 ? $siblings[$position + 1] : null,
            'associateSummary' => [
                'total' => $siblings->count(),
                'pending_count' => $pending->count(),
                'pending_balance' => $pending->sum(fn (Invoice $i) => $i->balance()),
                'overdue_count' => $pending->filter(fn (Invoice $i) => $i->isOverdue())->count(),
            ],
        ]);
    }
}

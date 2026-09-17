@extends('layouts.app')

@section('title', 'Pagos')

@section('content')
    <x-page-header title="Gestión de pagos" subtitle="Cuotas mensuales de los asociados: qué está pagado, qué falta cobrar y el historial de cada pago.">
        <x-slot:actions>
            @can('payments.register')
                <a href="{{ route('payments.import.create') }}" class="btn btn-secondary btn-sm">
                    {{ icon('upload', 'icon', 16) }} Importar desde Excel
                </a>
                <a href="{{ route('payments.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Registrar pago">
                    {{ icon('plus', 'icon', 16) }} Registrar pago
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php
        $statusFilters = \App\Http\Controllers\PaymentController::STATUS_FILTERS;
        $movementFilters = \App\Http\Controllers\PaymentController::MOVEMENT_FILTERS;
        $filterLabels = [
            'q' => 'Búsqueda',
            'status' => 'Estado',
            'year' => 'Año',
            'month' => 'Mes',
            'sectorista' => 'Sectorista',
            'category' => 'Categoría',
            'associate_id' => 'Asociado',
            'invoice_id' => 'Factura',
            'date_from' => 'Desde',
            'date_to' => 'Hasta',
            'state' => 'Estado',
            'method' => 'Método de pago',
        ];
        $methodLabels = \App\Models\Payment::METHODS + ['sin_metodo' => 'Sin especificar'];
        $pretty = fn ($key, $value) => match ($key) {
            'status' => $statusFilters[$value] ?? $value,
            'state' => $movementFilters[$value] ?? $value,
            'method' => $methodLabels[$value] ?? $value,
            'month' => $months[(int) $value] ?? $value,
            'date_from', 'date_to' => format_date($value),
            default => $value,
        };
        $activeFilters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
        $baseParams = $tab === 'movimientos' ? ['tab' => 'movimientos'] : [];
    @endphp

    <nav class="tabs" aria-label="Secciones de pagos">
        <a href="{{ route('payments.index') }}" class="tab-link {{ $tab === 'cuotas' ? 'is-active' : '' }}">
            {{ icon('receipt', 'icon', 15) }} Cuotas por asociado
        </a>
        <a href="{{ route('payments.index', ['tab' => 'movimientos']) }}" class="tab-link {{ $tab === 'movimientos' ? 'is-active' : '' }}">
            {{ icon('wallet', 'icon', 15) }} Movimientos de pago
        </a>
    </nav>

    @if ($tab === 'cuotas')
        @php
            // Each card is a quick filter on `status`; the other active
            // filters (search, year, month, sectorista…) are kept.
            $withStatus = fn (?string $status) => route('payments.index', array_filter(['status' => $status] + $activeFilters, fn ($v) => $v !== null && $v !== ''));
        @endphp
        <div class="kpi-grid kpi-grid-compact">
            <x-kpi-card label="Cuotas" :value="number_format($summary['total'])" icon="receipt" variant="navy"
                        :footnote="'Facturado: '.format_money($summary['billed'])"
                        :href="$withStatus(null)" hint="Ver todas las cuotas (quitar filtro de estado)" />
            <x-kpi-card label="Pagado" :value="format_money($summary['paid'])" icon="check-circle-2" variant="teal"
                        :footnote="number_format($summary['paid_count']).' cuotas pagadas'"
                        :href="$withStatus('pagadas')" :active="$filters['status'] === 'pagadas'" hint="Ver solo cuotas pagadas" />
            <x-kpi-card label="Pendiente de cobro" :value="format_money($summary['balance'])" icon="clock" variant="warning"
                        :footnote="number_format($summary['unpaid_count']).' cuotas no pagadas'"
                        :href="$withStatus('no_pagadas')" :active="$filters['status'] === 'no_pagadas'" hint="Ver solo cuotas con saldo pendiente" />
            <x-kpi-card label="Vencidas" :value="number_format($summary['overdue_count'])" icon="alert-triangle" variant="danger"
                        :critical="$summary['overdue_count'] > 0" footnote="Con saldo y fecha de vencimiento pasada"
                        :href="$withStatus('vencidas')" :active="$filters['status'] === 'vencidas'" hint="Ver solo cuotas vencidas" />
        </div>
    @endif

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar filter-bar-grow" method="GET" action="{{ route('payments.index') }}">
                @if ($tab === 'movimientos')
                    <input type="hidden" name="tab" value="movimientos">
                @endif
                @if ($filters['associate_id'])
                    <input type="hidden" name="associate_id" value="{{ $filters['associate_id'] }}">
                @endif
                @if ($filters['invoice_id'])
                    <input type="hidden" name="invoice_id" value="{{ $filters['invoice_id'] }}">
                @endif

                <div class="search-input search-input-grow">
                    {{ icon('search', 'icon', 16) }}
                    <input type="search" name="q" class="form-control" placeholder="Buscar asociado por razón social, RUC, nombre comercial o N° de comprobante" value="{{ $filters['q'] }}">
                </div>

                @if ($tab === 'cuotas')
                    <select name="status" class="form-select form-select-sm" aria-label="Estado" onchange="this.form.submit()">
                        <option value="">Estado: todas</option>
                        @foreach ($statusFilters as $key => $label)
                            <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="year" class="form-select form-select-sm" aria-label="Año" onchange="this.form.submit()">
                        <option value="">Año: todos</option>
                        @foreach ($filterOptions['years'] as $year)
                            <option value="{{ $year }}" {{ $filters['year'] === (string) $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="month" class="form-select form-select-sm" aria-label="Mes" onchange="this.form.submit()">
                        <option value="">Mes: todos</option>
                        @foreach ($months as $number => $label)
                            <option value="{{ $number }}" {{ $filters['month'] === $number ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    <select name="state" class="form-select form-select-sm" aria-label="Estado" onchange="this.form.submit()">
                        <option value="">Estado: todos</option>
                        @foreach ($movementFilters as $key => $label)
                            <option value="{{ $key }}" {{ $filters['state'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date_from" class="form-control form-control-sm" style="width: auto" aria-label="Desde" value="{{ $filters['date_from'] }}">
                    <input type="date" name="date_to" class="form-control form-control-sm" style="width: auto" aria-label="Hasta" value="{{ $filters['date_to'] }}">
                @endif

                <select name="method" class="form-select form-select-sm" aria-label="Método de pago" onchange="this.form.submit()">
                    <option value="">Método: todos</option>
                    @foreach ($methodLabels as $key => $label)
                        <option value="{{ $key }}" {{ $filters['method'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                @foreach (['sectorista' => 'Sectorista', 'category' => 'Categoría'] as $key => $label)
                    @if ($filterOptions[$key] !== [])
                        <select name="{{ $key }}" class="form-select form-select-sm" aria-label="{{ $label }}" onchange="this.form.submit()">
                            <option value="">{{ $label }}: todos</option>
                            @foreach ($filterOptions[$key] as $option)
                                <option value="{{ $option }}" {{ $filters[$key] === $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    @endif
                @endforeach

                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                @if ($activeFilters)
                    <a href="{{ route('payments.index', $baseParams) }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($activeFilters)
            @php $paginator = $tab === 'cuotas' ? $invoices : $payments; @endphp
            <div class="filter-chips" aria-label="Filtros activos">
                <span class="filter-chips-label">{{ $paginator->total() }} resultado{{ $paginator->total() === 1 ? '' : 's' }} con:</span>
                @foreach ($activeFilters as $key => $value)
                    <a href="{{ route('payments.index', $baseParams + array_diff_key($activeFilters, [$key => true])) }}" class="filter-chip" title="Quitar este filtro">
                        <span class="filter-chip-key">{{ $filterLabels[$key] ?? $key }}</span>
                        {{ $pretty($key, $value) }}
                        {{ icon('x', 'icon', 12) }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($tab === 'cuotas')
            @if ($invoices->isEmpty())
                <x-empty-state icon="receipt" title="No hay cuotas"
                    :message="$activeFilters ? 'No se encontraron cuotas para los filtros seleccionados.' : 'Las cuotas aparecen aquí al generar la facturación del mes o al importar el padrón de asociados con sus aportes.'" />
            @else
                <div class="table-wrap">
                    <table class="data-table data-table-compact">
                        <thead>
                        <tr>
                            <th>Asociado</th>
                            <th>Período</th>
                            <th>Comprobante</th>
                            <th class="is-numeric">Monto</th>
                            <th class="is-numeric">Pagado</th>
                            <th class="is-numeric">Saldo</th>
                            <th>Fecha de pago</th>
                            <th>Estado</th>
                            <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($invoices as $invoice)
                            @php
                                $lastPayment = $invoice->activePayments->first();
                                $balance = $invoice->balance();
                            @endphp
                            <tr class="{{ $invoice->isVoided() ? 'row-voided' : '' }}">
                                <td class="cell-primary cell-clamp">
                                    <a href="{{ route('associates.show', $invoice->associate) }}" class="link-plain">{{ $invoice->associate->name }}</a>
                                    @if ($invoice->associate->ruc)
                                        <div class="text-tertiary" style="font-size: var(--text-xs); font-weight: 400;">RUC {{ $invoice->associate->ruc }}</div>
                                    @endif
                                </td>
                                <td class="cell-nowrap">
                                    {{ format_period($invoice->period) }}
                                    <div class="text-tertiary" style="font-size: var(--text-xs);">{{ $invoice->period }}</div>
                                </td>
                                <td class="cell-muted cell-nowrap">{{ $invoice->receipt_number ?? '-' }}</td>
                                <td class="is-numeric cell-money">{{ format_money($invoice->amount) }}</td>
                                <td class="is-numeric cell-money">{{ format_money($invoice->paid_total) }}</td>
                                <td class="is-numeric cell-money" style="{{ $balance > 0 ? 'color: var(--color-danger); font-weight: 600;' : '' }}">{{ format_money($balance) }}</td>
                                <td class="cell-muted cell-nowrap">
                                    @if ($lastPayment)
                                        {{ format_date($lastPayment->paid_at) }}
                                        @if ($invoice->activePayments->count() > 1)
                                            <span class="text-tertiary">(+{{ $invoice->activePayments->count() - 1 }})</span>
                                        @endif
                                        <div class="text-tertiary" style="font-size: var(--text-xs);">{{ $invoice->activePayments->map(fn ($p) => $p->methodLabel())->unique()->implode(', ') }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td><x-status-badge :status="$invoice->effectiveStatus()" /></td>
                                <td class="is-numeric">
                                    <div class="row-actions">
                                        @can('billing.view')
                                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost btn-icon" title="Ver detalle" aria-label="Ver detalle">
                                                {{ icon('eye', 'icon', 16) }}
                                            </a>
                                        @endcan
                                        @can('payments.register')
                                            @if ($balance > 0 && ! $invoice->isVoided())
                                                <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-ghost btn-icon js-modal-link" title="Registrar pago" aria-label="Registrar pago" data-modal-title="Registrar pago — {{ $invoice->associate->name }} · {{ format_period($invoice->period) }}">
                                                    {{ icon('wallet', 'icon', 16) }}
                                                </a>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <x-pagination-meta :paginator="$invoices" noun="cuotas" />
                    {{ $invoices->onEachSide(1)->links() }}
                </div>
            @endif
        @else
            @if ($payments->isEmpty())
                <x-empty-state icon="wallet" title="No hay movimientos de pago" message="No se encontraron pagos para los filtros seleccionados." />
            @else
                <div class="table-wrap">
                    <table class="data-table data-table-compact">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Asociado</th>
                            <th>Período</th>
                            <th>Comprobante</th>
                            <th class="is-numeric">Monto</th>
                            <th>Método</th>
                            <th>Registrado por</th>
                            <th>Notas</th>
                            <th>Estado</th>
                            <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="cell-muted cell-nowrap">{{ format_date($payment->paid_at) }}</td>
                                <td class="cell-primary cell-clamp">
                                    <a href="{{ route('associates.show', $payment->invoice->associate) }}" class="link-plain">{{ $payment->invoice->associate->name }}</a>
                                </td>
                                <td class="cell-muted cell-nowrap">{{ format_period($payment->invoice->period) }}</td>
                                <td class="cell-muted cell-nowrap">{{ $payment->invoice->receipt_number ?? '-' }}</td>
                                <td class="is-numeric cell-money" style="{{ $payment->isVoided() ? 'text-decoration: line-through; opacity: .6;' : '' }}">{{ format_money($payment->amount) }}</td>
                                <td class="cell-muted cell-nowrap">
                                    {{ $payment->methodLabel() }}
                                    @if ($payment->operation_number)
                                        <div class="text-tertiary" style="font-size: var(--text-xs);">N° {{ $payment->operation_number }}</div>
                                    @endif
                                </td>
                                <td class="cell-muted">{{ $payment->registeredBy->name ?? '-' }}</td>
                                <td class="cell-muted cell-clamp" title="{{ $payment->notes }}">{{ $payment->notes ?? '-' }}</td>
                                <td>
                                    @if ($payment->isVoided())
                                        <span class="badge badge-neutral" title="{{ $payment->void_reason }}">Anulado</span>
                                    @else
                                        <span class="badge badge-success">Válido</span>
                                    @endif
                                </td>
                                <td class="is-numeric">
                                    @can('billing.view')
                                        <a href="{{ route('invoices.show', $payment->invoice) }}" class="btn btn-ghost btn-icon" title="Ver factura" aria-label="Ver factura">
                                            {{ icon('eye', 'icon', 16) }}
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <x-pagination-meta :paginator="$payments" noun="pagos" />
                    {{ $payments->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection

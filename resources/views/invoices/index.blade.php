@extends('layouts.app')

@section('title', 'Facturación')

@section('content')
    <x-page-header title="Facturación" subtitle="Consulta y genera la facturación mensual de los asociados.">
        <x-slot:actions>
            <a href="{{ route('invoices.stats') }}" class="btn btn-secondary btn-sm">
                {{ icon('bar-chart-3', 'icon', 16) }} Ver estadísticas
            </a>
            @can('billing.generate')
                <a href="{{ route('invoices.import.create') }}" class="btn btn-secondary btn-sm">
                    {{ icon('upload', 'icon', 16) }} Importar desde Excel
                </a>
                <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Generar facturación del mes">
                    {{ icon('plus', 'icon', 16) }} Generar facturación del mes
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        <div class="table-toolbar">
            {{-- data-live-filter: app.js refreshes #invoice-results as the user
                 types / changes a dropdown, without a full page reload. --}}
            <form class="filter-bar filter-bar-grow" method="GET" action="{{ route('invoices.index') }}"
                  data-live-filter="#invoice-results" role="search">
                @if ($filters['associate_id'])
                    <input type="hidden" name="associate_id" value="{{ $filters['associate_id'] }}">
                @endif
                @if ($filters['period'])
                    <input type="hidden" name="period" value="{{ $filters['period'] }}">
                @endif

                <div class="search-input search-input-grow">
                    {{ icon('search', 'icon', 16) }}
                    <input type="search" name="q" class="form-control" autocomplete="off"
                           placeholder="Buscar asociado por razón social, RUC, nombre comercial o N° de comprobante"
                           value="{{ $filters['q'] }}">
                </div>

                <select name="status" class="form-select form-select-sm" aria-label="Estado">
                    <option value="">Estado: todas</option>
                    @foreach ($statusFilters as $key => $label)
                        <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="year" class="form-select form-select-sm" aria-label="Año">
                    <option value="">Año: todos</option>
                    @foreach ($filterOptions['years'] as $year)
                        <option value="{{ $year }}" {{ $filters['year'] === (string) $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>

                <select name="month" class="form-select form-select-sm" aria-label="Mes">
                    <option value="">Mes: todos</option>
                    @foreach ($months as $number => $label)
                        <option value="{{ $number }}" {{ $filters['month'] === $number ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                @foreach (['sectorista' => 'Sectorista', 'category' => 'Categoría'] as $key => $label)
                    @if ($filterOptions[$key] !== [])
                        <select name="{{ $key }}" class="form-select form-select-sm" aria-label="{{ $label }}">
                            <option value="">{{ $label }}: todos</option>
                            @foreach ($filterOptions[$key] as $option)
                                <option value="{{ $option }}" {{ $filters[$key] === $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    @endif
                @endforeach

                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                <a href="{{ route('invoices.index') }}" class="btn btn-link btn-sm js-live-clear" {{ array_filter($filters, fn ($v) => $v !== null && $v !== '') ? '' : 'hidden' }}>Limpiar</a>
            </form>
        </div>

        <div id="invoice-results" class="live-results">
            @include('invoices._results')
        </div>
    </div>
@endsection

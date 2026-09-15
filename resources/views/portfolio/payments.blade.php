@extends('layouts.app')

@section('title', 'Cartera — Historial de pagos')

@section('content')
    <x-page-header title="Seguimiento de cartera" subtitle="Todos los pagos recibidos: cuándo, de quién, cuánto y por qué medio." />

    @include('portfolio._tabs', ['active' => 'pagos'])

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar filter-bar-grow" method="GET" action="{{ route('portfolio.payments') }}" data-live-filter="#ledger-results" role="search">
                <div class="search-input search-input-grow">
                    {{ icon('search', 'icon', 16) }}
                    <input type="search" name="q" class="form-control" autocomplete="off" placeholder="Buscar por asociado, RUC o N° de comprobante" value="{{ $filters['q'] }}">
                </div>

                <select name="year" class="form-select form-select-sm" aria-label="Año">
                    <option value="">Año: todos</option>
                    @foreach ($filterOptions['payment_years'] as $year)
                        <option value="{{ $year }}" {{ $filters['year'] === (string) $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
                <select name="month" class="form-select form-select-sm" aria-label="Mes">
                    <option value="">Mes: todos</option>
                    @foreach ($months as $number => $label)
                        <option value="{{ $number }}" {{ $filters['month'] === $number ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" class="form-control form-control-sm" style="width: auto" aria-label="Desde" title="Desde" value="{{ $filters['date_from'] }}">
                <input type="date" name="date_to" class="form-control form-control-sm" style="width: auto" aria-label="Hasta" title="Hasta" value="{{ $filters['date_to'] }}">

                <select name="method" class="form-select form-select-sm" aria-label="Método de pago">
                    <option value="">Método: todos</option>
                    @foreach ($methodLabels as $key => $label)
                        <option value="{{ $key }}" {{ $filters['method'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="state" class="form-select form-select-sm" aria-label="Estado del pago">
                    <option value="">Estado: todos</option>
                    <option value="validos" {{ $filters['state'] === 'validos' ? 'selected' : '' }}>Válidos</option>
                    <option value="anulados" {{ $filters['state'] === 'anulados' ? 'selected' : '' }}>Anulados</option>
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
                <a href="{{ route('portfolio.payments') }}" class="btn btn-link btn-sm js-live-clear" {{ array_filter($filters) ? '' : 'hidden' }}>Limpiar</a>
            </form>
        </div>

        <div id="ledger-results" class="live-results">
            @include('portfolio._payments_results')
        </div>
    </div>
@endsection

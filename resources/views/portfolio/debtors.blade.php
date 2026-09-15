@extends('layouts.app')

@section('title', 'A quién falta cobrar')

@section('content')
    <x-page-header title="Seguimiento de cartera" subtitle="Asociados con saldo pendiente, ordenados por deuda, con su contacto a mano para la gestión de cobranza." />

    @include('portfolio._tabs', ['active' => 'deudores'])

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar filter-bar-grow" method="GET" action="{{ route('portfolio.debtors') }}" data-live-filter="#debtors-results" role="search">
                <div class="search-input search-input-grow">
                    {{ icon('search', 'icon', 16) }}
                    <input type="search" name="q" class="form-control" autocomplete="off" placeholder="Buscar por razón social, nombre comercial o RUC" value="{{ $filters['q'] }}">
                </div>
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
                <select name="sort" class="form-select form-select-sm" aria-label="Orden">
                    <option value="balance" {{ $filters['sort'] === 'balance' ? 'selected' : '' }}>Orden: mayor deuda</option>
                    <option value="name" {{ $filters['sort'] === 'name' ? 'selected' : '' }}>Orden: nombre</option>
                </select>
                <label class="form-check" style="font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 6px; margin: 0;">
                    <input type="checkbox" class="form-check-input" name="only_overdue" value="1" {{ $filters['only_overdue'] ? 'checked' : '' }} style="margin: 0;">
                    Solo con facturas vencidas
                </label>
                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                <a href="{{ route('portfolio.debtors') }}" class="btn btn-link btn-sm js-live-clear" {{ array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== false && $v !== 'balance') ? '' : 'hidden' }}>Limpiar</a>
            </form>
        </div>

        <div id="debtors-results" class="live-results">
            @include('portfolio._debtors_results')
        </div>
    </div>
@endsection

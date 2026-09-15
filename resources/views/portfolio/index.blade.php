@extends('layouts.app')

@section('title', 'Cartera')

@section('content')
    <x-page-header title="Seguimiento de cartera" subtitle="Qué se ha facturado, qué se ha cobrado y qué falta por cobrar a cada asociado." />

    @include('portfolio._tabs', ['active' => 'cartera'])

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar filter-bar-grow" method="GET" action="{{ route('portfolio.index') }}" data-live-filter="#portfolio-results" role="search">
                <div class="search-input search-input-grow">
                    {{ icon('search', 'icon', 16) }}
                    <input type="search" name="q" class="form-control" autocomplete="off" placeholder="Buscar por razón social, nombre comercial o RUC" value="{{ $filters['q'] }}">
                </div>
                <select name="status" class="form-select form-select-sm" aria-label="Situación">
                    <option value="">Situación: todos</option>
                    @foreach ($statusFilters as $key => $label)
                        <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
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
                <a href="{{ route('portfolio.index') }}" class="btn btn-link btn-sm js-live-clear" {{ array_filter($filters) ? '' : 'hidden' }}>Limpiar</a>
            </form>
        </div>

        <div id="portfolio-results" class="live-results">
            @include('portfolio._index_results')
        </div>
    </div>
@endsection

{{-- $activeFilters, $filterLabels, $pretty, $route, $count, $noun --}}
@if ($activeFilters)
    <div class="filter-chips" aria-label="Filtros activos">
        <span class="filter-chips-label">{{ number_format($count) }} {{ $noun }} con:</span>
        @foreach ($activeFilters as $key => $value)
            <a href="{{ route($route, array_diff_key($activeFilters, [$key => true])) }}" class="filter-chip js-live-link" title="Quitar este filtro">
                <span class="filter-chip-key">{{ $filterLabels[$key] ?? $key }}</span>
                {{ $pretty($key, $value) }}
                {{ icon('x', 'icon', 12) }}
            </a>
        @endforeach
    </div>
@endif

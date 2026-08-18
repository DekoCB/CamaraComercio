@props(['paginator', 'noun' => 'resultados'])
@if ($paginator->total() > 0)
    <span class="table-pagination-meta">
        Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} {{ $noun }}
    </span>
@endif

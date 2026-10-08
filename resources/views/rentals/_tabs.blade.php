<nav class="tabs" aria-label="Secciones de Alquileres">
    <a href="{{ route('rentals.index') }}" class="tab-link {{ $active === 'listado' ? 'is-active' : '' }}">
        {{ icon('list-filter', 'icon', 15) }} Listado
    </a>
    <a href="{{ route('rentals.calendar') }}" class="tab-link {{ $active === 'calendario' ? 'is-active' : '' }}">
        {{ icon('calendar', 'icon', 15) }} Calendario
    </a>
    @can('rentals.requisitions.manage')
        <a href="{{ route('rentals.requisitions.index') }}" class="tab-link {{ $active === 'requerimientos' ? 'is-active' : '' }}">
            {{ icon('receipt', 'icon', 15) }} Requerimientos de pago
        </a>
    @endcan
</nav>

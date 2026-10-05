<nav class="tabs" aria-label="Secciones de asociados">
    <a href="{{ route('associates.index') }}" class="tab-link {{ $active === 'listado' ? 'is-active' : '' }}">
        {{ icon('users', 'icon', 15) }} Listado de asociados
    </a>
    <a href="{{ route('associates.birthdays') }}" class="tab-link {{ $active === 'cumpleanos' ? 'is-active' : '' }}">
        {{ icon('cake', 'icon', 15) }} Cumpleaños de socios
    </a>
    <a href="{{ route('benefits.index') }}" class="tab-link {{ $active === 'beneficios' ? 'is-active' : '' }}">
        {{ icon('gift', 'icon', 15) }} Beneficios
    </a>
</nav>

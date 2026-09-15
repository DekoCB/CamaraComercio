<nav class="tabs" aria-label="Secciones de cartera">
    <a href="{{ route('portfolio.index') }}" class="tab-link {{ $active === 'cartera' ? 'is-active' : '' }}">
        {{ icon('trending-up', 'icon', 15) }} Cartera por asociado
    </a>
    <a href="{{ route('portfolio.payments') }}" class="tab-link {{ $active === 'pagos' ? 'is-active' : '' }}">
        {{ icon('wallet', 'icon', 15) }} Historial de pagos
    </a>
    <a href="{{ route('portfolio.debtors') }}" class="tab-link {{ $active === 'deudores' ? 'is-active' : '' }}">
        {{ icon('alert-triangle', 'icon', 15) }} A quién falta cobrar
    </a>
</nav>

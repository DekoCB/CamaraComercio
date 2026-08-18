<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sistema de Facturación') · Cámara de Comercio</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    @stack('styles')
</head>
<body>
<a href="#main-content" class="skip-link">Saltar al contenido</a>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <x-brand-mark />
            <div class="sidebar-brand-text">
                <strong>Cámara de Comercio</strong>
                <span>Gestión de Facturación</span>
            </div>
        </div>
        <nav class="sidebar-nav" aria-label="Navegación principal">
            @module('dashboard')
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard">
                    {{ icon('layout-dashboard') }}<span>Dashboard</span>
                </a>
            @endmodule
            @module('associates')
                <a href="{{ route('associates.index') }}" class="nav-link {{ request()->routeIs('associates.*') ? 'active' : '' }}" title="Asociados">
                    {{ icon('users') }}<span>Asociados</span>
                </a>
            @endmodule
            @module('billing')
                @can('billing.view')
                    <a href="{{ route('invoices.index') }}" class="nav-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}" title="Facturación">
                        {{ icon('file-text') }}<span>Facturación</span>
                    </a>
                @endcan
            @endmodule
            @module('payments')
                @can('payments.register')
                    <a href="{{ route('payments.index') }}" class="nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}" title="Pagos">
                        {{ icon('wallet') }}<span>Pagos</span>
                    </a>
                @endcan
            @endmodule
            @module('portfolio')
                @can('portfolio.view')
                    <a href="{{ route('portfolio.index') }}" class="nav-link {{ request()->routeIs('portfolio.*') || request()->routeIs('associates.statement') ? 'active' : '' }}" title="Cartera">
                        {{ icon('trending-up') }}<span>Cartera</span>
                    </a>
                @endcan
            @endmodule
            @module('reports')
                @can('reports.view')
                    <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" title="Reportes">
                        {{ icon('bar-chart-3') }}<span>Reportes</span>
                    </a>
                @endcan
            @endmodule
            @module('administration')
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" title="Administración">
                    {{ icon('settings') }}<span>Administración</span>
                </a>
            @endmodule
        </nav>
        <div class="sidebar-collapse-toggle">
            <button type="button" id="sidebarCollapseToggle" aria-label="Contraer u expandir el menú">
                {{ icon('chevron-left', 'icon icon-collapse') }}
            </button>
        </div>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <button class="icon-btn d-lg-none" id="sidebarToggle" type="button" aria-label="Abrir menú">
                {{ icon('menu') }}
            </button>
            <div class="topbar-breadcrumb">
                <strong>@yield('title', '')</strong>
            </div>
            <div class="dropdown">
                <button class="topbar-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar">{{ Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                    <span class="topbar-user-meta">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ auth()->user()->role->name }}</span>
                    </span>
                    {{ icon('chevron-down', 'icon text-tertiary') }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-secondary" style="font-size: 0.8rem">{{ auth()->user()->email }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">{{ icon('log-out') }} Cerrar sesión</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content" id="main-content">
            @yield('content')
        </main>
    </div>
</div>

{{-- Confirmation modal — replaces window.confirm() for every data-confirm form (app.js) --}}
<div class="modal-backdrop-custom" id="confirmModalBackdrop"></div>
<div class="modal-dialog-custom" id="confirmModalDialog" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="modal-panel">
        <div class="modal-icon" id="confirmModalIcon">{{ icon('alert-triangle') }}</div>
        <h3 id="confirmModalTitle">¿Confirmar acción?</h3>
        <p id="confirmModalMessage"></p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="confirmModalCancel">Cancelar</button>
            <button type="button" class="btn btn-primary" id="confirmModalAccept">Confirmar</button>
        </div>
    </div>
</div>

{{-- Toasts: server flash messages rendered as auto-dismissing notifications instead of a static banner --}}
<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<script type="application/json" id="flashData">{!! json_encode(['success' => session('success') ? [session('success')] : [], 'error' => session('error') ? [session('error')] : []]) !!}</script>
<script>
    window.__iconSvgs = {
        'check-circle-2': {!! json_encode((string) icon('check-circle-2')) !!},
        'x-circle': {!! json_encode((string) icon('x-circle')) !!},
        'alert-triangle': {!! json_encode((string) icon('alert-triangle')) !!},
        'info': {!! json_encode((string) icon('info')) !!}
    };
</script>

<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sistema de Facturación') · Cámara de Comercio</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-building"></i>
            <span>Cámara de Comercio</span>
        </div>
        <nav class="sidebar-nav">
            @module('dashboard')
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                </a>
            @endmodule
            @module('associates')
                <a href="{{ route('associates.index') }}" class="nav-link {{ request()->routeIs('associates.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i><span>Asociados</span>
                </a>
            @endmodule
            @module('billing')
                @can('billing.view')
                    <a href="{{ route('invoices.index') }}" class="nav-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                        <i class="bi bi-receipt"></i><span>Facturación</span>
                    </a>
                @endcan
            @endmodule
            @module('payments')
                @can('payments.register')
                    <a href="{{ route('payments.index') }}" class="nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                        <i class="bi bi-cash-coin"></i><span>Pagos</span>
                    </a>
                @endcan
            @endmodule
            @module('portfolio')
                @can('portfolio.view')
                    <a href="{{ route('portfolio.index') }}" class="nav-link {{ request()->routeIs('portfolio.*') || request()->routeIs('associates.statement') ? 'active' : '' }}">
                        <i class="bi bi-graph-up"></i><span>Cartera</span>
                    </a>
                @endcan
            @endmodule
            @module('reports')
                @can('reports.view')
                    <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart"></i><span>Reportes</span>
                    </a>
                @endcan
            @endmodule
            @module('administration')
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                    <i class="bi bi-gear"></i><span>Administración</span>
                </a>
            @endmodule
        </nav>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-title">@yield('title', '')</div>
            <div class="topbar-user dropdown">
                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted small">{{ auth()->user()->role->name }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sistema de Facturación') · Cámara de Comercio</title>
    <script>
        // Keeps the guest screens (login, etc.) in whatever theme the user
        // last chose inside the app — applied before first paint, same as
        // layouts/app.blade.php.
        (function () {
            try {
                var theme = localStorage.getItem('cc_theme');
                if (theme === 'dark' || theme === 'light') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="guest-shell">
    <div class="guest-branding">
        <div class="guest-branding-content">
            <x-brand-mark :size="52" />
            <h1>Cámara de Comercio</h1>
            <p>Plataforma para gestionar la facturación y cobranza mensual de los asociados, con control claro de la deuda pendiente.</p>
            <div class="guest-branding-features">
                <div class="feature">
                    <span class="icon-wrap">{{ icon('file-text', 'icon', 16) }}</span>
                    <span>Facturación mensual centralizada</span>
                </div>
                <div class="feature">
                    <span class="icon-wrap">{{ icon('wallet', 'icon', 16) }}</span>
                    <span>Pagos totales y parciales con saldo en tiempo real</span>
                </div>
                <div class="feature">
                    <span class="icon-wrap">{{ icon('trending-up', 'icon', 16) }}</span>
                    <span>Cartera y morosidad siempre visibles</span>
                </div>
            </div>
        </div>
    </div>

    <div class="guest-form-side">
        <div class="guest-form-card">
            @if (session('status'))
                <div class="badge badge-success" style="display: flex; padding: var(--space-3); border-radius: var(--radius-sm); margin-bottom: var(--space-5); font-size: 0.8125rem; white-space: normal; text-align: left;">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="badge badge-danger" style="display: flex; padding: var(--space-3); border-radius: var(--radius-sm); margin-bottom: var(--space-5); font-size: 0.8125rem; white-space: normal; text-align: left;">
                    <ul class="mb-0 ps-3" style="list-style: disc;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </div>
    </div>
</div>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>

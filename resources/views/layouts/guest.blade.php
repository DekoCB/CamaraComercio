<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sistema de Facturación') · Cámara de Comercio</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
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
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body>
<div class="guest-shell">
    <div class="guest-form-side">
        <div class="guest-form-middle">
            <div class="guest-form-card">
                <img src="{{ asset('images/logo.png') }}" alt="Cámara de Comercio de Huancayo" class="guest-logo">
                @if (session('status'))
                    <div class="guest-alert guest-alert-ok">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="guest-alert guest-alert-error">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </div>

        <div class="guest-form-footer">
            <span>&copy; {{ now()->year }} Cámara de Comercio</span>
        </div>
    </div>

    <div class="guest-photo-side" role="img" aria-label="Plaza y catedral de Huancayo"></div>
</div>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/password-toggle.js') }}?v={{ filemtime(public_path('assets/js/password-toggle.js')) }}"></script>
</body>
</html>

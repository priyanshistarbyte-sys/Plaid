<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', 'Log in') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/favicon.svg') }}">

    {{-- Same pre-paint theme bootstrap as the app shell, sharing its stored choice --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('hrm-theme');
                var theme = saved || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @stack('styles')
</head>
<body>
    <div class="auth-container">
        <button type="button" id="themeToggle" class="auth-theme-toggle"
                title="Switch theme" aria-label="Switch between light and dark theme">
            <i class="bi bi-moon-stars theme-icon-dark"></i>
            <i class="bi bi-sun theme-icon-light"></i>
        </button>

        @yield('content')
    </div>

    <script>
        document.getElementById('themeToggle').addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('hrm-theme', next); } catch (e) { /* private mode — session only */ }
        });
    </script>
    @stack('scripts')
</body>
</html>

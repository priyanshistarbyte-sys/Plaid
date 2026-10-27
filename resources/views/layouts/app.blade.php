<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('page-title')@yield('page-title') · @endif{{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/favicon.svg') }}">

    {{-- Theme must be applied before the first paint, or the page flashes the wrong palette. --}}
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

    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/toastr.min.css') }}">
    {{-- Theme (loaded last so it wins over the vendor sheets) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">

    @stack('styles')
</head>
<body>
<nav class="navbar navbar-dark navbar-main">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ route('plaid.index') }}">
            <i class="bi bi-bank2 me-2"></i>{{ config('app.name') }}
        </a>

        <button class="nav-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#navSidebar"
                aria-controls="navSidebar" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>

        <div class="offcanvas-md offcanvas-end nav-collapse" tabindex="-1" id="navSidebar" aria-labelledby="navSidebarLabel">
            <div class="offcanvas-header">
                <h6 class="offcanvas-title" id="navSidebarLabel"><i class="bi bi-bank2 me-2"></i>{{ config('app.name') }}</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#navSidebar" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="nav-links">
                    <a href="{{ route('plaid.index') }}" class="nav-btn {{ request()->routeIs('plaid.*') ? 'active' : '' }}">
                        <i class="bi bi-receipt"></i> Transactions
                    </a>
                    <a href="{{ route('settings.edit') }}" class="nav-btn {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </div>

                <div class="nav-user">
                    <div class="nav-dropdown dropdown user-dropdown">
                        <a href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                           class="nav-btn nav-user-badge dropdown-toggle" title="Signed in as {{ auth()->user()->name }}">
                            <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end nav-dropdown-menu">
                            <li class="dropdown-header">{{ auth()->user()->email }}</li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item danger">
                                        <i class="bi bi-box-arrow-right"></i> Log out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>

                    {{-- Light / dark switch. The icon shown is the theme you'd switch TO. --}}
                    <button type="button" id="themeToggle" class="nav-btn nav-btn-icon theme-toggle"
                            title="Switch theme" aria-label="Switch between light and dark theme">
                        <i class="bi bi-moon-stars theme-icon-dark"></i>
                        <i class="bi bi-sun theme-icon-light"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="page-wrap">
    @if (trim($__env->yieldContent('page-title')) !== '')
        <div class="page-header">
            <div>
                <div class="dash-title">@yield('page-title')</div>
                @if (trim($__env->yieldContent('page-subtitle')) !== '')
                    <div class="dash-subtitle">@yield('page-subtitle')</div>
                @endif
            </div>
            @if (trim($__env->yieldContent('page-actions')) !== '')
                <div class="header-actions">@yield('page-actions')</div>
            @endif
        </div>
    @endif

    @yield('content')
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/toastr.min.js') }}"></script>
<script src="{{ asset('assets/js/toastr.js') }}"></script>

<script>
    document.getElementById('themeToggle').addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('hrm-theme', next); } catch (e) { /* private mode — session only */ }
    });
</script>

<!-- Flash messages -->
<script>
    @if (session('success'))
        toastr.success(@json(session('success')));
    @endif
    @if (session('error'))
        toastr.error(@json(session('error')));
    @endif
</script>

@stack('scripts')
</body>
</html>

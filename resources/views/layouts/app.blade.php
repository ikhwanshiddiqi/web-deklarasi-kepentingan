<!DOCTYPE html>
<html lang="id" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full app-shell text-slate-800 antialiased">
    <div class="min-h-screen">
        @auth
            @php
                $user = auth()->user()?->loadMissing('profile');
                $isAdmin = $user?->isAdmin();
                $existingDeclaration = $user?->latestDeclaration();
            @endphp

            {{-- Tanpa class "sidebar-open": di layar kecil sidebar mulai tertutup. --}}
            <div class="dashboard-layout">
                <button type="button" class="mobile-sidebar-toggle" aria-label="Buka sidebar" aria-controls="app-sidebar"
                    aria-expanded="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path class="icon-open" d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" />
                        <path class="icon-close" d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="sidebar-backdrop" aria-hidden="true"></div>

                <aside class="app-sidebar" id="app-sidebar">
                    <div class="sidebar-inner">
                        <div class="brand-wrap">
                            <div class="brand-logo">
                                <img src="{{ asset('pict/logosp.png') }}" alt="logo">
                            </div>
                            <div>
                                <p class="brand-label">Portal</p>
                                <h1 class="brand-name">GRC</h1>
                            </div>
                        </div>

                        <nav class="nav-menu">
                            @if ($isAdmin)
                                <a href="{{ route('admin.home') }}"
                                    class="nav-item {{ request()->routeIs('admin.home') ? 'active' : '' }}">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M4 10.5 12 4l8 6.5V18a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-7.5Z"
                                                fill="none" stroke="currentColor" stroke-width="1.7"
                                                stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <span>Beranda</span>
                                </a>
                                <a href="{{ route('admin.data.index') }}"
                                    class="nav-item {{ request()->routeIs('admin.data.index') || request()->routeIs('declarations.show') ? 'active' : '' }}">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path
                                                d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13A1.5 1.5 0 0 1 18.5 20h-13A1.5 1.5 0 0 1 4 18.5v-13Zm3 0v13m5-13v13m5-13v13M4 9h16M4 15h16"
                                                fill="none" stroke="currentColor" stroke-width="1.7"
                                                stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Data</span>
                                </a>
                                <a href="{{ route('admin.users.index') }}"
                                    class="nav-item {{ request()->routeIs('admin.users.index') ? 'active' : '' }}">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path
                                                d="M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5 8c.7-2.2 2.4-3.5 5-3.5s4.3 1.3 5 3.5M17 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm3 9c-.7-1.8-2.1-2.9-4.2-3.2"
                                                fill="none" stroke="currentColor" stroke-width="1.7"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <span>Kelola Akun</span>
                                </a>
                            @else
                                <a href="{{ route('home') }}"
                                    class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                                    <span class="nav-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M4 10.5 12 4l8 6.5V18a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-7.5Z"
                                                fill="none" stroke="currentColor" stroke-width="1.7"
                                                stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <span>Beranda</span>
                                </a>

                                @if ($user?->hasCompleteProfile())
                                    <a href="{{ $existingDeclaration ? route('declarations.show', $existingDeclaration) : route('declarations.create') }}"
                                        class="nav-item {{ request()->routeIs('declarations.*') ? 'active' : '' }}"
                                        data-isi-form="enabled">
                                        <span class="nav-icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path
                                                    d="M7 4.75A1.75 1.75 0 0 1 8.75 3h6.5A1.75 1.75 0 0 1 17 4.75V18a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4.75Zm2.25.75h5.5m-5.5 4h5.5m-5.5 4h3.5"
                                                    fill="none" stroke="currentColor" stroke-width="1.7"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <span>Isi Form</span>
                                    </a>
                                @else
                                    <span title="Lengkapi profil Anda terlebih dahulu untuk mengisi form deklarasi."
                                        class="nav-item nav-item-disabled" data-isi-form-disabled="true">
                                        <span class="nav-icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path
                                                    d="M7 4.75A1.75 1.75 0 0 1 8.75 3h6.5A1.75 1.75 0 0 1 17 4.75V18a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4.75Zm2.25.75h5.5m-5.5 4h5.5m-5.5 4h3.5"
                                                    fill="none" stroke="currentColor" stroke-width="1.7"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <span>Isi Form</span>
                                    </span>
                                @endif
                            @endif

                            <a href="{{ route('account.settings') }}"
                                class="nav-item {{ request()->routeIs('account.settings') ? 'active' : '' }}" hidden>
                                <span class="nav-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path
                                            d="M12 3.5v2.1m0 12.8v2.1m8.5-8.5h-2.1M5.6 12H3.5m15.4-5.4-1.5 1.5M7.6 16.4l-1.5 1.5m0-10.9 1.5 1.5m9.4 9.4 1.5 1.5M12 8.2a3.8 3.8 0 1 1 0 7.6 3.8 3.8 0 0 1 0-7.6Z"
                                            fill="none" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" />
                                    </svg>
                                </span>
                                <span>Settings</span>
                            </a>
                        </nav>

                        <div class="sidebar-footer">
                            <div class="user-card">
                                <p class="user-card-label">Akun</p>
                                <p class="user-card-name">
                                    {{ $user?->profile?->full_name ?? ($user?->email ?? 'User') }}
                                </p>
                                <span class="role-badge">
                                    {{ $isAdmin ? 'Admin' : 'Employee' }}
                                </span>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <a href="{{ route('logout') }}" class="logout-btn"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Logout
                                </a>
                            </form>
                        </div>
                    </div>
                </aside>

                <div class="content-area">
                    <header class="topbar">
                        <div class="topbar-inner">
                            <div class="topbar-actions">
                                <div class="avatar-mini">
                                    {{ strtoupper(substr($user?->profile?->full_name ?? ($user?->email ?? 'U'), 0, 1)) }}
                                </div>
                                <div class="topbar-user-label">
                                    {{ $user?->profile?->full_name ?? ($user?->email ?? 'User') }}</div>
                            </div>
                        </div>
                    </header>

                    <main class="page-main">
                        @if (session('warning'))
                            <div role="alert" class="flash-warning">
                                {{ session('warning') }}
                            </div>
                        @endif

                        @yield('content')
                    </main>
                </div>
            </div>
        @else
            <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        @endauth
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const layout = document.querySelector('.dashboard-layout');
            const toggle = document.querySelector('.mobile-sidebar-toggle');
            const backdrop = document.querySelector('.sidebar-backdrop');

            if (!layout || !toggle) {
                return;
            }

            const mobileQuery = window.matchMedia('(max-width: 900px)');

            function setOpen(open) {
                layout.classList.toggle('sidebar-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Tutup sidebar' : 'Buka sidebar');
            }

            toggle.addEventListener('click', function() {
                setOpen(!layout.classList.contains('sidebar-open'));
            });

            // Klik area gelap di luar sidebar = tutup
            backdrop?.addEventListener('click', function() {
                setOpen(false);
            });

            // Tombol Esc = tutup
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    setOpen(false);
                }
            });

            // Pindah halaman lewat menu di layar kecil = tutup dulu
            layout.querySelectorAll('.app-sidebar a').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (mobileQuery.matches) {
                        setOpen(false);
                    }
                });
            });

            // Ganti ukuran layar (mis. rotasi/resize) = reset ke tertutup
            mobileQuery.addEventListener('change', function() {
                setOpen(false);
            });
        });
    </script>
</body>

</html>

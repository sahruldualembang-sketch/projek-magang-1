<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SIM-LJI') · PT Liny Jaya Informatika</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo-pt-lji.jpeg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    $currentUser = auth()->user();
    $navigation = [
        ['label' => 'MENU UTAMA', 'items' => [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => '▦', 'active' => request()->routeIs('dashboard'), 'url' => route('dashboard')],
        ]],
        ['label' => 'SUMBER DAYA MANUSIA', 'items' => [
            ['slug' => 'employees', 'label' => 'Data Karyawan', 'icon' => '♙', 'group' => 'HR'],
            ['slug' => 'attendances', 'label' => $currentUser->role === 'staff' ? 'Absensi Saya' : 'Data Absensi', 'icon' => '◷', 'group' => 'HR'],
            ['slug' => 'payrolls', 'label' => 'Data Gaji', 'icon' => 'Rp', 'group' => 'HR'],
        ]],
        ['label' => 'OPERASIONAL & BISNIS', 'items' => [
            ['slug' => 'activities', 'label' => 'Aktivitas Karyawan', 'icon' => '☷', 'group' => 'OPERASIONAL'],
            ['slug' => 'assets', 'label' => 'Inventaris Perusahaan', 'icon' => '▦', 'group' => 'OPERASIONAL'],
            ['slug' => 'clients', 'label' => 'Data Klien', 'icon' => '♧', 'group' => 'BISNIS'],
            ['slug' => 'orders', 'label' => 'Pemesanan / Penjualan', 'icon' => '▤', 'group' => 'BISNIS'],
        ]],
        ['label' => 'DOKUMEN & KEUANGAN', 'items' => [
            ['slug' => 'contracts', 'label' => 'Arsip Kontrak', 'icon' => '▣', 'group' => 'DOKUMEN'],
            ['slug' => 'legal_documents', 'label' => 'Legalitas Perusahaan', 'icon' => '⌑', 'group' => 'DOKUMEN'],
            ['slug' => 'invoices', 'label' => 'Data Invoice', 'icon' => '▧', 'group' => 'KEUANGAN'],
            ['slug' => 'expenses', 'label' => 'Pengeluaran Perusahaan', 'icon' => '↗', 'group' => 'KEUANGAN'],
        ]],
    ];
    $navigation = array_values(array_filter(array_map(function ($section) use ($currentUser) {
        $section['items'] = array_values(array_filter($section['items'], function ($item) use ($currentUser) {
            return !isset($item['slug'])
                || $currentUser->hasRole(config("simlji.{$item['slug']}.roles", []));
        }));

        return $section;
    }, $navigation), function ($section) {
        return $section['items'] !== [];
    }));
@endphp

<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-mark"><img class="brand-mark-logo" src="{{ asset('assets/logo.webp') }}" alt="Logo PT Liny Jaya Informatika" width="30" height="30"></span>
            <span class="brand-info">
                <strong>SIM-LJI</strong>
                <small>PT Liny Jaya Informatika</small>
            </span>
            <button class="sidebar-close" type="button" aria-label="Tutup navigasi"
                    onclick="document.getElementById('sidebar').classList.remove('show')">×</button>
        </a>

        <div class="sidebar-scroll">
            @foreach($navigation as $section)
                <div class="nav-section">
                    <div class="nav-caption">{!! $section['label'] !!}</div>
                    @foreach($section['items'] as $item)
                        @if(isset($item['slug']))
                            <a class="nav-link {{ request()->is('modul/'.$item['slug'].'*') ? 'active' : '' }}"
                               href="{{ route('modules.index', $item['slug']) }}">
                                <span class="nav-icon">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @else
                            <a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}">
                                <span class="nav-icon">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endforeach

            @if($currentUser->hasRole(['super_admin']))
                <div class="nav-section">
                    <div class="nav-caption">PENGELOLAAN SISTEM</div>
                    <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                       href="{{ route('users.index') }}">
                        <span class="nav-icon">♙</span><span>Pengguna Sistem</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('settings.attendance') ? 'active' : '' }}"
                       href="{{ route('settings.attendance') }}">
                        <span class="nav-icon">⚙</span><span>Pengaturan</span>
                    </a>
                </div>
            @endif

            @if($currentUser->hasRole(['super_admin', 'director', 'finance']))
                <div class="nav-section">
                    <div class="nav-caption">ANALISIS & LAPORAN</div>
                    <a class="nav-link {{ request()->routeIs('profit-loss') ? 'active' : '' }}"
                       href="{{ route('profit-loss') }}">
                        <span class="nav-icon">◫</span><span>Laporan Laba/Rugi</span>
                    </a>
                </div>
            @endif
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <span class="avatar">{{ strtoupper(substr($currentUser->name, 0, 1)) }}</span>
                <span class="user-meta">
                    <strong>{{ $currentUser->name }}</strong>
                    <small>{{ str_replace('_', ' ', $currentUser->role) }}</small>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="logout-button" type="submit" title="Keluar" aria-label="Keluar">⇥</button>
                </form>
            </div>
        </div>
    </aside>

    <button class="sidebar-backdrop" type="button" aria-label="Tutup navigasi"
            onclick="document.getElementById('sidebar').classList.remove('show')"></button>

    <main class="main">
        <header class="topbar">
            <div class="topbar-start">
                <button class="mobile-menu" type="button" aria-label="Buka navigasi"
                        onclick="document.getElementById('sidebar').classList.toggle('show')">☰</button>
                <div class="topbar-heading">
                    <span class="eyebrow">SISTEM INFORMASI MANAJEMEN</span>
                    <h1>@yield('heading', 'Dashboard')</h1>
                </div>
            </div>
            <div class="topbar-end">
                <div class="topbar-date">
                    <span class="date-symbol">◷</span>
                    <span>{{ now()->translatedFormat('l, d F Y') }}</span>
                </div>
                <span class="topbar-divider"></span>
                <div class="topbar-account">
                    <span class="topbar-avatar">{{ strtoupper(substr($currentUser->name, 0, 1)) }}</span>
                    <span class="topbar-account-copy">
                        <strong>{{ $currentUser->name }}</strong>
                        <small>{{ str_replace('_', ' ', $currentUser->role) }}</small>
                    </span>
                </div>
            </div>
        </header>

        <section class="content">
            @if(session('success'))
                <div class="alert success"><span class="alert-symbol">✓</span><span>{{ session('success') }}</span></div>
            @endif
            @if(session('error'))
                <div class="alert error"><span class="alert-symbol">!</span><span>{{ session('error') }}</span></div>
            @endif
            @if($errors->any())
                <div class="alert error"><span class="alert-symbol">!</span><span>Periksa kembali input. {{ $errors->first() }}</span></div>
            @endif
            @yield('content')
        </section>

        <footer class="footer">
            <span>© {{ date('Y') }} PT Liny Jaya Informatika</span>
            <span>SIM-LJI <i></i> Sistem Manajemen Perusahaan</span>
        </footer>
    </main>
</div>
</body>
</html>

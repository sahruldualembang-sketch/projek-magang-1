<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk · SIM-LJI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    {{-- Ambient animated background — decorative only --}}
    <div class="login-bg" aria-hidden="true">
        <div class="login-bg-wash"></div>
        <div class="login-grid-veil"></div>

        <div class="login-parallax login-parallax--far">
            <span class="login-blob login-blob--a"></span>
            <span class="login-blob login-blob--b"></span>
        </div>
        <div class="login-parallax login-parallax--near">
            <span class="login-blob login-blob--c"></span>
            <span class="login-blob login-blob--d"></span>
        </div>

        <span class="login-thread login-thread--one"></span>
        <span class="login-thread login-thread--two"></span>
        <span class="login-orb login-orb--one"></span>
        <span class="login-orb login-orb--two"></span>
        <span class="login-orb login-orb--three"></span>
        <span class="login-cursor-glow"></span>
    </div>

    <main class="login-shell {{ $errors->any() ? 'has-error' : '' }}">
        <div class="login-card-glow" aria-hidden="true"></div>
        <div class="login-brand-chip">
            <img class="login-chip-logo" src="{{ asset('assets/logo.webp') }}" alt="Logo PT Liny Jaya Informatika" width="34" height="34">
        </div>

        <form class="login-card"
              method="POST"
              action="{{ route('login') }}"
              data-state="{{ $errors->any() ? 'error' : 'idle' }}"
              x-data="{ loading: false }"
              x-on:submit="loading = true"
              x-bind:class="{ 'is-loading': loading }"
              x-bind:data-state="loading ? 'loading' : '{{ $errors->any() ? 'error' : 'idle' }}'">
            @csrf
            <div class="login-card-inner">
                <header class="login-header">
                    <span class="login-kicker">PT Liny Jaya Informatika</span>
                    <h1>Selamat datang kembali</h1>
                    <p>Masuk menggunakan akun perusahaan untuk melanjutkan ke SIM-LJI.</p>
                </header>

                @if($errors->any())
                    <div class="alert error login-alert" role="alert">
                        <span class="alert-symbol">!</span>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <div class="login-form">
                    <div class="login-field">
                        <div class="login-control">
                            <svg class="login-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="2.5" y="4" width="15" height="12" rx="2.5"/>
                                <path d="m3.5 6 6.5 4.7L16.5 6"/>
                            </svg>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder=" ">
                            <label for="email">Alamat email</label>
                        </div>
                    </div>

                    <div class="login-field" x-data="{ show: false, value: '' }">
                        <div class="login-control has-toggle" x-bind:class="{ 'has-value': value.length > 0 }">
                            <svg class="login-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="4" y="8.5" width="12" height="9" rx="2.2"/>
                                <path d="M7 8.5V6a3 3 0 0 1 6 0v2.5"/>
                                <circle cx="10" cy="13" r="1.2" fill="currentColor" stroke="none"/>
                            </svg>
                            <input id="password" x-model="value" x-bind:type="show ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password" placeholder=" ">
                            <label for="password">Kata sandi</label>
                            <button class="login-toggle" type="button" x-on:click="show = !show" x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" x-bind:aria-pressed="show">
                                <svg x-cloak x-show="!show" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2.5 10S5.4 5.5 10 5.5 17.5 10 17.5 10 14.6 14.5 10 14.5 2.5 10 2.5 10Z"/>
                                    <circle cx="10" cy="10" r="2.3"/>
                                </svg>
                                <svg x-cloak x-show="show" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 3l14 14"/>
                                    <path d="M7.6 6.2A7.6 7.6 0 0 1 10 5.5c4.6 0 7.5 4.5 7.5 4.5a12.8 12.8 0 0 1-2.4 2.9"/>
                                    <path d="M5.1 7.6A12.9 12.9 0 0 0 2.5 10S5.4 14.5 10 14.5c1 0 1.9-.2 2.7-.5"/>
                                    <path d="M8.2 8.3a2.4 2.4 0 0 0 3.4 3.4"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <label class="login-remember">
                            <input type="checkbox" name="remember">
                            <span class="login-checkbox" aria-hidden="true">
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2.5 6.2 2.2 2.2 4.8-5"/></svg>
                            </span>
                            <span>Ingat saya</span>
                        </label>
                        <span class="login-secure-note">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5 16 5v4.6c0 3.5-2.3 6.1-6 7.4-3.7-1.3-6-3.9-6-7.4V5l6-2.5Z"/></svg>
                            Koneksi aman
                        </span>
                    </div>

                    <button class="btn primary full login-submit" type="submit" x-bind:aria-busy="loading">
                        <span class="login-submit-label" x-text="loading ? 'Memproses…' : 'Masuk ke SIM-LJI'">Masuk ke SIM-LJI</span>
                        <span class="login-spinner" x-cloak x-show="loading" aria-hidden="true"></span>
                        <svg class="login-submit-arrow" x-cloak x-show="!loading" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 10h11m-4.5-4.5L15 10l-4.5 4.5"/>
                        </svg>
                    </button>
                </div>

                <footer class="login-foot">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="8.5" width="12" height="9" rx="2.2"/>
                        <path d="M7 8.5V6a3 3 0 0 1 6 0v2.5"/>
                    </svg>
                    <span>Akses sistem internal perusahaan</span>
                </footer>
            </div>
        </form>
    </main>

    <script>
        (function () {
            var page = document.querySelector('.login-page');
            if (!page || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            var frame = null;

            page.addEventListener('pointermove', function (event) {
                var x = event.clientX;
                var y = event.clientY;
                if (frame) return;
                frame = window.requestAnimationFrame(function () {
                    page.style.setProperty('--pointer-x', x + 'px');
                    page.style.setProperty('--pointer-y', y + 'px');
                    page.style.setProperty('--px', ((x / window.innerWidth) * 2 - 1).toFixed(3));
                    page.style.setProperty('--py', ((y / window.innerHeight) * 2 - 1).toFixed(3));
                    frame = null;
                });
            }, { passive: true });

            page.addEventListener('pointerleave', function () {
                page.style.setProperty('--px', '0');
                page.style.setProperty('--py', '0');
            });
        })();
    </script>
</body>
</html>

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
    <form class="login-card" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="brand-mark">LJ</div>
        <p class="eyebrow">PT LINY JAYA INFORMATIKA</p>
        <h1>Selamat datang kembali</h1>
        <p class="muted">Masuk menggunakan akun perusahaan untuk melanjutkan ke SIM-LJI.</p>

        @if($errors->any())
            <div class="alert error"><span class="alert-symbol">!</span><span>{{ $errors->first() }}</span></div>
        @endif

        <label for="email">Alamat email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@perusahaan.com">

        <label for="password">Kata sandi</label>
        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">

        <label class="remember"><input type="checkbox" name="remember"> Ingat saya</label>
        <button class="btn primary full" type="submit">Masuk ke SIM-LJI <span>→</span></button>
        <small class="muted center">Akses sistem internal perusahaan</small>
    </form>
</body>
</html>

@extends('layouts.app')
@section('title', 'Ubah Kata Sandi')
@section('heading', 'Ubah Kata Sandi')

@section('content')
    <div class="page-intro">
        <div>
            <h1>Ubah Kata Sandi</h1>
            <p>Gunakan kata sandi yang kuat dan tidak dipakai di layanan lain.</p>
        </div>
        <a class="btn secondary" href="{{ route('dashboard') }}">← Kembali ke dashboard</a>
    </div>

    <form class="panel form-panel" method="POST" action="{{ route('profile.password.update') }}">
        @csrf
        @method('PUT')

        <div class="panel-head form-panel-head">
            <div>
                <h2>Keamanan Akun</h2>
                <p>Masukkan kata sandi saat ini untuk mengonfirmasi perubahan.</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="current_password">Kata Sandi Saat Ini <em>*</em></label>
                <input id="current_password" type="password" name="current_password" required
                       autocomplete="current-password">
                @error('current_password')<small class="field-error">{{ $message }}</small>@enderror
            </div>

            <div class="field">
                <label for="password">Kata Sandi Baru <em>*</em></label>
                <input id="password" type="password" name="password" required minlength="12"
                       autocomplete="new-password">
                <small>Minimal 12 karakter dan berbeda dari kata sandi saat ini.</small>
                @error('password')<small class="field-error">{{ $message }}</small>@enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Konfirmasi Kata Sandi Baru <em>*</em></label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="12"
                       autocomplete="new-password">
                @error('password_confirmation')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="form-actions">
            <a class="btn secondary" href="{{ route('dashboard') }}">Batal</a>
            <button class="btn primary" type="submit">Simpan Kata Sandi</button>
        </div>
    </form>
@endsection

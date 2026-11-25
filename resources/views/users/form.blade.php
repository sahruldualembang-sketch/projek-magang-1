@extends('layouts.app')
@section('title', $account ? 'Ubah Pengguna' : 'Tambah Pengguna')
@section('heading', 'Pengguna Sistem')

@section('content')
    <div class="page-intro">
        <div>
            <h1>{{ $account ? 'Ubah Akun Pengguna' : 'Tambah Akun Pengguna' }}</h1>
            <p>Atur identitas login, hak akses, dan keterkaitan karyawan.</p>
        </div>
        <a class="btn secondary" href="{{ route('users.index') }}">← Kembali ke daftar</a>
    </div>

    <form class="panel form-panel" method="POST" action="{{ $account ? route('users.update', $account) : route('users.store') }}">
        @csrf
        @if($account) @method('PUT') @endif

        <div class="panel-head form-panel-head">
            <div><h2>Informasi Akun</h2><p>Pastikan email dan peran sudah sesuai.</p></div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="name">Nama Lengkap <em>*</em></label>
                <input id="name" name="name" required value="{{ old('name', $account->name ?? '') }}" autocomplete="name">
                @error('name')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="email">Alamat Email <em>*</em></label>
                <input id="email" type="email" name="email" required value="{{ old('email', $account->email ?? '') }}" autocomplete="email">
                @error('email')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="password">Kata Sandi @if(!$account)<em>*</em>@endif</label>
                <input id="password" type="password" name="password" {{ $account ? '' : 'required' }} minlength="12" autocomplete="new-password">
                <small>{{ $account ? 'Kosongkan jika tidak ingin mengganti kata sandi.' : 'Gunakan minimal 12 karakter.' }}</small>
                @error('password')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="role">Peran Pengguna <em>*</em></label>
                <select id="role" name="role" required>
                    @foreach(['super_admin'=>'Super Admin','director'=>'Direktur/Manajemen','hr'=>'HRD','finance'=>'Keuangan','operations'=>'Operasional','staff'=>'Karyawan'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('role', $account->role ?? 'staff') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="employee_id">Hubungkan ke Data Karyawan</label>
                <select id="employee_id" name="employee_id">
                    <option value="">Tidak ditautkan</option>
                    @foreach($employees as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('employee_id', $account->employee_id ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('employee_id')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="is_active">Status Akun</label>
                <select id="is_active" name="is_active">
                    <option value="1" @selected((string) old('is_active', $account->is_active ?? 1) === '1')>Aktif</option>
                    <option value="0" @selected((string) old('is_active', $account->is_active ?? 1) === '0')>Nonaktif</option>
                </select>
                @error('is_active')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="form-actions">
            <a class="btn secondary" href="{{ route('users.index') }}">Batal</a>
            <button class="btn primary" type="submit">{{ $account ? 'Simpan Perubahan' : 'Buat Akun Pengguna' }}</button>
        </div>
    </form>
@endsection

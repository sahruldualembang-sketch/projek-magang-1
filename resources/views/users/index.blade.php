@extends('layouts.app')
@section('title', 'Pengguna Sistem')
@section('heading', 'Pengguna Sistem')

@section('content')
    <div class="page-intro">
        <div>
            <h1>Pengguna Sistem</h1>
            <p>Kelola akun masuk, status, dan peran pengguna SIM-LJI.</p>
        </div>
        <a class="btn primary" href="{{ route('users.create') }}">＋ Tambah Pengguna</a>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Daftar Pengguna</h2>
                <p>{{ $users->total() }} akun ditemukan</p>
            </div>
        </div>

        <form class="toolbar module-filter-form"
              method="GET"
              action="{{ route('users.index') }}">
            <label class="search-field">
                <span>Nama, email, atau karyawan</span>
                <input type="search"
                       name="q"
                       value="{{ $search }}"
                       placeholder="Cari nama, email, atau karyawan...">
                @error('q')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </label>

            <label class="search-field">
                <span>Peran</span>
                <select name="role">
                    <option value="">Semua Peran</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected($roleFilter === $role)>
                            {{ str_replace('_', ' ', $role) }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </label>

            <label class="search-field">
                <span>Status Akun</span>
                <select name="is_active">
                    <option value="">Semua Status</option>
                    <option value="1" @selected($activeFilter === '1')>Aktif</option>
                    <option value="0" @selected($activeFilter === '0')>Nonaktif</option>
                </select>
                @error('is_active')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </label>

            <div class="filter-actions">
                <button class="btn secondary" type="submit">Terapkan Filter</button>
                <a class="btn ghost" href="{{ route('users.index') }}">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Nama Pengguna</th><th>Email</th><th>Peran</th><th>Data Karyawan</th><th>Status Akun</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td class="strong">{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td><span class="role-tag">{{ str_replace('_', ' ', $u->role) }}</span></td>
                            <td>{{ $u->employee?->name ?? '—' }}</td>
                            <td><span class="status {{ $u->is_active ? 'aktif' : 'nonaktif' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td><div class="row-actions"><a href="{{ route('users.edit', $u) }}">Ubah akun</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Belum ada pengguna yang terdaftar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">
            @include('components.pagination', [
                'paginator' => $users
            ])
        </div>
    </section>

    <div class="notice">
        <strong>Pengelolaan keamanan akun</strong>
        <p>Berikan peran sesuai tanggung jawab pengguna. Akun karyawan sebaiknya ditautkan ke data karyawan agar pembatasan data pribadi berjalan sebagaimana mestinya. Gunakan kata sandi minimal 12 karakter.</p>
    </div>
@endsection

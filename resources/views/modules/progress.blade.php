@extends('layouts.app')
@section('title', 'Ubah Progres Aktivitas')
@section('heading', 'Aktivitas Karyawan')

@section('content')
    <div class="page-intro">
        <div>
            <h1>Ubah Progres Aktivitas</h1>
            <p>Perbarui capaian aktivitas. Hanya progres dan status yang dapat diubah di halaman ini.</p>
        </div>
        <a class="btn secondary" href="{{ route('modules.index', 'activities') }}">← Kembali ke daftar</a>
    </div>

    <form class="panel form-panel" method="POST" action="{{ route('activities.progress.update', $row->id) }}">
        @csrf
        @method('PUT')

        <div class="panel-head form-panel-head">
            <div>
                <h2>{{ $row->title }}</h2>
                <p>Tanggal aktivitas: {{ \Carbon\Carbon::parse($row->activity_date)->format('d/m/Y') }}</p>
            </div>
        </div>

        @if($row->description)
            <p class="form-hint">{{ $row->description }}</p>
        @endif

        <div class="form-grid">
            <div class="field">
                <label for="progress">Progres (%) <em>*</em></label>
                <input id="progress" type="number" name="progress" min="0" max="100" step="5"
                       value="{{ old('progress', (int) $row->progress) }}" required>
                @error('progress')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="field">
                <label for="status">Status <em>*</em></label>
                <select id="status" name="status" required>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option }}" @selected(old('status', $row->status) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('status')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </div>
        </div>

        <p class="form-hint">
            Jika progres sudah mencapai <strong>100%</strong>, ubah status menjadi <strong>Selesai</strong>.
            Setiap perubahan dicatat di riwayat audit.
        </p>

        <div class="form-actions">
            <a class="btn secondary" href="{{ route('modules.index', 'activities') }}">Batal</a>
            <button class="btn primary" type="submit">Simpan Progres</button>
        </div>
    </form>
@endsection

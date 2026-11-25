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
            Progres dan status dijaga tetap konsisten: <strong>0%</strong> berarti <strong>Belum Dimulai</strong>,
            <strong>1–99%</strong> berarti <strong>Proses</strong> atau <strong>Tertunda</strong>, dan
            <strong>100%</strong> otomatis menjadi <strong>Selesai</strong>. Memilih status Selesai otomatis
            mengisi progres 100%, memilih Belum Dimulai mengembalikan progres ke 0%. Setiap perubahan dicatat di riwayat audit.
        </p>

        <div class="form-actions">
            <a class="btn secondary" href="{{ route('modules.index', 'activities') }}">Batal</a>
            <button class="btn primary" type="submit">Simpan Progres</button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const progress = document.getElementById('progress');
            const status = document.getElementById('status');
            if (!progress || !status) return;

            progress.addEventListener('input', function () {
                const value = Number(this.value);
                if (value >= 100) status.value = 'Selesai';
                else if (value === 0) status.value = 'Belum Dimulai';
                else if (status.value === 'Selesai' || status.value === 'Belum Dimulai') status.value = 'Proses';
            });

            status.addEventListener('change', function () {
                if (this.value === 'Selesai') progress.value = 100;
                if (this.value === 'Belum Dimulai') progress.value = 0;
            });
        });
    </script>
@endsection

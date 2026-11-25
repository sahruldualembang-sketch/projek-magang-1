@extends('layouts.app')
@section('title', 'Pengaturan Absensi')
@section('heading', 'Pengaturan Absensi')

@section('content')
    <div class="page-intro">
        <div>
            <h1>Pengaturan Jam Kerja</h1>
            <p>Perubahan berlaku untuk pencatatan dan penetapan Alpha berikutnya.</p>
        </div>
    </div>

    <section class="panel attendance-settings-panel">
        <form method="POST" action="{{ route('settings.attendance.update') }}">
            @csrf
            @method('PUT')
            <div class="settings-grid">
                <label class="field">
                    <span>Jam mulai kerja</span>
                    <input type="time" name="start_time" value="{{ old('start_time', substr($settings->start_time, 0, 5)) }}" required>
                    @error('start_time')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Jam selesai hari biasa (Senin–Jumat)</span>
                    <input type="time" name="weekday_end_time" value="{{ old('weekday_end_time', substr($settings->weekday_end_time, 0, 5)) }}" required>
                    @error('weekday_end_time')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Jam selesai Sabtu</span>
                    <input type="time" name="saturday_end_time" value="{{ old('saturday_end_time', substr($settings->saturday_end_time, 0, 5)) }}" required>
                    @error('saturday_end_time')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Toleransi keterlambatan (menit)</span>
                    <input type="number" name="late_grace_minutes" min="0" max="240"
                           value="{{ old('late_grace_minutes', $settings->late_grace_minutes) }}" required>
                    @error('late_grace_minutes')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>

            <fieldset class="workdays-fieldset">
                <legend>Hari kerja terjadwal</legend>
                @php
                    $oldDays = old('workdays', $workdays);
                    $selectedDays = array_filter(
                        array_map('intval', is_array($oldDays) ? $oldDays : $workdays),
                        fn (int $day): bool => $day >= 1 && $day <= 6
                    );
                @endphp
                <div class="workdays-grid">
                    @foreach([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'] as $day => $label)
                        <label class="workday-option">
                            <input type="checkbox" name="workdays[]" value="{{ $day }}" @checked(in_array($day, $selectedDays, true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                    <label class="workday-option workday-holiday">
                        <input type="checkbox" disabled>
                        <span>Minggu (Libur)</span>
                    </label>
                </div>
                @error('workdays')<small class="field-error">{{ $message }}</small>@enderror
                @error('workdays.*')<small class="field-error">{{ $message }}</small>@enderror
            </fieldset>

            <label class="workday-option alpha-option">
                <input type="hidden" name="automatic_alpha" value="0">
                <input type="checkbox" name="automatic_alpha" value="1"
                       @checked((bool) old('automatic_alpha', $settings->automatic_alpha))>
                <span>Tetapkan Alpha otomatis setelah jam kerja berakhir jika belum ada absensi</span>
            </label>

            <div class="settings-policy-note">
                Status Terlambat mulai dihitung setelah {{ (int) $settings->late_grace_minutes }} menit dari jam mulai.
                Alpha hanya ditetapkan pada hari kerja yang dipilih, setelah jam selesai, bila absensi belum ada.
                Tidak ada tanggal hari libur khusus. Laravel Scheduler harus berjalan agar penetapan Alpha otomatis diproses.
            </div>

            <div class="filter-actions">
                <button class="btn primary" type="submit">Simpan Pengaturan</button>
            </div>
        </form>
    </section>
@endsection

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
                Hari libur nasional yang terdaftar di bawah akan secara otomatis dilewati dari penetapan Alpha.
            </div>

            <div class="filter-actions">
                <button class="btn primary" type="submit">Simpan Pengaturan Jam Kerja</button>
            </div>
        </form>
    </section>

    <section class="panel attendance-holidays-panel" style="margin-top: 24px;">
        <div class="panel-head">
            <div>
                <h2>Hari Libur Nasional & Cuti Bersama Perusahaan</h2>
                <p>Tanggal-tanggal di bawah ini akan dikecualikan dari penetapan Alpha otomatis oleh scheduler.</p>
            </div>
        </div>

        <form class="toolbar" method="POST" action="{{ route('settings.attendance.holidays.store') }}" style="margin-bottom: 20px;">
            @csrf
            <label class="search-field">
                <span>Tanggal Libur</span>
                <input type="date" name="holiday_date" required>
                @error('holiday_date')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="search-field" style="flex: 2;">
                <span>Keterangan Libur</span>
                <input type="text" name="description" placeholder="Contoh: Tahun Baru, Idul Fitri, Hari Kemerdekaan" required maxlength="150">
                @error('description')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <button class="btn primary" type="submit">＋ Tambah Hari Libur</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 140px;">Tanggal</th>
                        <th>Keterangan</th>
                        <th style="width: 100px; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($holidays as $holiday)
                        <tr>
                            <td class="strong">{{ \Illuminate\Support\Carbon::parse($holiday->holiday_date)->format('d/m/Y') }}</td>
                            <td>{{ $holiday->description }}</td>
                            <td style="text-align: right;">
                                <form method="POST" action="{{ route('settings.attendance.holidays.destroy', $holiday->id) }}" onsubmit="return confirm('Hapus hari libur ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="link-danger" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="empty">Belum ada hari libur khusus yang didaftarkan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

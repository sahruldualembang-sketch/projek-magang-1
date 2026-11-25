@extends('layouts.app')
@section('title', 'Koreksi Absensi')
@section('heading', 'Koreksi Absensi')

@section('content')
    <div class="page-intro">
        <div>
            <h1>Koreksi Data Absensi</h1>
            <p>Anda sedang mengedit satu catatan absensi. Perubahan dan alasannya akan disimpan di riwayat audit.</p>
        </div>
        <span class="role-tag">Mode Koreksi</span>
    </div>

    <section class="panel attendance-correction-page">
        <div class="panel-head">
            <div>
                <h2>Informasi Karyawan</h2>
                <p>Nilai saat ini sebagai referensi sebelum koreksi.</p>
            </div>
        </div>
        <dl class="attendance-reference-grid">
            <div><dt>Nama karyawan</dt><dd>{{ $attendance->employee_name }}</dd></div>
            <div><dt>Kode karyawan</dt><dd>{{ $attendance->employee_code }}</dd></div>
            <div><dt>Tanggal absensi</dt><dd>{{ \Illuminate\Support\Carbon::parse($attendance->attendance_date)->format('d/m/Y') }}</dd></div>
            <div><dt>Jam masuk sebelumnya</dt><dd>{{ $attendance->check_in ? substr($attendance->check_in, 0, 5) : '—' }}</dd></div>
            <div><dt>Jam pulang sebelumnya</dt><dd>{{ $attendance->check_out ? substr($attendance->check_out, 0, 5) : '—' }}</dd></div>
            <div><dt>Status sebelumnya</dt><dd><span class="status {{ \Illuminate\Support\Str::slug($attendance->status) }}">{{ $attendance->status }}</span></dd></div>
            <div class="attendance-reference-notes"><dt>Keterangan sebelumnya</dt><dd>{{ $attendance->notes ?: '—' }}</dd></div>
        </dl>
    </section>

    <section class="panel attendance-correction-page">
        <div class="panel-head">
            <div>
                <h2>Formulir Koreksi</h2>
                <p>Alasan koreksi wajib diisi untuk menjaga jejak perubahan.</p>
            </div>
        </div>
        <form class="attendance-correction-form" method="POST"
              action="{{ route('attendance.correction.update', ['id' => $attendance->id] + request()->query()) }}">
            @csrf
            @method('PUT')

            <label class="field">
                <span>Jam Masuk</span>
                <input type="time" name="check_in"
                       value="{{ old('check_in', $attendance->check_in ? substr($attendance->check_in, 0, 5) : '') }}">
                @error('check_in')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>Jam Pulang</span>
                <input type="time" name="check_out"
                       value="{{ old('check_out', $attendance->check_out ? substr($attendance->check_out, 0, 5) : '') }}">
                @error('check_out')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>Status Kehadiran</span>
                <select name="status" required>
                    @foreach(['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpha'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $attendance->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field attendance-correction-wide">
                <span>Keterangan</span>
                <textarea name="notes" rows="3" maxlength="2000">{{ old('notes', $attendance->notes) }}</textarea>
                @error('notes')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field attendance-correction-wide">
                <span>Alasan Koreksi <i class="required-mark">*</i></span>
                <textarea name="reason" rows="3" maxlength="2000" required>{{ old('reason') }}</textarea>
                @error('reason')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <div class="attendance-correction-actions">
                <a class="btn ghost" href="{{ $backUrl }}">Kembali</a>
                <button class="btn primary" type="submit">Simpan Koreksi</button>
            </div>
        </form>
    </section>
@endsection

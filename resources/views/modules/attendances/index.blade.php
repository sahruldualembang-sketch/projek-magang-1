@extends('layouts.app')
@section('title', $isStaff ? 'Rekap Absensi Saya' : 'Rekap Data Absensi')
@section('heading', $isStaff ? 'Rekap Absensi Saya' : 'Rekap Data Absensi')

@section('content')
    @if($isStaff)
        @php
            $isScheduledWorkday = in_array($today->dayOfWeekIso, array_map('intval', json_decode($settings->workdays, true) ?? []), true);
            $scheduledEnd = $today->dayOfWeekIso === 6 ? $settings->saturday_end_time : $settings->weekday_end_time;
        @endphp

        <div class="page-intro">
            <div>
                <h1>Rekap Absensi Saya</h1>
                <p>Karyawan: <strong>{{ auth()->user()->employee?->name }}</strong> · Pantau catatan kehadiran real-time dan riwayat pengajuan izin/sakit Anda.</p>
            </div>
            <div class="page-intro-meta">
                <span class="attendance-source-tag">
                    <span class="dot"></span> Real-Time Sync
                </span>
            </div>
        </div>

        @unless($isPrint)
            <section class="panel attendance-self-panel">
                <div class="attendance-clock">
                    <span class="eyebrow">WAKTU SEKARANG</span>
                    <strong>{{ $today->translatedFormat('l, d F Y') }}</strong>
                    <time data-live-clock>{{ $today->format('H:i:s') }}</time>
                    <span>
                        @if($isScheduledWorkday)
                            Jadwal {{ substr($settings->start_time, 0, 5) }}–{{ substr($scheduledEnd, 0, 5) }}
                        @elseif($today->dayOfWeekIso === 7)
                            Hari libur (Minggu)
                        @else
                            Bukan hari kerja terjadwal
                        @endif
                    </span>
                </div>

                <div class="attendance-today">
                    <div>
                        <span class="attendance-label">Status absensi hari ini</span>
                        @if(!$todayAttendance)
                            <strong class="status belum-absen">Belum ada rekaman</strong>
                        @else
                            <strong class="status {{ \Illuminate\Support\Str::slug($todayAttendance->status) }}">
                                {{ $todayAttendance->status }}
                            </strong>
                        @endif
                    </div>
                    <div class="attendance-time">
                        <span>Jam masuk</span>
                        <strong>{{ $todayAttendance && $todayAttendance->check_in ? substr($todayAttendance->check_in, 0, 5) : '—' }}</strong>
                    </div>
                    <div class="attendance-time">
                        <span>Jam pulang</span>
                        <strong>{{ $todayAttendance && $todayAttendance->check_out ? substr($todayAttendance->check_out, 0, 5) : '—' }}</strong>
                    </div>
                    <div class="attendance-action">
                        <span class="attendance-source-tag">
                            <span class="dot"></span> Real-Time Sync
                        </span>
                        <span class="attendance-source-desc">Tercatat otomatis via aplikasi presensi kantor</span>
                    </div>
                </div>

                @if($todayAttendance && $todayAttendance->status === 'Terlambat')
                    <p class="attendance-late">Anda tercatat terlambat berdasarkan jam kerja yang ditetapkan.</p>
                @endif
            </section>

            <div class="panel" style="margin-bottom: 20px; border-left: 4px solid #3b82f6; background: #f8fafc; padding: 14px 18px; border-radius: 8px;">
                <strong style="color: #1e40af; font-size: 12px;">ℹ Integrasi Presensi & Perizinan Aplikasi</strong>
                <p style="margin: 4px 0 0; font-size: 11px; color: #475569;">Presensi harian, izin, dan sakit dicatat langsung melalui aplikasi presensi perusahaan. Halaman ini menyajikan rekapitulasi data kehadiran resmi Anda.</p>
            </div>

            <section class="panel attendance-requests-panel">
                <div class="panel-head">
                    <div>
                        <h2>Riwayat Pengajuan</h2>
                        <p>Status persetujuan izin atau sakit yang pernah diajukan.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Alasan</th><th>Status</th><th>Catatan HR</th></tr></thead>
                        <tbody>
                            @forelse($myLeaveRequests as $leaveRequest)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($leaveRequest->attendance_date)->format('d/m/Y') }}</td>
                                    <td>{{ $leaveRequest->request_type }}</td>
                                    <td>{{ $leaveRequest->reason }}</td>
                                    <td>
                                        @php
                                            $requestStatus = ['Pending' => 'Menunggu', 'Approved' => 'Disetujui', 'Rejected' => 'Ditolak'][$leaveRequest->status] ?? $leaveRequest->status;
                                        @endphp
                                        <span class="status {{ \Illuminate\Support\Str::slug($leaveRequest->status) }}">{{ $requestStatus }}</span>
                                    </td>
                                    <td>{{ $leaveRequest->review_note ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">Belum ada pengajuan izin atau sakit.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination">@include('components.pagination', ['paginator' => $myLeaveRequests])</div>
            </section>
        @endunless
    @else
        <div class="page-intro">
            <div>
                <h1>Rekap Data Absensi</h1>
                <p>Pantau data presensi real-time karyawan dan proses pengajuan izin/sakit.</p>
            </div>
            <div class="page-intro-meta">
                <span class="attendance-source-tag">
                    <span class="dot"></span> Real-Time Sync
                </span>
            </div>
        </div>

        @if(auth()->user()->employee && !$isPrint)
            @php
                $isScheduledWorkday = in_array($today->dayOfWeekIso, array_map('intval', json_decode($settings->workdays, true) ?? []), true);
                $scheduledEnd = $today->dayOfWeekIso === 6 ? $settings->saturday_end_time : $settings->weekday_end_time;
            @endphp
            <section class="panel attendance-self-panel" style="margin-bottom: 20px;">
                <div class="attendance-clock">
                    <span class="eyebrow">ABSENSI PRIBADI ANDA</span>
                    <strong>{{ auth()->user()->employee->name }}</strong>
                    <time data-live-clock>{{ $today->format('H:i:s') }}</time>
                    <span>
                        @if($isScheduledWorkday)
                            Jadwal {{ substr($settings->start_time, 0, 5) }}–{{ substr($scheduledEnd, 0, 5) }}
                        @elseif($today->dayOfWeekIso === 7)
                            Hari libur (Minggu)
                        @else
                            Bukan hari kerja terjadwal
                        @endif
                    </span>
                </div>

                <div class="attendance-today">
                    <div>
                        <span class="attendance-label">Status absensi Anda hari ini</span>
                        @if(!$todayAttendance)
                            <strong class="status belum-absen">Belum ada rekaman</strong>
                        @else
                            <strong class="status {{ \Illuminate\Support\Str::slug($todayAttendance->status) }}">
                                {{ $todayAttendance->status }}
                            </strong>
                        @endif
                    </div>
                    <div class="attendance-time">
                        <span>Jam masuk</span>
                        <strong>{{ $todayAttendance && $todayAttendance->check_in ? substr($todayAttendance->check_in, 0, 5) : '—' }}</strong>
                    </div>
                    <div class="attendance-time">
                        <span>Jam pulang</span>
                        <strong>{{ $todayAttendance && $todayAttendance->check_out ? substr($todayAttendance->check_out, 0, 5) : '—' }}</strong>
                    </div>
                    <div class="attendance-action">
                        <span class="attendance-source-tag">
                            <span class="dot"></span> Real-Time Sync
                        </span>
                        <span class="attendance-source-desc">Tercatat otomatis via aplikasi presensi kantor</span>
                    </div>
                </div>

                @if($todayAttendance && $todayAttendance->status === 'Terlambat')
                    <p class="attendance-late">Anda tercatat terlambat berdasarkan jam kerja yang ditetapkan.</p>
                @endif
            </section>
        @endif

        @if($leaveRequests && !$isPrint)
            <section class="panel attendance-requests-panel">
                <div class="panel-head">
                    <div>
                        <h2>Pengajuan Izin dan Sakit</h2>
                        <p>{{ $leaveRequests->total() }} pengajuan menunggu persetujuan</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Karyawan</th><th>Tanggal</th><th>Jenis</th><th>Alasan</th><th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leaveRequests as $leaveRequest)
                                <tr>
                                    <td>{{ $leaveRequest->employee_name }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($leaveRequest->attendance_date)->format('d/m/Y') }}</td>
                                    <td>{{ $leaveRequest->request_type }}</td>
                                    <td>{{ $leaveRequest->reason }}</td>
                                    <td>
                                        <details class="attendance-review">
                                            <summary>Proses</summary>
                                            <form method="POST" action="{{ route('attendance.leave-approve', $leaveRequest->id) }}">
                                                @csrf
                                                <input type="text" name="review_note" placeholder="Catatan HR (opsional)">
                                                <button class="btn primary" type="submit">Setujui</button>
                                            </form>
                                            <form method="POST" action="{{ route('attendance.leave-reject', $leaveRequest->id) }}">
                                                @csrf
                                                <input type="text" name="review_note" placeholder="Alasan penolakan" required>
                                                <button class="btn secondary" type="submit">Tolak</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">Tidak ada pengajuan yang menunggu persetujuan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination">@include('components.pagination', ['paginator' => $leaveRequests])</div>
            </section>
        @endif
    @endif

    @if($isPrint)
        <div class="print-header">
            <h2>PT LINY JAYA INFORMATIKA</h2>
            <p>REKAPITULASI DATA PRESENSI KARYAWAN</p>
            <div class="print-meta">
                <span>Tanggal Cetak: {{ now()->translatedFormat('d F Y H:i') }}</span>
                @if($filters['tanggal_mulai'] || $filters['tanggal_akhir'])
                    <span>Periode: {{ $filters['tanggal_mulai'] ? \Illuminate\Support\Carbon::parse($filters['tanggal_mulai'])->format('d/m/Y') : 'Awal' }} s/d {{ $filters['tanggal_akhir'] ? \Illuminate\Support\Carbon::parse($filters['tanggal_akhir'])->format('d/m/Y') : 'Sekarang' }}</span>
                @endif
                @if($filters['status'])
                    <span>Status: {{ $filters['status'] }}</span>
                @endif
            </div>
        </div>
    @endif

    <section class="panel attendance-history-panel">
        <div class="panel-head">
            <div>
                <h2>{{ $isStaff ? 'Riwayat Absensi Saya' : 'Riwayat Absensi Karyawan' }}</h2>
                <p>{{ $isPrint ? count($rows) : $rows->total() }} data ditemukan</p>
            </div>
            @unless($isPrint)
                <div class="print-actions">
                    <a class="btn secondary"
                       href="{{ route('modules.index', ['module' => 'attendances'] + request()->query() + ['print' => 1]) }}"
                       target="_blank" rel="noopener">
                        🖨️ Cetak / PDF
                    </a>
                    <a class="btn ghost"
                       href="{{ route('modules.index', ['module' => 'attendances'] + request()->query() + ['export' => 'csv']) }}">
                        📥 Unduh CSV / Excel
                    </a>
                </div>
            @endunless
        </div>

        @unless($isPrint)
            <form class="toolbar module-filter-form" method="GET" action="{{ route('modules.index', 'attendances') }}">
                @if(!$isStaff)
                    <label class="search-field">
                        <span>Nama Karyawan</span>
                        <input type="search" name="nama" value="{{ $filters['nama'] }}" placeholder="Cari nama...">
                    </label>
                @endif
                <label class="search-field">
                    <span>Tanggal Mulai</span>
                    <input type="date" name="tanggal_mulai" value="{{ $filters['tanggal_mulai'] }}">
                </label>
                <label class="search-field">
                    <span>Tanggal Akhir</span>
                    <input type="date" name="tanggal_akhir" value="{{ $filters['tanggal_akhir'] }}">
                </label>
                <label class="search-field">
                    <span>Status Kehadiran</span>
                    <select name="status">
                        <option value="">Semua Status</option>
                        @foreach(['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpha'] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="filter-actions">
                    <button class="btn secondary" type="submit">Terapkan Filter</button>
                    @if(!$isStaff && auth()->user()->employee)
                        @if($filters['nama'] === auth()->user()->employee->name)
                            <a class="btn ghost" href="{{ route('modules.index', 'attendances') }}">Tampilkan Semua Karyawan</a>
                        @else
                            <a class="btn ghost" href="{{ route('modules.index', ['module' => 'attendances', 'nama' => auth()->user()->employee->name]) }}">👤 Hanya Absensi Saya</a>
                        @endif
                    @endif
                    <a class="btn ghost" href="{{ route('modules.index', 'attendances') }}">Reset</a>
                </div>
            </form>
        @endunless

        <div class="table-wrap">
            <table class="attendance-table">
                <thead>
                    <tr>
                        @if(!$isStaff)<th>Nama Karyawan</th>@endif
                        <th>Tanggal</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th><th>Keterangan</th>
                        @if($canCorrect && !$isPrint)<th>Koreksi</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            @if(!$isStaff)<td>{{ $row->employee_name }}</td>@endif
                            <td>{{ \Illuminate\Support\Carbon::parse($row->attendance_date)->format('d/m/Y') }}</td>
                            <td>{{ $row->check_in ? substr($row->check_in, 0, 5) : '—' }}</td>
                            <td>{{ $row->check_out ? substr($row->check_out, 0, 5) : '—' }}</td>
                            <td><span class="status {{ \Illuminate\Support\Str::slug($row->status) }}">{{ $row->status }}</span></td>
                            <td>{{ $row->notes ?: '—' }}</td>
                            @if($canCorrect && !$isPrint)
                                <td>
                                    <a class="btn secondary attendance-correction-link"
                                       href="{{ route('attendance.correction.edit', ['id' => $row->id] + request()->query()) }}">
                                        Koreksi
                                    </a>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ ($isStaff ? 5 : 6) + ($canCorrect && !$isPrint ? 1 : 0) }}" class="empty">Belum ada data absensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless($isPrint)
            <div class="pagination">@include('components.pagination', ['paginator' => $rows])</div>
        @endunless

        @if($isPrint)
            <div class="print-signatures">
                <div>
                    <p>Disiapkan Oleh,</p>
                    <div class="signature-space"></div>
                    <strong>HRD / Personalia</strong>
                </div>
                <div>
                    <p>Mengetahui,</p>
                    <div class="signature-space"></div>
                    <strong>Pimpinan Perusahaan</strong>
                </div>
            </div>
            <script>window.addEventListener('load', () => window.print());</script>
        @endif
    </section>

    @if($correctionHistory && !$isPrint)
        <section class="panel correction-history-panel">
            <div class="panel-head">
                <div>
                    <h2>Riwayat Koreksi</h2>
                    <p>Perubahan absensi yang dilakukan HR atau Super Admin.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Waktu</th><th>Petugas</th><th>Karyawan</th><th>Tanggal</th><th>Sebelum</th><th>Sesudah</th><th>Alasan</th></tr></thead>
                    <tbody>
                        @forelse($correctionHistory as $entry)
                            @php
                                $before = json_decode($entry->before_data, true) ?: [];
                                $after = json_decode($entry->after_data, true) ?: [];
                            @endphp
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $entry->reviewer_name ?: 'Pengguna dihapus' }}</td>
                                <td>{{ $entry->employee_name ?: '—' }}</td>
                                <td>{{ $entry->attendance_date ? \Illuminate\Support\Carbon::parse($entry->attendance_date)->format('d/m/Y') : '—' }}</td>
                                <td>{{ ($before['status'] ?? '—').' · '.($before['check_in'] ?? '—').' / '.($before['check_out'] ?? '—') }}</td>
                                <td>{{ ($after['status'] ?? '—').' · '.($after['check_in'] ?? '—').' / '.($after['check_out'] ?? '—') }}</td>
                                <td>{{ $after['reason'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty">Belum ada koreksi absensi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination">@include('components.pagination', ['paginator' => $correctionHistory])</div>
        </section>
    @endif

    @unless($isPrint)
        <script>
            (function () {
                const formatter = new Intl.DateTimeFormat('en-GB', {
                    timeZone: @json(config('app.timezone')),
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hourCycle: 'h23'
                });

                // Navigasi SPA memuat ulang skrip; hentikan timer lama agar tidak menumpuk.
                if (window.__attendanceClockTimer) {
                    clearInterval(window.__attendanceClockTimer);
                    window.__attendanceClockTimer = null;
                }

                function updateClock() {
                    const nodes = document.querySelectorAll('[data-live-clock]');
                    if (nodes.length === 0) {
                        clearInterval(window.__attendanceClockTimer);
                        window.__attendanceClockTimer = null;
                        return;
                    }

                    const value = formatter.format(new Date());
                    nodes.forEach(function (node) {
                        node.textContent = value;
                    });
                }

                updateClock();
                window.__attendanceClockTimer = setInterval(updateClock, 1000);
            })();
        </script>
    @endunless
@endsection

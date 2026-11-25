@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    @if(!$isStaff)
        <div class="dashboard-hero">
            <div class="hero-copy">
                <div class="hero-kicker">Ringkasan perusahaan</div>
                <h2>Selamat datang, {{ auth()->user()->name }}</h2>
                <p>Pantau aktivitas dan informasi penting perusahaan dari satu tempat.</p>
            </div>
            <div class="hero-date">
                <small>Hari ini</small>
                <strong>{{ now()->translatedFormat('d F Y') }}</strong>
            </div>
        </div>

        @if(count($summaryCards))
            <div class="stat-grid {{ count($summaryCards) === 1 ? 'one' : (count($summaryCards) === 2 ? 'two' : (count($summaryCards) === 3 ? 'three' : '')) }}">
                @foreach($summaryCards as $card)
                    <article class="stat-card">
                        <div class="stat-card-top">
                            <span class="stat-card-label">{{ $card['label'] }}</span>
                            <span class="stat-icon {{ $card['variant'] }}">{{ $card['icon'] }}</span>
                        </div>
                        <strong>{{ number_format($card['value']) }}</strong>
                        <small>{{ $card['caption'] }}</small>
                    </article>
                @endforeach
            </div>
        @endif

        @if($hasOrders)
            <div class="dashboard-grid">
                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Tren Pesanan</h2>
                            <p>Nilai pesanan enam bulan terakhir</p>
                        </div>
                        <div class="chart-legend"><span><i class="legend-dot"></i>Nilai pesanan</span></div>
                    </div>
                    <div class="panel-body">
                        <div class="chart-total">
                            <small>Nilai pesanan bulan ini</small>
                            <strong>Rp {{ number_format($stats['monthly_order_value'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        @if(collect($monthlyTrend ?? [])->sum('value') > 0)
                            <div class="bar-chart" role="img" aria-label="Grafik nilai pesanan enam bulan terakhir">
                                @foreach($monthlyTrend as $month)
                                    <div class="bar-column">
                                        <div class="bar-track">
                                            <div class="bar-fill" style="height: {{ max(4, (int) round(($month['value'] / max(1, $maxMonthlyValue)) * 100)) }}%"></div>
                                        </div>
                                        <span class="bar-label">{{ $month['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="chart-empty">Belum ada nilai pesanan pada periode ini.</div>
                        @endif
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Perlu Perhatian</h2>
                            <p>Ringkasan item yang perlu ditindaklanjuti</p>
                        </div>
                    </div>
                    <div class="panel-body quick-list">
                        @if(isset($stats['pending_expenses']))
                            <div class="quick-item">
                                <span class="quick-icon">↗</span>
                                <span class="quick-copy"><strong>Pengeluaran menunggu</strong><small>Menunggu pemeriksaan atau persetujuan</small></span>
                                <span class="quick-value">{{ number_format($stats['pending_expenses']) }}</span>
                            </div>
                        @endif
                        @if(isset($stats['pending_invoices']))
                            <div class="quick-item">
                                <span class="quick-icon">▧</span>
                                <span class="quick-copy"><strong>Invoice belum lunas</strong><small>Invoice terkirim atau jatuh tempo</small></span>
                                <span class="quick-value">{{ number_format($stats['pending_invoices']) }}</span>
                            </div>
                        @endif
                        @if(isset($stats['expiring_contracts']))
                            <div class="quick-item">
                                <span class="quick-icon">▣</span>
                                <span class="quick-copy"><strong>Kontrak segera berakhir</strong><small>Dalam 30 hari ke depan</small></span>
                                <span class="quick-value">{{ number_format($stats['expiring_contracts']) }}</span>
                            </div>
                        @endif
                        <div class="quick-item">
                            <span class="quick-icon">▤</span>
                            <span class="quick-copy"><strong>Pesanan bulan ini</strong><small>Pesanan yang dicatat bulan berjalan</small></span>
                            <span class="quick-value">{{ number_format($stats['monthly_orders'] ?? 0) }}</span>
                        </div>
                    </div>
                </section>
            </div>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Pesanan Terbaru</h2>
                        <p>Enam pesanan terakhir yang dicatat dalam sistem</p>
                    </div>
                    <a class="btn secondary" href="{{ route('modules.index', 'orders') }}">Lihat semua <span>→</span></a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>No. Pesanan</th><th>Klien</th><th>Tanggal</th><th>Nilai Pesanan</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td class="strong">{{ $order->order_number }}</td>
                                <td>{{ $order->company_name }}</td>
                                <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</td>
                                <td>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                                <td><span class="status {{ strtolower(str_replace(' ', '-', $order->status)) }}">{{ $order->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">Belum ada data pesanan. Data pesanan akan muncul di sini setelah dicatat.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <div class="notice">
                <strong>Ringkasan sesuai hak akses</strong>
                <p>Dashboard menampilkan informasi dari modul yang tersedia untuk akun Anda. Gunakan menu di samping untuk membuka data yang menjadi kewenangan Anda.</p>
            </div>
        @endif

        @if(auth()->user()->hasRole(['super_admin', 'director', 'finance']))
            <a class="notice notice-link" href="{{ route('profit-loss') }}">
                <strong>Catatan laporan keuangan <span class="notice-link-arrow">→</span></strong>
                <p>Laporan laba/rugi saat ini merupakan ringkasan manajerial berdasarkan pesanan berstatus Selesai serta pengeluaran dan penggajian yang disetujui atau dibayar. Angka ini belum menggantikan laporan akuntansi formal berbasis jurnal berpasangan.</p>
                <small class="notice-link-hint">Klik untuk membuka Laporan Laba/Rugi</small>
            </a>
        @endif
    @else
        <div class="dashboard-hero">
            <div class="hero-copy">
                <div class="hero-kicker">Ruang kerja karyawan</div>
                <h2>Halo, {{ auth()->user()->name }}</h2>
                <p>Berikut ringkasan kehadiran dan aktivitas pekerjaanmu.</p>
            </div>
            <div class="hero-date"><small>Hari ini</small><strong>{{ now()->translatedFormat('d F Y') }}</strong></div>
        </div>
        <div class="stat-grid two">
            <article class="stat-card">
                <div class="stat-card-top"><span class="stat-card-label">Absensi Bulan Ini</span><span class="stat-icon green">◷</span></div>
                <strong>{{ number_format($stats['my_attendance'] ?? 0) }}</strong>
                <small>Catatan kehadiran bulan berjalan</small>
            </article>
            <article class="stat-card">
                <div class="stat-card-top"><span class="stat-card-label">Aktivitas Belum Selesai</span><span class="stat-icon amber">☷</span></div>
                <strong>{{ number_format($stats['my_activities'] ?? 0) }}</strong>
                <small>Aktivitas yang masih perlu diselesaikan</small>
            </article>
        </div>
        <div class="notice"><strong>Informasi</strong><p>Jika data kehadiran atau aktivitas belum sesuai, hubungi HRD atau atasan yang bertanggung jawab.</p></div>
    @endif
@endsection

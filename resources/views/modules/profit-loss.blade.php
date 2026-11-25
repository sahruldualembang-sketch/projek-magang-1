@extends('layouts.app')
@section('title', 'Laporan Laba/Rugi')
@section('heading', 'Laporan Laba/Rugi')

@section('content')
    <div class="page-intro report-heading">
        <div>
            <h1>Laporan Laba/Rugi</h1>
            <p>Laporan manajerial transaksi pendapatan, pengeluaran, dan penggajian.</p>
        </div>
        @unless($isPrint)
            <a class="btn secondary print-button"
               href="{{ route('profit-loss', ['start' => $start, 'end' => $end, 'print' => 1]) }}"
               target="_blank" rel="noopener">Cetak / Simpan PDF</a>
        @endunless
    </div>

    <form class="panel toolbar report-filter" method="GET" action="{{ route('profit-loss') }}">
        <label>
            <span>Tanggal mulai</span>
            <input type="date" name="start" value="{{ $start }}">
        </label>
        <label>
            <span>Tanggal akhir</span>
            <input type="date" name="end" value="{{ $end }}">
        </label>
        <button class="btn primary" type="submit">Tampilkan laporan</button>
    </form>

    <p class="report-period">
        Periode laporan: <strong>{{ \Carbon\Carbon::parse($start)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($end)->format('d/m/Y') }}</strong>
        <span>Periode penggajian: {{ $payrollPeriodStart }} – {{ $payrollPeriodEnd }} (YYYY-MM)</span>
    </p>

    <nav class="report-tabs" aria-label="Bagian laporan">
        <a href="#summary">Ringkasan</a>
        <a href="#income">Pendapatan</a>
        <a href="#expenses">Pengeluaran</a>
        <a href="#payroll">Penggajian</a>
    </nav>

    <section class="report-section" id="summary">
        <div class="report-section-heading">
            <h2>Ringkasan Keuangan</h2>
            <span>Periode {{ \Carbon\Carbon::parse($start)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($end)->format('d/m/Y') }}</span>
        </div>

        <div class="stat-grid report-stat-grid">
            <article class="stat-card">
                <div class="stat-card-top"><span class="stat-card-label">Total Pendapatan</span><span class="stat-icon green">↗</span></div>
                <strong>Rp {{ number_format($revenue, 0, ',', '.') }}</strong>
                <small>Pesanan berstatus Selesai</small>
            </article>
            <article class="stat-card">
                <div class="stat-card-top"><span class="stat-card-label">Pengeluaran Operasional</span><span class="stat-icon red">↘</span></div>
                <strong>Rp {{ number_format($expenses, 0, ',', '.') }}</strong>
                <small>Disetujui atau Dibayar</small>
            </article>
            <article class="stat-card">
                <div class="stat-card-top"><span class="stat-card-label">Biaya Penggajian</span><span class="stat-icon amber">Rp</span></div>
                <strong>Rp {{ number_format($payroll, 0, ',', '.') }}</strong>
                <small>Periode payroll {{ $payrollPeriodStart }} – {{ $payrollPeriodEnd }}</small>
            </article>
            <article class="stat-card">
                <div class="stat-card-top"><span class="stat-card-label">Estimasi Surplus / Defisit</span><span class="stat-icon {{ $surplus >= 0 ? 'green' : 'red' }}">=</span></div>
                <strong class="{{ $surplus >= 0 ? 'positive' : 'negative' }}">Rp {{ number_format($surplus, 0, ',', '.') }}</strong>
                <small>Pendapatan dikurangi beban operasional dan penggajian</small>
            </article>
        </div>

        <section class="panel report-panel">
            <div class="panel-head">
                <div>
                    <h2>Rekapitulasi Beban Operasional</h2>
                    <p>Total pengeluaran Disetujui atau Dibayar per kategori.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Kategori</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse($expenseBreakdown as $category)
                            <tr>
                                <td>{{ $category->category }}</td>
                                <td class="strong">Rp {{ number_format((float) $category->total, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="empty">Tidak ada pengeluaran operasional pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="report-section" id="income">
        <div class="report-section-heading">
            <h2>Rincian Pendapatan</h2>
            <span>Total: Rp {{ number_format($revenue, 0, ',', '.') }}</span>
        </div>
        <section class="panel report-panel">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nomor Pesanan</th>
                            <th>Tanggal Pesanan</th>
                            <th>Nama Klien</th>
                            <th>Deskripsi Pesanan</th>
                            <th>Total Pesanan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incomeRows as $row)
                            <tr>
                                <td class="strong">{{ $row->order_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->order_date)->format('d/m/Y') }}</td>
                                <td>{{ $row->client_name }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($row->description, 90) }}</td>
                                <td class="strong">Rp {{ number_format((float) $row->total_amount, 0, ',', '.') }}</td>
                                <td><span class="status {{ \Illuminate\Support\Str::slug($row->status) }}">{{ $row->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">Tidak ada pesanan selesai pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless($isPrint)
                <div class="pagination">
                    @include('components.pagination', ['paginator' => $incomeRows])
                </div>
            @endunless
        </section>
    </section>

    <section class="report-section" id="expenses">
        <div class="report-section-heading">
            <h2>Rincian Pengeluaran</h2>
            <span>Total operasional: Rp {{ number_format($expenses, 0, ',', '.') }}</span>
        </div>

        @if($potentialPayrollExpenseCount > 0)
            <div class="notice report-warning">
                <strong>Perlu rekonsiliasi biaya penggajian</strong>
                <p>
                    {{ $potentialPayrollExpenseCount }} pengeluaran (Rp {{ number_format($potentialPayrollExpenseTotal, 0, ',', '.') }})
                    memiliki kategori terkait gaji. Pengeluaran ini dipisahkan dari beban operasional agar tidak dihitung dua kali;
                    periksa tabel di bawah dan cocokkan dengan data penggajian.
                </p>
            </div>

            <section class="panel report-panel">
                <div class="panel-head">
                    <div>
                        <h2>Pengeluaran Berkategori Gaji — Belum Dihitung</h2>
                        <p>Data tetap tersimpan; cocokkan secara manual karena tabel expenses tidak memiliki relasi ke payroll.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nomor Pengeluaran</th><th>Tanggal Pengeluaran</th><th>Kategori</th>
                                <th>Keterangan</th><th>Jumlah</th><th>Metode Pembayaran</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($potentialPayrollExpenseRows as $row)
                                <tr>
                                    <td class="strong">{{ $row->expense_number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($row->expense_date)->format('d/m/Y') }}</td>
                                    <td>{{ $row->category }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($row->description, 90) }}</td>
                                    <td class="strong">Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                                    <td>{{ $row->payment_method ?: '—' }}</td>
                                    <td><span class="status {{ \Illuminate\Support\Str::slug($row->status) }}">{{ $row->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty">Tidak ada data pada halaman ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @unless($isPrint)
                    <div class="pagination">
                        @include('components.pagination', ['paginator' => $potentialPayrollExpenseRows])
                    </div>
                @endunless
            </section>
        @endif

        <section class="panel report-panel">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nomor Pengeluaran</th>
                            <th>Tanggal Pengeluaran</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th>Jumlah</th>
                            <th>Metode Pembayaran</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenseRows as $row)
                            <tr>
                                <td class="strong">{{ $row->expense_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->expense_date)->format('d/m/Y') }}</td>
                                <td>{{ $row->category }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($row->description, 90) }}</td>
                                <td class="strong">Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                                <td>{{ $row->payment_method ?: '—' }}</td>
                                <td><span class="status {{ \Illuminate\Support\Str::slug($row->status) }}">{{ $row->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty">Tidak ada pengeluaran operasional pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless($isPrint)
                <div class="pagination">
                    @include('components.pagination', ['paginator' => $expenseRows])
                </div>
            @endunless
        </section>
    </section>

    <section class="report-section" id="payroll">
        <div class="report-section-heading">
            <h2>Rincian Penggajian</h2>
            <span>Total: Rp {{ number_format($payroll, 0, ',', '.') }}</span>
        </div>
        <section class="panel report-panel">
            <div class="panel-head">
                <div>
                    <h2>Periode {{ $payrollPeriodStart }} – {{ $payrollPeriodEnd }}</h2>
                    <p>Seluruh bulan YYYY-MM yang beririsan dengan tanggal laporan.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Periode Penggajian</th><th>Nama Karyawan</th><th>Gaji Bersih</th><th>Status Pembayaran</th></tr>
                    </thead>
                    <tbody>
                        @forelse($payrollRows as $row)
                            <tr>
                                <td class="strong">{{ $row->payroll_period }}</td>
                                <td>{{ $row->employee_name }}</td>
                                <td class="strong">Rp {{ number_format((float) $row->net_salary, 0, ',', '.') }}</td>
                                <td><span class="status {{ \Illuminate\Support\Str::slug($row->status) }}">{{ $row->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Tidak ada penggajian disetujui atau dibayar pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless($isPrint)
                <div class="pagination">
                    @include('components.pagination', ['paginator' => $payrollRows])
                </div>
            @endunless
        </section>
    </section>


    @if($isPrint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
@endsection

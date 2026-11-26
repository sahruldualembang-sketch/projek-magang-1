@extends('layouts.app')
@section('title', 'Slip Gaji · ' . $payroll->employee_name . ' (' . $payroll->payroll_period . ')')
@section('heading', 'Slip Gaji Karyawan')

@section('content')
<div class="page-intro no-print">
    <div>
        <h1>Slip Pembayaran Gaji</h1>
        <p>Dokumen resmi bukti pembayaran gaji karyawan PT Liny Jaya Informatika.</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn primary" type="button" onclick="window.print()">🖶 Cetak / Simpan PDF</button>
        <a class="btn secondary" href="{{ route('modules.index', 'payrolls') }}">← Kembali ke Daftar Gaji</a>
    </div>
</div>

<article class="panel payslip-card" style="max-width: 800px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">
    <header class="payslip-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #202b38; padding-bottom: 20px; margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <img src="{{ asset('assets/logo.webp') }}" alt="Logo PT Liny Jaya Informatika" style="width: 50px; height: 50px; object-fit: contain;">
            <div>
                <h2 style="font-size: 18px; margin: 0; color: #1e293b; font-weight: 800; letter-spacing: 0.5px;">PT LINY JAYA INFORMATIKA</h2>
                <p style="margin: 3px 0 0; font-size: 11px; color: #64748b;">Sistem Informasi Manajemen & Penggajian Karyawan</p>
                <p style="margin: 2px 0 0; font-size: 10px; color: #94a3b8;">Makassar, Sulawesi Selatan · sim.sistemlji.com</p>
            </div>
        </div>
        <div style="text-align: right;">
            <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                SLIP GAJI RESMI
            </span>
            <p style="margin: 6px 0 0; font-size: 13px; font-weight: 700; color: #0f172a;">
                Periode: {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $payroll->payroll_period)->translatedFormat('F Y') }}
            </p>
            <p style="margin: 2px 0 0; font-size: 10px; color: #64748b;">
                Status: <strong style="color: {{ $payroll->status === 'Dibayar' ? '#16a34a' : ($payroll->status === 'Disetujui' ? '#2563eb' : '#d97706') }};">{{ $payroll->status }}</strong>
                @if($payroll->paid_at)
                    · Tgl Bayar: {{ \Illuminate\Support\Carbon::parse($payroll->paid_at)->format('d/m/Y') }}
                @endif
            </p>
        </div>
    </header>

    <section class="payslip-employee" style="margin-bottom: 24px;">
        <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
            <tbody>
                <tr>
                    <td style="padding: 4px 0; color: #64748b; width: 140px;">Nama Karyawan</td>
                    <td style="padding: 4px 0; font-weight: 700; color: #0f172a;">: {{ $payroll->employee_name }}</td>
                    <td style="padding: 4px 0; color: #64748b; width: 140px;">Departemen</td>
                    <td style="padding: 4px 0; font-weight: 600; color: #0f172a;">: {{ $payroll->department ?: '—' }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; color: #64748b;">Kode Karyawan / NIK</td>
                    <td style="padding: 4px 0; font-weight: 600; color: #0f172a;">: {{ $payroll->employee_code }}</td>
                    <td style="padding: 4px 0; color: #64748b;">Jabatan</td>
                    <td style="padding: 4px 0; font-weight: 600; color: #0f172a;">: {{ $payroll->position ?: '—' }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; color: #64748b;">Status Kepegawaian</td>
                    <td style="padding: 4px 0; font-weight: 600; color: #0f172a;">: {{ $payroll->employment_status }}</td>
                    <td style="padding: 4px 0; color: #64748b;">Tanggal Cetak</td>
                    <td style="padding: 4px 0; color: #0f172a;">: {{ now()->translatedFormat('d F Y, H:i') }} WITA</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="payslip-breakdown" style="margin-bottom: 28px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #cbd5e1; text-align: left;">
                    <th style="padding: 10px 14px; color: #334155; font-weight: 700;">KOMPONEN PENGHASILAN</th>
                    <th style="padding: 10px 14px; text-align: right; color: #334155; font-weight: 700; width: 180px;">JUMLAH (RP)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px 14px; color: #1e293b;">Gaji Pokok (Basic Salary)</td>
                    <td style="padding: 10px 14px; text-align: right; font-weight: 600; color: #0f172a;">
                        Rp {{ number_format((float) $payroll->basic_salary, 0, ',', '.') }}
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px 14px; color: #1e293b;">Tunjangan (Allowance)</td>
                    <td style="padding: 10px 14px; text-align: right; font-weight: 600; color: #0f172a;">
                        Rp {{ number_format((float) ($payroll->allowance ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
                <tr style="background: #f1f5f9; font-weight: 700; border-bottom: 2px solid #cbd5e1;">
                    <td style="padding: 10px 14px; color: #0f172a;">Total Penghasilan Kotor</td>
                    <td style="padding: 10px 14px; text-align: right; color: #0f172a;">
                        Rp {{ number_format((float) $payroll->basic_salary + (float) ($payroll->allowance ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
                <tr style="background: #f8fafc; border-bottom: 2px solid #cbd5e1; text-align: left;">
                    <th style="padding: 10px 14px; color: #334155; font-weight: 700;">KOMPONEN POTONGAN</th>
                    <th style="padding: 10px 14px; text-align: right; color: #334155; font-weight: 700;">JUMLAH (RP)</th>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px 14px; color: #1e293b;">Potongan Gaji / Pinjaman / Keterlambatan</td>
                    <td style="padding: 10px 14px; text-align: right; font-weight: 600; color: #b91c1c;">
                        - Rp {{ number_format((float) ($payroll->deduction ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
                <tr style="background: #fef2f2; font-weight: 700; border-bottom: 2px solid #cbd5e1;">
                    <td style="padding: 10px 14px; color: #991b1b;">Total Potongan</td>
                    <td style="padding: 10px 14px; text-align: right; color: #991b1b;">
                        - Rp {{ number_format((float) ($payroll->deduction ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
                <tr style="background: #ecfdf5; font-size: 14px; font-weight: 800; border-top: 2px solid #059669;">
                    <td style="padding: 14px; color: #065f46;">GAJI BERSIH (TAKE HOME PAY)</td>
                    <td style="padding: 14px; text-align: right; color: #047857; font-size: 16px;">
                        Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <footer class="payslip-signatures" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center; font-size: 11px; color: #475569; margin-top: 40px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">
        <div>
            <p style="margin-bottom: 50px;">Disiapkan oleh,<br><strong>Human Resources (HRD)</strong></p>
            <p style="border-top: 1px solid #94a3b8; display: inline-block; padding-top: 4px; min-width: 140px;">Tim HRD</p>
        </div>
        <div>
            <p style="margin-bottom: 50px;">Disetujui oleh,<br><strong>Finance & Accounting</strong></p>
            <p style="border-top: 1px solid #94a3b8; display: inline-block; padding-top: 4px; min-width: 140px;">Tim Keuangan</p>
        </div>
        <div>
            <p style="margin-bottom: 50px;">Diterima oleh,<br><strong>Karyawan Bersangkutan</strong></p>
            <p style="border-top: 1px solid #94a3b8; display: inline-block; padding-top: 4px; min-width: 140px;">{{ $payroll->employee_name }}</p>
        </div>
    </footer>

    <div style="margin-top: 32px; padding: 10px; background: #f8fafc; border-radius: 6px; font-size: 10px; color: #94a3b8; text-align: center;">
        Dokumen ini diterbitkan secara elektronik oleh Sistem Informasi Manajemen PT Liny Jaya Informatika (SIM-LJI) dan sah tanpa tanda tangan basah bila disertai verifikasi data sistem.
    </div>
</article>

<style>
@media print {
    body { background: #fff !important; color: #000 !important; }
    .no-print, header, aside, nav, .sidebar, .page-header { display: none !important; }
    .payslip-card { box-shadow: none !important; border: 1px solid #ccc !important; padding: 20px !important; max-width: 100% !important; margin: 0 !important; }
    main { padding: 0 !important; margin: 0 !important; }
}
</style>
@endsection

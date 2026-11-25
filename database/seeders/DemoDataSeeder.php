<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data demonstrasi SIM-LJI.
 *
 * Seeder ini hanya berjalan pada local/testing/staging dan hanya melakukan
 * upsert pada record yang memiliki kode/email demo khusus. Tidak menghapus
 * atau mereset data lain yang sudah ada.
 */
class DemoDataSeeder extends Seeder
{
    private string $demoPassword = 'DemoSIMLJI#2026';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->command?->warn('DemoDataSeeder dilewati: environment bukan local/testing/staging.');

            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $today = $now->copy()->startOfDay();

            // 1. Karyawan contoh.
            $employeeRows = [
                ['employee_code' => 'DEMO-KRY-001', 'name' => 'Siti Rahmawati', 'email' => 'siti.rahmawati@demo.sim-lji.local', 'phone' => '000000000101', 'department' => 'HR & Umum', 'position' => 'HR Officer', 'employment_status' => 'Tetap', 'join_date' => '2023-02-13', 'base_salary' => 6500000],
                ['employee_code' => 'DEMO-KRY-002', 'name' => 'Andi Pratama', 'email' => 'andi.pratama@demo.sim-lji.local', 'phone' => '000000000102', 'department' => 'Operasional', 'position' => 'Project Manager', 'employment_status' => 'Tetap', 'join_date' => '2022-08-01', 'base_salary' => 9000000],
                ['employee_code' => 'DEMO-KRY-003', 'name' => 'Nurul Aisyah', 'email' => 'nurul.aisyah@demo.sim-lji.local', 'phone' => '000000000103', 'department' => 'Keuangan', 'position' => 'Finance Officer', 'employment_status' => 'Tetap', 'join_date' => '2023-06-05', 'base_salary' => 7000000],
                ['employee_code' => 'DEMO-KRY-004', 'name' => 'Fajar Ramadhan', 'email' => 'fajar.ramadhan@demo.sim-lji.local', 'phone' => '000000000104', 'department' => 'Teknologi Informasi', 'position' => 'Web Developer', 'employment_status' => 'Tetap', 'join_date' => '2024-01-15', 'base_salary' => 8000000],
                ['employee_code' => 'DEMO-KRY-005', 'name' => 'Muhammad Rizky', 'email' => 'muhammad.rizky@demo.sim-lji.local', 'phone' => '000000000105', 'department' => 'Operasional', 'position' => 'Field Technician', 'employment_status' => 'Kontrak', 'join_date' => '2024-04-01', 'base_salary' => 6800000],
                ['employee_code' => 'DEMO-KRY-006', 'name' => 'Dewi Kartika', 'email' => 'dewi.kartika@demo.sim-lji.local', 'phone' => '000000000106', 'department' => 'Administrasi', 'position' => 'Admin Officer', 'employment_status' => 'Tetap', 'join_date' => '2023-11-20', 'base_salary' => 5500000],
                ['employee_code' => 'DEMO-KRY-007', 'name' => 'Ahmad Fauzan', 'email' => 'ahmad.fauzan@demo.sim-lji.local', 'phone' => '000000000107', 'department' => 'Pemasaran', 'position' => 'Account Executive', 'employment_status' => 'Kontrak', 'join_date' => '2025-01-06', 'base_salary' => 7000000],
                ['employee_code' => 'DEMO-KRY-008', 'name' => 'Rina Puspita', 'email' => 'rina.puspita@demo.sim-lji.local', 'phone' => '000000000108', 'department' => 'Operasional', 'position' => 'UI Designer', 'employment_status' => 'Magang', 'join_date' => '2026-08-03', 'base_salary' => 3500000],
            ];

            $employees = [];
            foreach ($employeeRows as $row) {
                $employees[$row['employee_code']] = $this->upsert(
                    'employees',
                    ['employee_code' => $row['employee_code']],
                    $row
                );
            }

            // 2. Akun contoh untuk mencoba hak akses tiap role.
            $demoUsers = [
                ['name' => 'Demo Direktur', 'email' => 'direktur@demo.sim-lji.local', 'role' => 'director', 'employee_id' => null],
                ['name' => 'Demo HR', 'email' => 'hr@demo.sim-lji.local', 'role' => 'hr', 'employee_id' => null],
                ['name' => 'Demo Keuangan', 'email' => 'keuangan@demo.sim-lji.local', 'role' => 'finance', 'employee_id' => null],
                ['name' => 'Demo Operasional', 'email' => 'operasional@demo.sim-lji.local', 'role' => 'operations', 'employee_id' => null],
                ['name' => 'Demo Karyawan', 'email' => 'staff@demo.sim-lji.local', 'role' => 'staff', 'employee_id' => $employees['DEMO-KRY-004']],
            ];

            foreach ($demoUsers as $user) {
                $this->upsert('users', ['email' => $user['email']], $user + [
                    'password' => Hash::make($this->demoPassword),
                    'email_verified_at' => $now,
                    'is_active' => true,
                ]);
            }

            // 3. Klien contoh.
            $clientRows = [
                ['client_code' => 'DEMO-KLN-001', 'company_name' => 'PT Cakrawala Digital Nusantara', 'contact_name' => 'Rizal Akbar', 'email' => 'rizal@cakrawala-demo.local', 'phone' => '000000000201', 'address' => 'Panakkukang, Makassar, Sulawesi Selatan', 'status' => 'Aktif'],
                ['client_code' => 'DEMO-KLN-002', 'company_name' => 'CV Sulawesi Karya Mandiri', 'contact_name' => 'Maya Salsabila', 'email' => 'maya@sulawesi-karya.invalid', 'phone' => '000000000202', 'address' => 'Rappocini, Makassar, Sulawesi Selatan', 'status' => 'Aktif'],
                ['client_code' => 'DEMO-KLN-003', 'company_name' => 'PT Bahari Logistik Timur', 'contact_name' => 'Arman Saputra', 'email' => 'arman@bahari-logistik.invalid', 'phone' => '000000000203', 'address' => 'Kota Makassar, Sulawesi Selatan', 'status' => 'Aktif'],
                ['client_code' => 'DEMO-KLN-004', 'company_name' => 'Koperasi Sejahtera Bersama', 'contact_name' => 'Indah Permata', 'email' => 'indah@sejahtera-demo.invalid', 'phone' => '000000000204', 'address' => 'Gowa, Sulawesi Selatan', 'status' => 'Prospek'],
                ['client_code' => 'DEMO-KLN-005', 'company_name' => 'PT Pilar Teknologi Indonesia', 'contact_name' => 'Dimas Kurniawan', 'email' => 'dimas@pilar-teknologi.invalid', 'phone' => '000000000205', 'address' => 'Jakarta Selatan, DKI Jakarta', 'status' => 'Aktif'],
                ['client_code' => 'DEMO-KLN-006', 'company_name' => 'CV Mitra Niaga Sejati', 'contact_name' => 'Fitri Handayani', 'email' => 'fitri@mitra-niaga.invalid', 'phone' => '000000000206', 'address' => 'Maros, Sulawesi Selatan', 'status' => 'Nonaktif'],
            ];

            $clients = [];
            foreach ($clientRows as $row) {
                $clients[$row['client_code']] = $this->upsert(
                    'clients',
                    ['client_code' => $row['client_code']],
                    $row
                );
            }

            // 4. Pesanan selama enam bulan agar grafik dashboard terisi.
            $orderRows = [
                ['order_number' => 'DEMO-ORD-2026-001', 'client' => 'DEMO-KLN-001', 'months_ago' => 5, 'day' => 5, 'description' => 'Pengembangan website profil perusahaan (data demonstrasi).', 'quantity' => 1, 'unit_price' => 18500000, 'status' => 'Selesai'],
                ['order_number' => 'DEMO-ORD-2026-002', 'client' => 'DEMO-KLN-002', 'months_ago' => 4, 'day' => 10, 'description' => 'Pemeliharaan aplikasi internal dan dukungan teknis (data demonstrasi).', 'quantity' => 3, 'unit_price' => 3500000, 'status' => 'Selesai'],
                ['order_number' => 'DEMO-ORD-2026-003', 'client' => 'DEMO-KLN-003', 'months_ago' => 4, 'day' => 21, 'description' => 'Implementasi dashboard pemantauan operasional (data demonstrasi).', 'quantity' => 1, 'unit_price' => 27500000, 'status' => 'Selesai'],
                ['order_number' => 'DEMO-ORD-2026-004', 'client' => 'DEMO-KLN-005', 'months_ago' => 3, 'day' => 8, 'description' => 'Pengadaan dan konfigurasi perangkat jaringan (data demonstrasi).', 'quantity' => 5, 'unit_price' => 4200000, 'status' => 'Selesai'],
                ['order_number' => 'DEMO-ORD-2026-005', 'client' => 'DEMO-KLN-001', 'months_ago' => 2, 'day' => 12, 'description' => 'Pembuatan modul laporan dan ekspor data (data demonstrasi).', 'quantity' => 2, 'unit_price' => 7800000, 'status' => 'Selesai'],
                ['order_number' => 'DEMO-ORD-2026-006', 'client' => 'DEMO-KLN-004', 'months_ago' => 2, 'day' => 20, 'description' => 'Analisis kebutuhan sistem informasi koperasi (data demonstrasi).', 'quantity' => 1, 'unit_price' => 9500000, 'status' => 'Penawaran'],
                ['order_number' => 'DEMO-ORD-2026-007', 'client' => 'DEMO-KLN-002', 'months_ago' => 1, 'day' => 7, 'description' => 'Pembaruan antarmuka aplikasi layanan pelanggan (data demonstrasi).', 'quantity' => 1, 'unit_price' => 16500000, 'status' => 'Diproses'],
                ['order_number' => 'DEMO-ORD-2026-008', 'client' => 'DEMO-KLN-005', 'months_ago' => 1, 'day' => 18, 'description' => 'Integrasi API dan sinkronisasi data (data demonstrasi).', 'quantity' => 2, 'unit_price' => 6200000, 'status' => 'Disetujui'],
                ['order_number' => 'DEMO-ORD-2026-009', 'client' => 'DEMO-KLN-003', 'months_ago' => 0, 'day' => 4, 'description' => 'Layanan dukungan infrastruktur TI bulanan (data demonstrasi).', 'quantity' => 1, 'unit_price' => 12500000, 'status' => 'Selesai'],
                ['order_number' => 'DEMO-ORD-2026-010', 'client' => 'DEMO-KLN-001', 'months_ago' => 0, 'day' => 11, 'description' => 'Pengembangan fitur manajemen dokumen (data demonstrasi).', 'quantity' => 1, 'unit_price' => 22000000, 'status' => 'Menunggu'],
            ];

            $orders = [];
            $orderDates = [];
            foreach ($orderRows as $row) {
                $orderDate = $this->monthDate((int) $row['months_ago'], (int) $row['day']);
                $quantity = (float) $row['quantity'];
                $unitPrice = (float) $row['unit_price'];
                $orders[$row['order_number']] = $this->upsert(
                    'orders',
                    ['order_number' => $row['order_number']],
                    [
                        'client_id' => $clients[$row['client']],
                        'order_date' => $orderDate,
                        'description' => $row['description'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_amount' => round($quantity * $unitPrice, 2),
                        'status' => $row['status'],
                    ]
                );
                $orderDates[$row['order_number']] = $orderDate;
            }

            // 5. Kontrak, termasuk kontrak yang akan segera berakhir untuk kartu dashboard.
            $contractRows = [
                ['contract_number' => 'DEMO-KTR-001/2026', 'client' => 'DEMO-KLN-001', 'title' => 'Kontrak Pemeliharaan Website', 'start_offset' => -180, 'end_offset' => 15, 'contract_value' => 24000000, 'status' => 'Aktif', 'notes' => 'Contoh kontrak demonstrasi; berakhir dalam sekitar dua minggu.'],
                ['contract_number' => 'DEMO-KTR-002/2026', 'client' => 'DEMO-KLN-002', 'title' => 'Kontrak Dukungan Aplikasi', 'start_offset' => -120, 'end_offset' => 24, 'contract_value' => 18000000, 'status' => 'Aktif', 'notes' => 'Contoh kontrak demonstrasi; perlu ditinjau sebelum perpanjangan.'],
                ['contract_number' => 'DEMO-KTR-003/2026', 'client' => 'DEMO-KLN-003', 'title' => 'Kontrak Implementasi Sistem', 'start_offset' => -60, 'end_offset' => 305, 'contract_value' => 67500000, 'status' => 'Aktif', 'notes' => 'Kontrak aktif contoh.'],
                ['contract_number' => 'DEMO-KTR-004/2026', 'client' => 'DEMO-KLN-005', 'title' => 'Perjanjian Pengadaan Perangkat', 'start_offset' => -30, 'end_offset' => null, 'contract_value' => 21000000, 'status' => 'Draft', 'notes' => 'Draft contoh yang menunggu persetujuan.'],
                ['contract_number' => 'DEMO-KTR-005/2025', 'client' => 'DEMO-KLN-006', 'title' => 'Kontrak Layanan Lama', 'start_offset' => -420, 'end_offset' => -35, 'contract_value' => 12000000, 'status' => 'Berakhir', 'notes' => 'Contoh kontrak yang sudah berakhir.'],
            ];

            foreach ($contractRows as $row) {
                $this->upsert('contracts', ['contract_number' => $row['contract_number']], [
                    'client_id' => $clients[$row['client']],
                    'title' => $row['title'],
                    'start_date' => $today->copy()->addDays($row['start_offset'])->toDateString(),
                    'end_date' => $row['end_offset'] === null ? null : $today->copy()->addDays($row['end_offset'])->toDateString(),
                    'contract_value' => $row['contract_value'],
                    'status' => $row['status'],
                    'notes' => $row['notes'],
                    'file_path' => null,
                    'file_name' => null,
                    'file_mime' => null,
                    'file_size' => null,
                ]);
            }

            // 6. Inventaris dan legalitas.
            $assetRows = [
                ['asset_code' => 'DEMO-AST-001', 'name' => 'Laptop Operasional 01', 'category' => 'Perangkat IT', 'acquisition_date' => '2024-02-12', 'acquisition_cost' => 12500000, 'asset_condition' => 'Baik', 'assigned_to' => 'Fajar Ramadhan', 'status' => 'Dipakai'],
                ['asset_code' => 'DEMO-AST-002', 'name' => 'Laptop Operasional 02', 'category' => 'Perangkat IT', 'acquisition_date' => '2024-04-18', 'acquisition_cost' => 11800000, 'asset_condition' => 'Baik', 'assigned_to' => 'Rina Puspita', 'status' => 'Dipakai'],
                ['asset_code' => 'DEMO-AST-003', 'name' => 'Printer Kantor', 'category' => 'Peralatan Kantor', 'acquisition_date' => '2023-08-10', 'acquisition_cost' => 3200000, 'asset_condition' => 'Perlu Perbaikan', 'assigned_to' => 'Dewi Kartika', 'status' => 'Dipakai'],
                ['asset_code' => 'DEMO-AST-004', 'name' => 'Router Utama', 'category' => 'Jaringan', 'acquisition_date' => '2025-01-20', 'acquisition_cost' => 4500000, 'asset_condition' => 'Baik', 'assigned_to' => 'Muhammad Rizky', 'status' => 'Dipakai'],
                ['asset_code' => 'DEMO-AST-005', 'name' => 'Proyektor Ruang Rapat', 'category' => 'Peralatan Kantor', 'acquisition_date' => '2023-10-04', 'acquisition_cost' => 6800000, 'asset_condition' => 'Baik', 'assigned_to' => null, 'status' => 'Tersedia'],
                ['asset_code' => 'DEMO-AST-006', 'name' => 'Switch Jaringan 24 Port', 'category' => 'Jaringan', 'acquisition_date' => '2024-07-15', 'acquisition_cost' => 5600000, 'asset_condition' => 'Baik', 'assigned_to' => 'Muhammad Rizky', 'status' => 'Dipakai'],
                ['asset_code' => 'DEMO-AST-007', 'name' => 'Kursi Kerja Ergonomis', 'category' => 'Furnitur', 'acquisition_date' => '2022-11-01', 'acquisition_cost' => 1750000, 'asset_condition' => 'Rusak', 'assigned_to' => null, 'status' => 'Dihapuskan'],
            ];
            foreach ($assetRows as $row) {
                $this->upsert('assets', ['asset_code' => $row['asset_code']], $row);
            }

            $legalRows = [
                ['document_name' => 'DEMO - Nomor Induk Berusaha', 'document_number' => 'DEMO-NIB-2026-001', 'issuer' => 'OSS (contoh)', 'issued_at' => $today->copy()->subYears(2)->toDateString(), 'expires_at' => $today->copy()->addYears(2)->toDateString(), 'status' => 'Berlaku', 'notes' => 'Data demonstrasi, bukan dokumen legal perusahaan.'],
                ['document_name' => 'DEMO - Sertifikat Standar', 'document_number' => 'DEMO-STD-2026-002', 'issuer' => 'Instansi penerbit (contoh)', 'issued_at' => $today->copy()->subYear()->toDateString(), 'expires_at' => $today->copy()->addDays(20)->toDateString(), 'status' => 'Segera Berakhir', 'notes' => 'Data demonstrasi untuk contoh pengingat masa berlaku.'],
                ['document_name' => 'DEMO - Perjanjian Sewa Kantor', 'document_number' => 'DEMO-SEWA-2025-003', 'issuer' => 'Pihak penyedia (contoh)', 'issued_at' => $today->copy()->subYears(2)->toDateString(), 'expires_at' => $today->copy()->subDays(30)->toDateString(), 'status' => 'Kedaluwarsa', 'notes' => 'Data demonstrasi untuk contoh status kedaluwarsa.'],
                ['document_name' => 'DEMO - Dokumen Kebijakan Internal', 'document_number' => 'DEMO-POL-2026-004', 'issuer' => 'Manajemen (contoh)', 'issued_at' => $today->copy()->subMonths(4)->toDateString(), 'expires_at' => null, 'status' => 'Berlaku', 'notes' => 'Contoh dokumen internal tanpa tanggal kedaluwarsa.'],
            ];
            foreach ($legalRows as $row) {
                $this->upsert('legal_documents', ['document_number' => $row['document_number']], $row + [
                    'file_path' => null,
                    'file_name' => null,
                    'file_mime' => null,
                    'file_size' => null,
                ]);
            }

            // 7. Invoice yang terhubung dengan klien dan pesanan contoh.
            $invoiceRows = [
                ['invoice_number' => 'DEMO-INV-001/2026', 'order' => 'DEMO-ORD-2026-001', 'client' => 'DEMO-KLN-001', 'months_ago' => 5, 'subtotal' => 18500000, 'tax' => 2035000, 'status' => 'Terbayar'],
                ['invoice_number' => 'DEMO-INV-002/2026', 'order' => 'DEMO-ORD-2026-002', 'client' => 'DEMO-KLN-002', 'months_ago' => 4, 'subtotal' => 10500000, 'tax' => 1155000, 'status' => 'Terbayar'],
                ['invoice_number' => 'DEMO-INV-003/2026', 'order' => 'DEMO-ORD-2026-003', 'client' => 'DEMO-KLN-003', 'months_ago' => 4, 'subtotal' => 27500000, 'tax' => 3025000, 'status' => 'Terbayar'],
                ['invoice_number' => 'DEMO-INV-004/2026', 'order' => 'DEMO-ORD-2026-004', 'client' => 'DEMO-KLN-005', 'months_ago' => 3, 'subtotal' => 21000000, 'tax' => 2310000, 'status' => 'Terbayar'],
                ['invoice_number' => 'DEMO-INV-005/2026', 'order' => 'DEMO-ORD-2026-005', 'client' => 'DEMO-KLN-001', 'months_ago' => 2, 'subtotal' => 15600000, 'tax' => 1716000, 'status' => 'Jatuh Tempo'],
                ['invoice_number' => 'DEMO-INV-006/2026', 'order' => 'DEMO-ORD-2026-007', 'client' => 'DEMO-KLN-002', 'months_ago' => 1, 'subtotal' => 16500000, 'tax' => 1815000, 'status' => 'Jatuh Tempo'],
                ['invoice_number' => 'DEMO-INV-007/2026', 'order' => 'DEMO-ORD-2026-009', 'client' => 'DEMO-KLN-003', 'months_ago' => 0, 'subtotal' => 12500000, 'tax' => 1375000, 'status' => 'Terkirim'],
                ['invoice_number' => 'DEMO-INV-008/2026', 'order' => 'DEMO-ORD-2026-010', 'client' => 'DEMO-KLN-001', 'months_ago' => 0, 'subtotal' => 22000000, 'tax' => 2420000, 'status' => 'Draft'],
            ];
            foreach ($invoiceRows as $row) {
                $issueDate = $this->dateFrom($orderDates[$row['order']], 2);
                $isPaid = $row['status'] === 'Terbayar';
                $this->upsert('invoices', ['invoice_number' => $row['invoice_number']], [
                    'client_id' => $clients[$row['client']],
                    'order_id' => $orders[$row['order']],
                    'issue_date' => $issueDate,
                    'due_date' => $this->dateFrom($issueDate, 14),
                    'subtotal' => $row['subtotal'],
                    'tax_amount' => $row['tax'],
                    'total_amount' => $row['subtotal'] + $row['tax'],
                    'status' => $row['status'],
                    'paid_at' => $isPaid ? $this->dateFrom($issueDate, 10) : null,
                    'file_path' => null,
                    'file_name' => null,
                    'file_mime' => null,
                    'file_size' => null,
                ]);
            }

            // 8. Pengeluaran, termasuk status yang tampil pada dashboard dan laporan.
            $expenseRows = [
                ['expense_number' => 'DEMO-EXP-001/2026', 'days_ago' => 2, 'category' => 'Operasional Kantor', 'description' => 'Pembelian alat tulis kantor (data demonstrasi).', 'amount' => 1250000, 'payment_method' => 'Transfer', 'status' => 'Menunggu Persetujuan'],
                ['expense_number' => 'DEMO-EXP-002/2026', 'days_ago' => 4, 'category' => 'Transportasi', 'description' => 'Transportasi kunjungan klien (data demonstrasi).', 'amount' => 850000, 'payment_method' => 'Tunai', 'status' => 'Draft'],
                ['expense_number' => 'DEMO-EXP-003/2026', 'days_ago' => 7, 'category' => 'Infrastruktur TI', 'description' => 'Pembelian kabel dan perangkat jaringan (data demonstrasi).', 'amount' => 2750000, 'payment_method' => 'Transfer', 'status' => 'Disetujui'],
                ['expense_number' => 'DEMO-EXP-004/2026', 'days_ago' => 11, 'category' => 'Langganan Software', 'description' => 'Perpanjangan lisensi software kerja (data demonstrasi).', 'amount' => 1800000, 'payment_method' => 'Kartu', 'status' => 'Dibayar'],
                ['expense_number' => 'DEMO-EXP-005/2026', 'days_ago' => 15, 'category' => 'Pemeliharaan', 'description' => 'Pemeliharaan perangkat kantor (data demonstrasi).', 'amount' => 2200000, 'payment_method' => 'Transfer', 'status' => 'Disetujui'],
                ['expense_number' => 'DEMO-EXP-006/2026', 'days_ago' => 21, 'category' => 'Konsumsi Rapat', 'description' => 'Konsumsi rapat koordinasi (data demonstrasi).', 'amount' => 675000, 'payment_method' => 'Tunai', 'status' => 'Dibayar'],
                ['expense_number' => 'DEMO-EXP-007/2026', 'days_ago' => 35, 'category' => 'Perjalanan Dinas', 'description' => 'Perjalanan dinas tim operasional (data demonstrasi).', 'amount' => 3400000, 'payment_method' => 'Transfer', 'status' => 'Dibayar'],
                ['expense_number' => 'DEMO-EXP-008/2026', 'days_ago' => 46, 'category' => 'Pelatihan', 'description' => 'Pelatihan internal tim (data demonstrasi).', 'amount' => 1500000, 'payment_method' => 'Transfer', 'status' => 'Dibatalkan'],
            ];
            foreach ($expenseRows as $row) {
                $this->upsert('expenses', ['expense_number' => $row['expense_number']], [
                    'expense_date' => $today->copy()->subDays($row['days_ago'])->toDateString(),
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                    'payment_method' => $row['payment_method'],
                    'status' => $row['status'],
                ]);
            }

            // 9. Riwayat absensi bulan berjalan, hanya untuk karyawan demo.
            $day = $today->copy()->startOfMonth();
            while ($day->lte($today)) {
                if ($day->isWeekday()) {
                    foreach (array_values($employees) as $index => $employeeId) {
                        $pattern = ((int) $day->format('j') + $index) % 17;
                        $status = match (true) {
                            $pattern === 0 => 'Izin',
                            $pattern === 1 => 'Sakit',
                            $pattern === 2 => 'Terlambat',
                            $pattern === 3 => 'Alpha',
                            default => 'Hadir',
                        };
                        $isAbsent = in_array($status, ['Izin', 'Sakit', 'Alpha'], true);
                        $checkOutPending = $day->isSameDay($today)
                            && now()->lt($today->copy()->setTime(17, 2));

                        $this->upsert('attendances', [
                            'employee_id' => $employeeId,
                            'attendance_date' => $day->toDateString(),
                        ], [
                            'check_in' => $isAbsent ? null : ($status === 'Terlambat' ? '09:12:00' : '08:05:00'),
                            'check_out' => $isAbsent || $checkOutPending ? null : '17:02:00',
                            'status' => $status,
                            'notes' => $status === 'Hadir' ? null : 'Contoh catatan absensi demonstrasi.',
                        ]);
                    }
                }

                $day->addDay();
            }

            // 10. Aktivitas tim yang menunjukkan variasi progres dan status.
            $activityRows = [
                ['employee' => 'DEMO-KRY-001', 'days_ago' => 1, 'title' => 'Pembaruan data administrasi karyawan', 'description' => 'Memeriksa dan memperbarui data administrasi karyawan contoh.', 'progress' => 100, 'status' => 'Selesai'],
                ['employee' => 'DEMO-KRY-001', 'days_ago' => 3, 'title' => 'Penyusunan jadwal evaluasi bulanan', 'description' => 'Menyusun jadwal evaluasi dan kebutuhan tim.', 'progress' => 65, 'status' => 'Proses'],
                ['employee' => 'DEMO-KRY-002', 'days_ago' => 2, 'title' => 'Koordinasi proyek klien', 'description' => 'Koordinasi pekerjaan dan pembagian tugas tim proyek.', 'progress' => 75, 'status' => 'Proses'],
                ['employee' => 'DEMO-KRY-002', 'days_ago' => 8, 'title' => 'Pemeriksaan hasil pekerjaan lapangan', 'description' => 'Pemeriksaan hasil pekerjaan operasional.', 'progress' => 100, 'status' => 'Selesai'],
                ['employee' => 'DEMO-KRY-003', 'days_ago' => 1, 'title' => 'Rekonsiliasi invoice demonstrasi', 'description' => 'Mencocokkan catatan invoice dan status pembayaran contoh.', 'progress' => 80, 'status' => 'Proses'],
                ['employee' => 'DEMO-KRY-003', 'days_ago' => 6, 'title' => 'Pemeriksaan pengeluaran bulanan', 'description' => 'Meninjau bukti dan kategori pengeluaran contoh.', 'progress' => 100, 'status' => 'Selesai'],
                ['employee' => 'DEMO-KRY-004', 'days_ago' => 1, 'title' => 'Pengembangan modul dashboard', 'description' => 'Melanjutkan pengembangan komponen dashboard aplikasi.', 'progress' => 70, 'status' => 'Proses'],
                ['employee' => 'DEMO-KRY-004', 'days_ago' => 5, 'title' => 'Perbaikan validasi formulir', 'description' => 'Melakukan pengujian validasi formulir pada aplikasi.', 'progress' => 100, 'status' => 'Selesai'],
                ['employee' => 'DEMO-KRY-005', 'days_ago' => 2, 'title' => 'Pengecekan perangkat jaringan', 'description' => 'Pemeriksaan perangkat jaringan pada lokasi kerja contoh.', 'progress' => 45, 'status' => 'Proses'],
                ['employee' => 'DEMO-KRY-006', 'days_ago' => 4, 'title' => 'Digitalisasi arsip dokumen', 'description' => 'Mengelompokkan arsip administrasi contoh.', 'progress' => 30, 'status' => 'Proses'],
                ['employee' => 'DEMO-KRY-007', 'days_ago' => 3, 'title' => 'Tindak lanjut calon klien', 'description' => 'Menyiapkan tindak lanjut penawaran layanan contoh.', 'progress' => 20, 'status' => 'Belum Dimulai'],
                ['employee' => 'DEMO-KRY-008', 'days_ago' => 1, 'title' => 'Penyempurnaan desain antarmuka', 'description' => 'Merapikan komponen antarmuka dan konsistensi tampilan.', 'progress' => 55, 'status' => 'Proses'],
                ['employee' => 'DEMO-KRY-008', 'days_ago' => 9, 'title' => 'Review desain halaman login', 'description' => 'Mengumpulkan dan menerapkan masukan desain.', 'progress' => 100, 'status' => 'Selesai'],
            ];
            foreach ($activityRows as $index => $row) {
                $identity = [
                    'employee_id' => $employees[$row['employee']],
                    'title' => '[DEMO] '.$row['title'],
                ];
                $this->upsert('activities', $identity, [
                    'activity_date' => $today->copy()->subDays($row['days_ago'])->toDateString(),
                    'description' => $row['description'],
                    'progress' => $row['progress'],
                    'status' => $row['status'],
                ]);
            }

            // 11. Penggajian untuk tiga periode terakhir.
            foreach ($employeeRows as $employeeRow) {
                $employeeId = $employees[$employeeRow['employee_code']];
                for ($monthsAgo = 2; $monthsAgo >= 0; $monthsAgo--) {
                    $periodDate = $now->copy()->subMonthsNoOverflow($monthsAgo);
                    $period = $periodDate->format('Y-m');
                    $basic = (float) $employeeRow['base_salary'];
                    $allowance = $monthsAgo === 0 ? 350000 : 500000;
                    $deduction = $monthsAgo === 0 ? 0 : 100000;
                    $status = $monthsAgo === 0 ? 'Disetujui' : 'Dibayar';
                    $this->upsert('payrolls', [
                        'employee_id' => $employeeId,
                        'payroll_period' => $period,
                    ], [
                        'basic_salary' => $basic,
                        'allowance' => $allowance,
                        'deduction' => $deduction,
                        'net_salary' => $basic + $allowance - $deduction,
                        'status' => $status,
                        'paid_at' => $monthsAgo === 0 ? null : $periodDate->copy()->endOfMonth()->toDateString(),
                    ]);
                }
            }

            $this->command?->info('Data dummy SIM-LJI berhasil dibuat/diperbarui tanpa menghapus data lain.');
        });
    }

    /** Upsert berdasarkan identitas stabil dan kembalikan primary key. */
    private function upsert(string $table, array $identity, array $values): int
    {
        $existingId = DB::table($table)->where($identity)->value('id');
        $attributes = $values + ['updated_at' => now()];

        if ($existingId !== null) {
            DB::table($table)->where('id', $existingId)->update($attributes);

            return (int) $existingId;
        }

        return (int) DB::table($table)->insertGetId($identity + $attributes + ['created_at' => now()]);
    }

    private function monthDate(int $monthsAgo, int $day): string
    {
        return now()->copy()->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays($day - 1)->toDateString();
    }

    private function dateFrom(string $date, int $days): string
    {
        return Carbon::parse($date)->addDays($days)->toDateString();
    }
}

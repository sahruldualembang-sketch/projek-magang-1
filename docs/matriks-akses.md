# Matriks Authority / Hak Akses SIM-LJI

Ringkasan ini mengikuti implementasi pada `config/simlji.php`, `routes/web.php`, `app/Http/Middleware/EnsureRole.php`, dan pembatasan di `ModuleController`, `AttendanceController`, `UserManagementController`, serta `DashboardController`.

Keterangan simbol:

| Simbol | Arti |
|---|---|
| ✔ | Akses penuh sesuai aturan modul (termasuk approval bila relevan) |
| ✎ | Hanya input/ubah pada status Draft (maker) |
| ◐ | Hanya dapat memperbarui progres & status aktivitas milik sendiri |
| 👁 | Lihat saja / hanya data diri sendiri (staff) |
| — | Tidak memiliki akses (403) |

## Matriks modul

| Modul | Super Admin | Director | HR | Finance | Operations | Staff |
|---|---|---|---|---|---|---|
| Data Karyawan | ✔ | ✔ | ✔ | — | — | 👁 |
| Data Absensi | ✔ | ✔ | ✔ | — | — | 👁 |
| Aktivitas Karyawan | ✔ | ✔ | ✔ | — | ✔ | ◐ |
| Data Gaji / Penggajian | ✔ | 👁 | ✎ | ✔ | — | 👁 |
| Data Klien | ✔ | ✔ | — | ✔ | ✔ | — |
| Pemesanan / Penjualan | ✔ | ✔ | — | ✔ | ✔ | — |
| Arsip Kontrak | ✔ | ✔ | — | ✔ | ✔ | — |
| Inventaris Perusahaan | ✔ | ✔ | — | — | ✔ | — |
| Legalitas Perusahaan | ✔ | ✔ | — | — | — | — |
| Invoice | ✔ | ✔ | — | ✔ | — | — |
| Pengeluaran Perusahaan | ✔ | 👁 | — | ✔ | ✎ | — |
| Laporan Laba/Rugi | ✔ | ✔ | — | ✔ | — | — |
| Pengguna Sistem (`/pengguna`) | ✔ | — | — | — | — | — |
| Pengaturan jam kerja (`/pengaturan/absensi`) | ✔ | — | — | — | — | — |

## Fase 1: alur maker-checker (Penggajian & Pengeluaran)

| Tahap | Penggajian | Pengeluaran |
|---|---|---|
| Input / ubah (Draft) | HR, Super Admin | Operations, Super Admin |
| Ajukan untuk persetujuan | — (langsung dari Draft) | Operations, Super Admin |
| Setujui | Finance, Super Admin | Finance, Super Admin |
| Tandai dibayar | Finance, Super Admin | Finance, Super Admin |
| Kembalikan ke Draft | Finance, Super Admin | Finance, Super Admin |

Alur status: `Draft → [Menunggu Persetujuan] → Disetujui → Dibayar`.
Setelah keluar dari Draft, data **terkunci dari edit**; perubahan hanya melalui pengembalian ke Draft dengan alasan yang tersimpan di `audit_logs`. Pembatalan (`Dibatalkan`) belum termasuk fase ini.

## Ringkasan per role

### 1. Super Admin
- Hak penuh pada **seluruh modul** dan halaman administrasi.
- Satu-satunya yang dapat **membuat/mengubah akun** (role, status aktif, tautan karyawan, password) melalui menu **Pengguna Sistem**.
- Satu-satunya yang dapat mengubah **Pengaturan jam kerja absensi**.
- Dapat melakukan **koreksi absensi** dan **menyetujui/menolak pengajuan izin/sakit** (bersama HR).
- Dapat menjalankan seluruh tahap maker-checker Penggajian/Pengeluaran (input, ajukan, setujui, bayar, kembalikan).
- Sistem menjaga minimal satu Super Admin aktif; akun sendiri tidak boleh dinonaktifkan/diubah perannya.
- Dashboard menampilkan seluruh kartu ringkasan dan tren pesanan.

### 2. Director (Direktur)
- Akses **baca/tulis modul bisnis**, termasuk Legalitas Perusahaan dan Laporan Laba/Rugi.
- Khusus **Penggajian dan Pengeluaran: lihat saja** — persetujuan dan pembayaran dipegang Finance.
- Dashboard menampilkan seluruh ringkasan manajerial.
- **Tidak** memiliki akses Pengguna Sistem, Pengaturan, maupun aksi workflow absensi (koreksi/approval).

### 3. HR
- Fokus pada SDM: **Karyawan, Absensi, Aktivitas Karyawan, Penggajian**.
- Penggajian: **input/ubah Draft**; tidak dapat menyetujui atau menandai pembayaran.
- Dapat **koreksi absensi** dan **menyetujui/menolak pengajuan izin/sakit**.
- Dashboard: total karyawan, kehadiran hari ini, aktivitas berjalan, dan ringkasan penggajian.
- **Tidak** memiliki akses klien, pesanan, kontrak, invoice, pengeluaran, aset, legalitas, laba/rugi, pengguna, dan pengaturan.

### 4. Finance (Keuangan)
- Fokus keuangan: **Penggajian, Klien, Pesanan, Kontrak, Invoice, Pengeluaran, Laporan Laba/Rugi**.
- Penggajian & Pengeluaran: **menyetujui, menandai dibayar, dan mengembalikan ke Draft**; tidak mengubah nominal/isi data (maker-checker).
- Dashboard: ringkasan klien/pesanan, tren nilai pesanan, invoice, pengeluaran, dan laba/rugi.
- **Tidak** memiliki akses karyawan, absensi, aktivitas, aset, legalitas, pengguna, dan pengaturan.

### 5. Operations (Operasional)
- Fokus operasional: **Aktivitas Karyawan, Klien, Pesanan, Kontrak, Inventaris, Pengeluaran**.
- Pengeluaran: **input/ubah Draft dan mengajukan**; tidak dapat menyetujui atau membayar.
- Dashboard: aktivitas berjalan, klien, pesanan, aset, dan pengeluaran.
- **Tidak** memiliki akses penggajian, invoice, legalitas, laba/rugi, absensi, data karyawan (mentah), pengguna, dan pengaturan.

### 6. Staff (Karyawan)
- Akses **hanya data diri sendiri**. Read-only pada Karyawan, Absensi, dan Penggajian.
- Pada **Aktivitas Karyawan**, dapat memperbarui **progres (%) dan status** miliknya sendiri melalui halaman *Ubah Progres* atau form *Ubah* pada aktivitas miliknya. Keduanya hanya menampilkan progres & status; field lain (karyawan, judul, deskripsi, tanggal) dikunci dan diabaikan server.
- Wajib memiliki `employee_id` yang tertaut ke data karyawan; jika belum, akses ditolak (403).
- Dapat melakukan **absen masuk/pulang** dan **mengajukan izin/sakit**, bukan menyetujui.
- Tidak dapat membuat atau menghapus data melalui modul mana pun.
- Dashboard khusus: jumlah absensi bulan ini dan aktivitas yang belum selesai miliknya.

## Aturan pembatasan lintas role

1. **Staff selalu *scoped*.** `ModuleController::scopedQuery` membatasi baris ke `employee_id` miliknya. Staff tidak dapat `create/store/destroy`. Form `edit/update` hanya dibuka untuk **aktivitas miliknya**, dan server hanya menyimpan `progress` & `status`; modul lain tetap ditolak 403.
2. **Absensi tidak diedit lewat halaman modul.** CRUD absensi lewat `/modul/attendances` diblokir; gunakan halaman absensi (clock in/out, pengajuan, koreksi oleh HR/Super Admin) agar jejak audit tersimpan.
3. **Catatan keuangan tidak dihapus.** `payrolls`, `invoices`, dan `expenses` tidak bisa dihapus oleh role apa pun — koreksi melalui status/prosedur pembatalan.
4. **Maker-checker Penggajian/Pengeluaran.** Hanya maker (HR/Operations) yang dapat membuat dan mengubah data selama berstatus Draft. Finance hanya dapat menyetujui, membayar, atau mengembalikan ke Draft. Setiap transisi dicatat di `audit_logs` dengan aksi `submitted`, `approved`, `paid`, atau `returned` (beserta alasan).
5. **Aksi workflow absensi:** clock in/out dan pengajuan izin hanya staff; persetujuan/penolakan dan koreksi hanya HR + Super Admin.
6. **Manajemen akun & pengaturan hanya Super Admin**, dengan pengaman minimal satu Super Admin aktif dan larangan mengubah status/role akun sendiri.
7. **Unduh lampiran** (kontrak, legalitas, invoice) tersedia bagi role yang memiliki akses modul terkait.
8. **Progres aktivitas staff.** Staff hanya boleh mengubah `progress` dan `status` aktivitas miliknya, baik lewat halaman `activities.progress.*` maupun form `modules.update`. Server mengabaikan field lain. Perubahan dicatat di `audit_logs` dengan aksi `progress_updated` (halaman progres) atau `updated` (form edit).

> Catatan: semua pemeriksaan dilakukan di server melalui middleware `role:` dan `hasRole()` pada controller. Matriks ini menggambarkan kondisi kode saat dokumentasi dibuat; review ulang bila kebijakan perusahaan berubah.

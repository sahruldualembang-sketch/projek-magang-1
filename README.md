# SIM-LJI — Sistem Informasi Manajemen PT Liny Jaya Informatika

Aplikasi internal berbasis Laravel 13, PHP 8.4.1+, MySQL 8.4, Blade, dan CSS responsif. Struktur ini merupakan fondasi aplikasi dan implementasi awal CRUD untuk 12 modul.

## Modul
1. Data Karyawan
2. Data Absensi
3. Aktivitas Karyawan
4. Data Gaji
5. Data Klien
6. Pemesanan/Penjualan
7. Arsip Kontrak
8. Inventaris Perusahaan
9. Legalitas Perusahaan
10. Invoice
11. Pengeluaran Perusahaan
12. Laporan Laba/Rugi

## Pengguna Sistem
Super Admin dapat membuat dan mengubah akun melalui menu **Pengguna Sistem**. Akun staff wajib ditautkan ke data karyawan. Sistem menjaga minimal satu Super Admin tetap aktif.

## Role
- `super_admin`: seluruh modul dan akses administrasi.
- `director`: akses manajemen dan laporan.
- `hr`: karyawan, absensi, aktivitas, penggajian.
- `finance`: klien/pesanan yang terkait, penggajian, invoice, pengeluaran, laporan.
- `operations`: aktivitas, klien, pesanan, kontrak, inventaris, pengeluaran.
- `staff`: akses terbatas untuk melihat data diri/absensi/aktivitas/penggajian miliknya jika akun dikaitkan ke karyawan.

Akses diperiksa di server melalui middleware dan konfigurasi role. Untuk produksi, lakukan review matriks izin sesuai kebijakan perusahaan sebelum aktivasi akun.

## Persyaratan
- PHP 8.4.1 atau lebih baru dan ekstensi Laravel yang diperlukan.
- Composer 2.
- MySQL 8.4 LTS (atau versi kompatibel yang telah diuji).
- Node.js 22 LTS dan npm.

## Instalasi lokal
```bash
composer install
cp .env.example .env
php artisan key:generate
# Sesuaikan DB_DATABASE, DB_USERNAME, DB_PASSWORD di .env
php artisan migrate --seed
npm install
npm run build
php artisan serve
```
Buka `http://127.0.0.1:8000`.

## Akun awal dan kebijakan kata sandi

Kredensial awal **tidak dicantumkan di dokumentasi maupun repositori**. Untuk memperoleh akun awal, hubungi administrator sistem PT Liny Jaya Informatika melalui kanal resmi internal.

Ketentuan yang berlaku:

- Setiap pengguna wajib mengganti kata sandi pada login pertama dan mengikuti kebijakan rotasi yang ditetapkan perusahaan.
- Kata sandi bersifat rahasia per individu. Jangan membagikannya, menuliskannya di dokumentasi, atau menyimpannya di repositori.
- Akun bawaan/sample hasil seeder hanya untuk instalasi lokal/staging dan **wajib dihapus atau dinonaktifkan sebelum produksi**.
- Sistem menjaga minimal satu Super Admin aktif. Super Admin tidak dapat menonaktifkan akun atau mengubah perannya sendiri.

**Rotasi kata sandi (Super Admin):** buka menu **Pengguna** → pilih akun → **Ubah** → isi kolom kata sandi baru (minimal 12 karakter) → simpan. Lakukan hal yang sama untuk akun lain sesuai kebutuhan, lalu sampaikan kata sandi baru kepada pemilik akun melalui kanal resmi, bukan melalui berkas bersama.

## Data dummy untuk demonstrasi

Seeder demo disediakan agar halaman tidak kosong saat aplikasi diperiksa. Data yang dibuat mencakup 8 karyawan, absensi bulan berjalan, aktivitas, 24 catatan penggajian untuk tiga periode, 6 klien, 10 pesanan, 5 kontrak, 7 aset, 4 dokumen legalitas, 8 invoice, dan 8 pengeluaran. Tanggal pesanan dibuat tersebar pada enam bulan terakhir agar grafik dashboard terisi.

Akun contoh untuk mencoba beberapa hak akses. Kredensialnya diperoleh dari administrator pada lingkungan lokal/staging dan **tidak boleh dipakai di produksi**:

| Role | Email |
|---|---|
| Super Admin | `admin@sim-lji.local` |
| Direktur | `direktur@demo.sim-lji.local` |
| HR | `hr@demo.sim-lji.local` |
| Keuangan | `keuangan@demo.sim-lji.local` |
| Operasional | `operasional@demo.sim-lji.local` |
| Karyawan | `staff@demo.sim-lji.local` |

Seeder tidak mengganti kata sandi, peran, atau status akun Super Admin yang sudah ada. Kata sandi akun contoh ditetapkan oleh administrator lingkungan lokal/staging dan tidak disimpan di sini. Sebelum produksi, hapus atau nonaktifkan seluruh akun contoh dan lakukan rotasi kata sandi untuk akun yang tetap digunakan.

Untuk database yang sudah dimigrasikan, jalankan dari folder proyek:

```bash
php artisan db:seed
```

Seeder hanya berjalan pada environment `local`, `testing`, atau `staging`. Record contoh menggunakan kode/email berawalan `DEMO`, judul bertanda `[DEMO]`, atau terhubung ke karyawan demo, lalu diperbarui berdasarkan identitas demonstrasi ketika seeder dijalankan ulang. Seeder tidak menghapus atau mereset record lain. Data ini sintetis, bukan data aktual PT Liny Jaya Informatika, dan tidak boleh dipakai sebagai data operasional/keuangan resmi. Jangan jalankan dengan akun demo di produksi.

## Absensi mandiri

Migrasi absensi menambahkan Pengaturan jam kerja dengan nilai awal yang telah dikonfirmasi: mulai 08:00, selesai 16:30 pada Senin–Jumat dan 15:00 pada Sabtu, toleransi keterlambatan 60 menit, serta tanpa tanggal libur khusus. Super Admin dapat mengubah jadwal melalui menu **Pengaturan**. Staff dapat absen masuk/pulang dan mengajukan izin atau sakit; HR memproses pengajuan serta melakukan koreksi dengan alasan yang tersimpan di riwayat audit.

Penetapan Alpha otomatis memerlukan Laravel Scheduler. Setelah deployment, pastikan cron menjalankan `php artisan schedule:run` setiap menit. Perintah `attendance:mark-absent` memeriksa jadwal dan tidak mengganti absensi yang sudah ada.

Contoh entri cron:
```cron
* * * * * cd /path/to/sim-lji && php artisan schedule:run >> /dev/null 2>&1
```

## Menjalankan untuk pengembangan
```bash
composer run dev
```

## Catatan data & laporan keuangan
Laporan Laba/Rugi versi awal adalah ringkasan manajerial: pendapatan dihitung dari pesanan berstatus `Selesai`; pengeluaran dan penggajian dihitung dari status `Disetujui`/`Dibayar`. Ini belum merupakan laporan akuntansi formal. Sebelum dipakai sebagai laporan resmi, perusahaan perlu menetapkan basis pengakuan pendapatan, pajak, akun, jurnal debit-kredit, periode tutup buku, dan prosedur koreksi bersama penanggung jawab keuangan.

## Catatan implementasi yang masih perlu dilanjutkan
- Perluasan workflow approval/maker-checker ke invoice, pesanan, dan kontrak. Penggajian & pengeluaran sudah memakai alur draft → setujui → bayar dengan pemisahan tugas (lihat `docs/matriks-akses.md`).
- Upload dokumen privat untuk kontrak, legalitas, dan invoice (PDF/JPG/PNG, maksimal 10 MB per file).
- Detail pesanan multi-item dan perhitungan total otomatis server-side.
- Penerbitan invoice dari pesanan, pencatatan pembayaran parsial, dan rekonsiliasi.
- Jurnal umum double-entry, bagan akun, tutup periode, dan laporan keuangan formal.
- Absensi berbasis lokasi/perangkat bila diperlukan; belum diaktifkan.
- MFA, kebijakan password, reset password, notifikasi, ekspor, backup, monitoring, dan hardening server.
- Uji otomatis dan browser QA sudah dilakukan pada lingkungan lokal; hasil, temuan, dan bukti tercantum pada `Laporan_QA_SIM_LJI.docx` dan folder `Bukti_QA_SIM_LJI/`. Ulangi pada lingkungan staging dengan MySQL sebelum produksi.

## Deployment

Jangan arahkan document root ke root proyek. Arahkan Nginx/Apache ke folder `public/`. Gunakan `.env` produksi yang tidak masuk Git, `APP_DEBUG=false`, HTTPS, database yang tidak terbuka ke internet, backup terenkripsi, dan storage privat. Deployment belum dilakukan.

### Checklist sebelum publikasi

- [ ] `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` memakai HTTPS, `APP_KEY` unik, dan session/cache disesuaikan dengan infrastruktur.
- [ ] `ATTENDANCE_API_KEY` diisi dengan kunci acak kuat (minimal 32 karakter) dan disimpan sebagai secret server, bukan di repositori.
- [ ] Seluruh akun contoh/demo dihapus atau dinonaktifkan; kata sandi akun yang dipakai sudah dirotasi oleh pemiliknya.
- [ ] Data dummy demo tidak dibawa ke database produksi.
- [ ] Cron scheduler dipasang untuk penetapan Alpha otomatis: `* * * * * cd /path/to/sim-lji && php artisan schedule:run >> /dev/null 2>&1`.
- [ ] Jalankan `npm run build`, `php artisan view:clear`, `php artisan optimize:clear`, dan `php artisan test` sebagai gate terakhir.
- [ ] Lakukan UAT bersama HR dan Finance, lalu simpan bukti persetujuan sesuai prosedur perusahaan.

Rincian temuan QA dan status perbaikannya dapat dilihat pada `Laporan_QA_SIM_LJI.docx`. Kredensial tidak disimpan di dokumentasi ini; hubungi administrator sistem untuk akun awal.


## Pembaruan antarmuka SIM-LJI
Antarmuka diperbarui dengan arah visual **Modern Corporate Business Management**:
- Navigasi samping navy dengan menu yang mengikuti hak akses pengguna.
- Header aplikasi yang konsisten dan responsif.
- Dashboard dengan ringkasan sesuai role, indikator kehadiran dan aktivitas bagi role yang berwenang, tren nilai pesanan enam bulan, serta daftar pesanan terbaru.
- Tampilan daftar, formulir, pengguna, laporan laba/rugi, serta login yang diseragamkan.
- Grafik dashboard dihitung dari nilai pesanan yang tersimpan di database. Seeder demo di atas menyediakan data sintetis untuk keperluan pengujian tampilan; tanpa menjalankannya, dashboard menampilkan keadaan kosong bila database belum berisi data.

Setelah mengganti source code, jalankan ulang build frontend:
```bash
npm install
npm run build
php artisan view:clear
php artisan optimize:clear
```
Jangan menimpa file `.env` milik instalasi lokal. Arsip source yang dibagikan tidak menyertakan `.env`, `vendor`, atau `node_modules`.

## Pembaruan pemeriksaan lanjutan
- Dashboard menghitung dan menampilkan kartu ringkasan sesuai modul yang dapat diakses oleh role pengguna; akun HR tidak otomatis menerima ringkasan klien, pesanan, atau pengeluaran.
- Role HR kini juga dapat membuka Aktivitas Karyawan, sesuai uraian matriks role di bagian Role.
- Filter Laporan Laba/Rugi memvalidasi format tanggal dan menolak tanggal akhir yang lebih awal dari tanggal mulai.
- Seeder aktivitas demo memakai identitas stabil agar eksekusi ulang tidak membuat catatan aktivitas contoh baru setiap hari.

Pemeriksaan source pada paket ini: 38 file PHP lolos `php -l`, 17 route berhasil dimuat, 8 template Blade berhasil dikompilasi, dan CSS berhasil diparse. Uji database, PHPUnit, browser, serta build frontend produksi belum dapat diverifikasi pada lingkungan pemeriksaan ini karena tidak tersedia driver PDO database/ekstensi DOM dan dependency Rollup Linux; jalankan build dan uji database pada lingkungan Windows lokal setelah ekstraksi.


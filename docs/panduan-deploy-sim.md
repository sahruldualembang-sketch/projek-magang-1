# Panduan Deploy SIM-LJI ke `sim.sistemlji.com`

Dokumen ini merangkum konfigurasi yang dibutuhkan agar aplikasi bisa diakses melalui subdomain `sim.sistemlji.com`. Sesuaikan nama pengguna, kata sandi, dan path dengan kondisi server sebenarnya. **Jangan simpan kata sandi atau API key di repositori.**

## 0. Pembagian tugas

| Pihak | Tugas |
|---|---|
| Kak Taufik / tim infra | Membuat DNS subdomain `sim` → IP server, menyiapkan server + database, sertifikat HTTPS, akses SSH |
| Pengembang / admin aplikasi | Upload kode, menyiapkan `.env`, migrasi, build aset, cron, verifikasi fungsi |

## 1. Prasyarat server

- PHP 8.4.1 atau lebih baru dengan ekstensi: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath`, `intl`, `fileinfo`, `openssl`
- Composer 2
- Node.js 22 LTS + npm
- MySQL 8.4 (disarankan; lokal memakai SQLite)
- Nginx + PHP-FPM 8.4

## 2. DNS

Buat record di domain `sistemlji.com`:

| Type | Name | Value |
|---|---|---|
| A | `sim` | IP publik server |

Alternatif: CNAME `sim` → hostname server. Tunggu propagasi sebelum mengaktifkan HTTPS.

## 3. Letak file

```
/var/www/sim-lji/            ← root proyek (JANGAN dijadikan document root)
/var/www/sim-lji/public/     ← document root Nginx
```

## 4. Contoh `.env` produksi

```dotenv
APP_NAME="SIM-LJI"
APP_ENV=production
APP_KEY=base64:GENERATE_DENGAN_PHP_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://sim.sistemlji.com
APP_TIMEZONE=Asia/Makassar
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sim_lji
DB_USERNAME=sim_lji_user
DB_PASSWORD=ISI_DARI_SECRET_SERVER

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# WAJIB: kunci acak minimal 32 karakter untuk sinkronisasi presensi.
# Tanpa kunci ini, endpoint API tidak boleh menerima data (temuan QA-02).
ATTENDANCE_API_KEY=ISI_KUNCI_RAHASIA_ACAK_32_KARAKTER_LEBIH
```

Buat kunci acak, misalnya: `openssl rand -hex 32`.

## 5. Konfigurasi Nginx

Simpan sebagai `/etc/nginx/sites-available/sim.sistemlji.com`, lalu symlink ke `sites-enabled`:

```nginx
server {
    listen 80;
    server_name sim.sistemlji.com;
    root /var/www/sim-lji/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # Upload dokumen maksimal 10 MB -> beri ruang sedikit lebih besar.
    client_max_body_size 12M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Uji konfigurasi lalu reload: `sudo nginx -t && sudo systemctl reload nginx`.

Aktifkan HTTPS: `sudo certbot --nginx -d sim.sistemlji.com`, lalu pastikan `APP_URL` memakai `https://`.

## 6. Langkah deploy

```bash
cd /var/www/sim-lji

composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env          # lalu isi sesuai bagian 4
php artisan key:generate

# Database produksi: jalankan migrasi SAJA. Jangan jalankan db:seed.
php artisan migrate --force

# Aset frontend WAJIB di-build (public/build tidak masuk Git).
npm ci
npm run build

php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear    # hanya bila perlu debug; pilih salah satu dengan cache di atas

sudo chown -R www-data:www-data storage bootstrap/cache public/build
sudo chmod -R ug+rwX storage bootstrap/cache
```

Catatan: `php artisan optimize:clear` dan `config:cache/route:cache/view:cache` saling bertentangan. Gunakan **salah satu** strategi: either cache semua untuk produksi, atau clear semua saat troubleshooting.

## 7. Cron scheduler

Penetapan Alpha otomatis memerlukan scheduler. Tambahkan cron:

```cron
* * * * * cd /var/www/sim-lji && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Verifikasi setelah deploy

| Pemeriksaan | Harapan |
|---|---|
| `https://sim.sistemlji.com/login` | HTTP 200, logo PT LJI tampil, CSS tidak rusak |
| Login dengan akun produksi | Berhasil, dashboard sesuai role |
| Menu Data Gaji / Pengeluaran | Tombol Setujui / Dibayar / Kembalikan berfungsi |
| Post API presensi **tanpa** `X-API-KEY` | Ditolak (tidak boleh HTTP 200) — bukti QA-02 tertutup |
| Post API presensi dengan API key benar | HTTP 200, data tercatat |
| Koreksi progres aktivitas | Progres 100% otomatis Selesai, 0% otomatis Belum Dimulai |
| `php artisan test` (di staging) | Semua test lolos |

## 9. Sebelum go-live

- [ ] Ganti semua kata sandi bawaan/demo dan nonaktifkan akun contoh
- [ ] Tidak ada data dummy demo di database produksi
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS aktif
- [ ] `ATTENDANCE_API_KEY` terisi dan disimpan sebagai secret server
- [ ] Database tidak terbuka ke internet; backup terenkripsi tersedia
- [ ] UAT bersama HR dan Finance, simpan bukti persetujuan

## 10. Troubleshooting

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Halaman 500 setelah upload kode | `.env` belum ada / `APP_KEY` kosong | `php artisan key:generate`, lalu `config:clear` |
| CSS/JS tidak tampil | `public/build` belum di-build | `npm ci && npm run build` |
| Catatan di dashboard tampak rusak | CSS hasil build basi | Build ulang + `php artisan view:clear` |
|Login redirect loop | `APP_URL` tidak sesuai HTTPS | Samakan `APP_URL` dengan `https://sim.sistemlji.com` |
| Alpha otomatis tidak jalan | Cron belum dipasang | Tambahkan entri cron pada bagian 7 |

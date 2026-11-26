# Runbook Produksi SIM-LJI

> Dokumen ini berisi prosedur **backup, restore, rollback, monitoring, dan deploy** untuk
> server produksi `sim.sistemlji.com`.
> Diperbarui: 2026-11-26.
> Semua perintah dijalankan **di server**, bukan di laptop dev. Ganti placeholder `<...>`.
>
> Salin file ini ke server di `/root/sim-lji-runbook.md` (di luar webroot agar tidak bisa
> diakses publik). Source tetap disimpan di `docs/runbook-produksi.md` di Git.

---

## Pemulihan cepat saat masalah

| Gejala | Langkah pertama |
|---|---|
| Halaman putih / 500 setelah deploy | `tail -100 storage/logs/laravel-*.log`, lalu lihat **Rollback deploy** di bawah |
| Tidak bisa login semua akun | Cek koneksi DB, `php artisan config:clear`, lalu `php artisan sim:create-admin` bila users kosong |
| Alpha otomatis tidak jalan | `php artisan schedule:list`, cek cron `schedule:run`, cek `attendance_settings` |
| Absensi API ditolak | Cek `ATTENDANCE_API_KEY`, lalu `curl -H "X-API-KEY: <key>" https://sim.sistemlji.com/api/v1/attendance/status` |
| CSS/JS rusak setelah deploy | `cd $APP_DIR && npm ci && npm run build && php artisan view:clear` |
| Data karyawan/gaji hilang atau salah | **Jangan hapus apa pun.** Lihat **Restore drill**, pulihkan backup terbaru ke staging dulu |
| Disk penuh | `df -h`, lalu `sudo tail -50 /var/log/sim-lji-backup.log` dan cek logrotate |

Prinsip: **jangan pernah menghapus/mengubah data produksi saat panik.** Pulihkan dari backup di staging dulu, verifikasi, baru terapkan ke produksi.

---

## 0. Variabel

```bash
APP_DIR=/www/wwwroot/sim.sistemlji.com
BACKUP_DIR=/var/backups/sim-lji
DB_NAME=sim_lji
DB_USER=sim_lji
```

Simpan kredensial DB di file privat (jangan di command line, karena terlihat di `ps`):

```bash
sudo tee /root/.sim-lji-my.cnf >/dev/null <<'EOF'
[client]
user=sim_lji
password=PASSWORD_DI_SINI
EOF
sudo chmod 600 /root/.sim-lji-my.cnf

sudo mkdir -p $BACKUP_DIR/{db,files}
sudo chmod 700 $BACKUP_DIR
openssl rand -base64 48 | sudo tee /root/.sim-lji-backup-pass >/dev/null
sudo chmod 600 /root/.sim-lji-backup-pass
```

## 1. Backup harian

```bash
sudo tee /usr/local/bin/sim-lji-backup.sh >/dev/null <<'EOF'
#!/bin/bash
set -euo pipefail
APP_DIR=/www/wwwroot/sim.sistemlji.com
BACKUP_DIR=/var/backups/sim-lji
STAMP=$(date +%F_%H%M)
PASS=/root/.sim-lji-backup-pass

mysqldump --defaults-extra-file=/root/.sim-lji-my.cnf \
  --single-transaction --quick --routines --triggers sim_lji \
  | gzip \
  | openssl enc -aes-256-cbc -pbkdf2 -pass file:$PASS \
  > "$BACKUP_DIR/db/sim_lji_$STAMP.sql.gz.enc"

tar -czf - -C "$APP_DIR" storage/app public/assets public/images .env \
  | openssl enc -aes-256-cbc -pbkdf2 -pass file:$PASS \
  > "$BACKUP_DIR/files/app_$STAMP.tar.gz.enc"

find "$BACKUP_DIR" -type f -mtime +14 -delete
EOF
sudo chmod 750 /usr/local/bin/sim-lji-backup.sh
sudo /usr/local/bin/sim-lji-backup.sh
```

Cron (backup tiap jam 02.00):

```bash
sudo crontab -e
# tambahkan:
0 2 * * * /usr/local/bin/sim-lji-backup.sh >> /var/log/sim-lji-backup.log 2>&1
```

Offsite (salinan ke server lain / object storage):

```bash
rclone copy $BACKUP_DIR remote:sim-lji-backups --exclude '*.tmp'
```

Verifikasi:

```bash
ls -lh $BACKUP_DIR/db $BACKUP_DIR/files
sha256sum $BACKUP_DIR/db/*.enc | tail -1
```

## 2. Restore drill (bulanan, di staging)

```bash
openssl enc -d -aes-256-cbc -pbkdf2 -pass file:/root/.sim-lji-backup-pass \
  -in $BACKUP_DIR/db/sim_lji_2026-11-26_0200.sql.gz.enc \
  | gunzip \
  | mysql --defaults-extra-file=/root/.sim-lji-my.cnf sim_lji_staging
```

Lalu cek:

```bash
mysql --defaults-extra-file=/root/.sim-lji-my.cnf -e "
SELECT (SELECT COUNT(*) FROM employees) AS karyawan,
       (SELECT COUNT(*) FROM users) AS akun,
       (SELECT COUNT(*) FROM payrolls) AS gaji;"

cd /www/wwwroot/sim-staging && php artisan test --do-not-cache-result
```

Catat di log: tanggal backup, nama file, `sha256`, waktu pemulihan (RTO), hasil cek.
**Backup dianggap valid hanya kalau drill berhasil.**

## 3. Rollback deploy

Sebelum deploy:

```bash
cd $APP_DIR
git rev-parse HEAD > /root/sim-lji-release-before.txt
sudo /usr/local/bin/sim-lji-backup.sh
git tag -a "v$(date +%Y%m%d-%H%M)" -m "Rilis sebelum deploy"
git push origin --tags
```

Kalau deploy gagal:

```bash
cd $APP_DIR
php artisan down --secret="rahasia-rollback"

git checkout <tag-yang-benar>
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Hanya jika migrasi ikut gagal dan belum ada data penting tertulis:
php artisan migrate:rollback --step=1

php artisan view:clear
php artisan up
```

> Hati-hati: jangan rollback migrasi kalau sudah ada transaksi/data baru setelah deploy —
> pulihkan DB dari backup instead.

## 4. Monitoring & log

`.env` produksi:

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=daily
LOG_LEVEL=error
```

Logrotate:

```bash
sudo tee /etc/logrotate.d/sim-lji >/dev/null <<'EOF'
/www/wwwroot/sim.sistemlji.com/storage/logs/*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
}
EOF
```

Cron Laravel (wajib untuk Alpha otomatis):

```bash
* * * * * cd /www/wwwroot/sim.sistemlji.com && php artisan schedule:run >> /dev/null 2>&1
```

Cek:

```bash
cd $APP_DIR && php artisan schedule:list
df -h
sudo tail -50 /var/log/sim-lji-backup.log
```

Monitoring external: ping `/login` dan `/api/v1/attendance/status` tiap 5 menit → alert
kalau bukan 200; plus alert expiration SSL dan disk > 80%.

## 5. Build aset sebelum deploy

Manifest lokal pernah lebih lama dari CSS → wajib build ulang di server:

```bash
cd $APP_DIR
git pull origin qa-logo-activity
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan view:clear
php artisan config:cache
php artisan route:cache
```

> Jangan jalankan `optimize:clear` setelah `config:cache`/`route:cache` — itu membatalkan
> cache. Pilih salah satu prosedur.

## 6. Acceptance sebelum go-live

- [ ] DNS `sim.sistemlji.com` resolv + SSL aktif + redirect 443
- [ ] Backup harian berjalan & restore drill berhasil (ada bukti)
- [ ] Rollback tag tersedia & sudah dicoba di staging
- [ ] Cron `schedule:run` aktif (Alpha otomatis teruji)
- [ ] `npm run build` setelah commit terakhir; halaman login/dashboard tampil normal
- [ ] DB produksi kosong dari seeder demo; `php artisan sim:create-admin` sudah dijalankan
- [ ] `ATTENDANCE_API_KEY` secret ≥32 karakter; `APP_DEBUG=false`
- [ ] UAT HR & Finance + bukti persetujuan tersimpan

---

## Di mana menyimpan salinan

| Tempat | Fungsi | Akses |
|---|---|---|
| `docs/runbook-produksi.md` di repo | Source of truth, ikut Git, dekat `panduan-deploy-sim.md` | Siapa pun yang clone repo |
| `/root/sim-lji-runbook.md` di server | Dipakai saat incident lewat SSH | Hanya root; di luar webroot |
| Opsional: `/usr/local/bin/runbook-sim` | Shortcut `runbook-sim` untuk `cat /root/sim-lji-runbook.md` | Admin server |

Jangan simpan di `public/` atau folder yang bisa diakses browser, karena file ini berisi
nama user DB, path kredensial, dan prosedur pemulihan.

# Dokumentasi Integrasi Real-Time API Absensi — SIM-LJI

Sistem Informasi Manajemen PT Liny Jaya Informatika (SIM-LJI) menyediakan endpoint REST API untuk menerima data presensi secara langsung (*real-time*) dari aplikasi luar, mesin fingerprint, maupun aplikasi mobile presensi.

---

## 1. Konfigurasi Server (`.env`)

Tambahkan variabel berikut pada file `.env` di server:

```ini
ATTENDANCE_API_KEY=<isi-dengan-kunci-acak-kuat>
```

Setiap request dari aplikasi luar wajib menyertakan header:
`X-API-KEY: <kunci-yang-sama-dengan-konfigurasi-server>` (atau Bearer token).
Ganti placeholder dengan kunci acak rahasia dan jangan gunakan contoh kunci publik.

> Catatan: Pada environment lokal / non-production, jika `ATTENDANCE_API_KEY` belum diatur, API dapat diuji coba tanpa validasi ketat. Pada environment `production`, API Key wajib disertakan.

---

## 2. Endpoint Cek Status Layanan

- **URL:** `GET /api/v1/attendance/status`
- **Tujuan:** Memeriksa apakah endpoint API SIM-LJI siap menerima data.
- **Respons (200 OK):**
```json
{
  "success": true,
  "service": "SIM-LJI Attendance Real-Time Sync API",
  "version": "1.0",
  "status": "ready",
  "timestamp": "2026-11-23T10:00:00+08:00"
}
```

---

## 3. Endpoint Rekam Presensi Real-Time

- **URL:** `POST /api/v1/attendance/record`
- **Content-Type:** `application/json`
- **Headers:**
  - `Accept: application/json`
  - `X-API-KEY: <ATTENDANCE_API_KEY>`

### A. Format Data (*Payload*) Absen Masuk (*Clock-In*)

```json
{
  "employee_code": "DEMO-KRY-001",
  "type": "in",
  "time": "07:55:00",
  "attendance_date": "2026-11-23",
  "notes": "Fingerprint Mesin Pintu Utama"
}
```

### B. Format Data (*Payload*) Absen Pulang (*Clock-Out*)

```json
{
  "employee_code": "DEMO-KRY-001",
  "type": "out",
  "time": "16:45:00",
  "attendance_date": "2026-11-23",
  "notes": "Fingerprint Mesin Pintu Utama"
}
```

### Parameter yang Didukung:

| Field | Tipe | Wajib? | Keterangan |
|---|---|---|---|
| `employee_code` | string | Wajib* | Kode Karyawan yang terdaftar di SIM-LJI (misal `DEMO-KRY-001`). *Dapat diganti `employee_id`. |
| `employee_id` | integer | Opsional | ID Karyawan di database (alternatif dari `employee_code`). |
| `type` | string | **Wajib** | Jenis presensi: `in` atau `masuk` untuk Masuk; `out` atau `pulang` untuk Pulang. |
| `attendance_date` | date | Opsional | Tanggal presensi format `YYYY-MM-DD`. Jika dikosongkan, default ke tanggal hari ini. |
| `time` | string | Opsional | Jam presensi format `HH:MM:SS` atau `HH:MM`. Jika dikosongkan, default ke jam saat ini. |
| `timestamp` | datetime | Opsional | Format ISO8601 (misal `2026-11-23T07:55:00+08:00`). Mengisi tanggal & jam sekaligus. |
| `notes` | string | Opsional | Catatan sumber mesin/lokasi perangkat (maksimal 500 karakter). |

---

## 4. Contoh Respons

### Berhasil Dicatat (200 OK):
```json
{
  "success": true,
  "message": "Absen masuk berhasil dicatat secara real-time.",
  "data": {
    "employee_code": "DEMO-KRY-001",
    "employee_name": "Ahmad Fauzi",
    "attendance_date": "2026-11-23",
    "check_in": "07:55:00",
    "check_out": null,
    "status": "Hadir",
    "time_recorded": "07:55:00"
  }
}
```

### Status Keterlambatan:
Sistem SIM-LJI secara otomatis menghitung status:
- Jika jam masuk <= jam mulai kantor + toleransi keterlambatan (dari Pengaturan SIM-LJI, default 08:00 + 60 menit = 09:00), status otomatis **`Hadir`**.
- Jika jam masuk > batas toleransi, status otomatis menjadi **`Terlambat`**.

### Contoh Error Validasi (422 Unprocessable Entity):
```json
{
  "success": false,
  "message": "Data presensi tidak valid.",
  "errors": {
    "type": ["The selected type is invalid."]
  }
}
```

### Contoh Unauthorized (401 Unauthorized):
```json
{
  "success": false,
  "message": "Unauthorized: API Key tidak valid atau tidak disertakan pada header X-API-KEY."
}
```

---

## 5. Contoh Pemanggilan cURL

```bash
curl -X POST "https://sim.linyjaya.co.id/api/v1/attendance/record" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -H "X-API-KEY: <kunci-yang-sama-dengan-konfigurasi-server>" \
     -d '{
       "employee_code": "DEMO-KRY-001",
       "type": "in",
       "time": "07:55:00",
       "notes": "Mesin Fingerprint Lobby"
     }'
```

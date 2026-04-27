# Attendance Load Test Baseline

Script ini dipakai untuk baseline performa endpoint absensi sebelum go-live.

## File

- `attendance-load-test.mjs`
- `tokens.example.json`
- `generate-student-tokens.php`
- `generate-admin-token.php`

## Prasyarat

1. Backend aktif (`php artisan serve` / nginx).
2. Data test user siswa + token akses sudah tersedia.
3. Gunakan **staging**, bukan production.

## Generate token siswa otomatis (disarankan)

PowerShell:

```powershell
cd backend-api
php scripts/load-test/generate-student-tokens.php 150 scripts/load-test/tokens.generated.json 10
```

Parameter:
1. jumlah token siswa yang dibuat (default `100`)
2. path output json token (default `scripts/load-test/tokens.generated.json`)
3. default accuracy meter (default `10`)

## Generate token admin (opsional untuk cek health-check queue)

```powershell
cd backend-api
php scripts/load-test/generate-admin-token.php scripts/load-test/admin-token.generated.json
```

## Format token

Lihat `tokens.example.json`.

Setiap item bisa:

1. String token saja.
2. Object:
   - `token` (wajib)
   - `latitude`, `longitude`, `accuracy`, `lokasi_id` (opsional, override default env)

## Mode uji

1. `validate_time` (disarankan baseline awal)
   - Endpoint: `POST /api/simple-attendance/validate-time`
   - Tidak menulis data absensi, aman untuk pengukuran API gate awal.
2. `submit`
   - Endpoint: `POST /api/simple-attendance/submit`
   - Menulis absensi nyata (ada side effect DB + storage foto).

## Contoh eksekusi

### A. Baseline aman (tanpa side effect submit)

```bash
cd backend-api
MODE=validate_time CONCURRENCY=100 DURATION_SECONDS=60 \
TOKENS_FILE=./scripts/load-test/tokens.example.json \
node scripts/load-test/attendance-load-test.mjs
```

PowerShell:

```powershell
cd backend-api
$env:TOKENS_FILE="c:\laragon\www\absen-jadi\backend-api\scripts\load-test\tokens.example.json"
$env:BASE_URL="http://localhost:8000/api"
$env:MODE="validate_time"
$env:ATTENDANCE_TYPE="masuk"
$env:CONCURRENCY="100"
$env:DURATION_SECONDS="60"
$env:TIMEOUT_MS="15000"
$env:REPORT_FILE="c:\laragon\www\absen-jadi\backend-api\storage\logs\attendance-load-test-report.json"
node scripts/load-test/attendance-load-test.mjs
```

Jika sudah generate token otomatis, gunakan:

```powershell
$env:TOKENS_FILE="c:\laragon\www\absen-jadi\backend-api\scripts\load-test\tokens.generated.json"
```

### B. Uji submit nyata (staging)

```powershell
cd backend-api
$env:TOKENS_FILE="c:\laragon\www\absen-jadi\backend-api\scripts\load-test\tokens.example.json"
$env:BASE_URL="http://localhost:8000/api"
$env:MODE="submit"
$env:ATTENDANCE_TYPE="masuk"
$env:CONCURRENCY="80"
$env:DURATION_SECONDS="45"
$env:TIMEOUT_MS="15000"
node scripts/load-test/attendance-load-test.mjs
```

## Environment variable yang didukung

- `BASE_URL` default: `http://localhost:8000/api`
- `MODE` default: `validate_time` (`validate_time|submit`)
- `ATTENDANCE_TYPE` default: `masuk` (`masuk|pulang`)
- `TOKENS_FILE` default: `scripts/load-test/tokens.example.json`
- `CONCURRENCY` default: `50`
- `DURATION_SECONDS` default: `60`
- `TIMEOUT_MS` default: `15000`
- `LATITUDE` default: `-6.75`
- `LONGITUDE` default: `108.55`
- `ACCURACY` default: `10`
- `LOKASI_ID` default: tidak dikirim
- `REPORT_FILE` default: kosong (tidak simpan file)

## Interpretasi hasil cepat

Target awal (baseline lokal/staging):

1. `successRatePercent >= 98`
2. `latencyMs.p95 <= 1200`
3. `latencyMs.p99 <= 2000`
4. `transportErrors` = kosong

Jika `MODE=submit` dan banyak `DUPLICATE_ATTENDANCE`, itu normal jika token user yang sama men-submit berulang.
Untuk simulasi real 1600 siswa serentak, gunakan token unik per siswa.

# Attendance Anti-Fraud Production Checklist

Checklist ini dipakai untuk rollout anti-fraud SIAPS secara bertahap tanpa memutus layanan absensi utama.

## 1. Pra-Deploy

- Pastikan migration fraud monitoring sudah tersedia di branch deploy.
- Pastikan backend yang aktif sudah memuat endpoint:
  - `/api/simple-attendance/fraud-assessments`
  - `/api/simple-attendance/fraud-assessments/summary`
  - `/api/monitoring-kelas/kelas/{id}/fraud-assessments`
  - `/api/monitoring-kelas/kelas/{id}/fraud-assessments/summary`
- Pastikan mobile Android build terbaru sudah mengirim `request_nonce`, `request_timestamp`, `anti_fraud_payload`, dan sinyal native `siaps/device_security`.
- Tentukan PIC harian:
  - Admin pusat untuk fraud summary global
  - Wali kelas untuk tindak lanjut siswa kelasnya
  - Wakasek kesiswaan untuk monitoring kelas lintas wali

## 2. Konfigurasi Environment

- Mulai dari `backend-api/.env.attendance-fraud.example`.
- Set minimal variabel berikut di `backend-api/.env`:
  - `ATTENDANCE_SECURITY_EVENT_LOGGING_ENABLED=true`
  - `ATTENDANCE_SECURITY_ROLLOUT_MODE=warning_mode`
  - `ATTENDANCE_SECURITY_WARN_USER=true`
  - `ATTENDANCE_SECURITY_STORE_RAW_PAYLOAD=true`
  - `ATTENDANCE_SECURITY_EXPECTED_ANDROID_PACKAGE=id.sch.sman1sumbercirebon.siaps`
  - `ATTENDANCE_SECURITY_ALLOWED_INSTALLERS=com.android.vending,com.google.android.packageinstaller,com.miui.packageinstaller`
- Jangan aktifkan `ATTENDANCE_SECURITY_REQUEST_SIGNING_ENABLED=true` sebelum mobile client benar-benar mengirim signature request.
- Jika sekolah punya Wi-Fi resmi untuk absensi, isi:
  - `ATTENDANCE_SECURITY_TRUSTED_WIFI_SSIDS`
  - `ATTENDANCE_SECURITY_TRUSTED_WIFI_BSSIDS`

## 3. Deploy Backend

- Jalankan migration di server produksi.
- Deploy backend lebih dulu.
- Bersihkan cache konfigurasi setelah update env:
  - `php artisan config:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
- Verifikasi endpoint:
  - `GET /api/health-check`
  - `GET /api/simple-attendance/fraud-assessments/summary`

## 4. Deploy Mobile

- Generate Android release build yang memakai `MainActivity.kt` dengan channel `siaps/device_security`.
- Pastikan package name final sama dengan `ATTENDANCE_SECURITY_EXPECTED_ANDROID_PACKAGE`.
- Distribusikan build ke user pilot lebih dulu, jangan langsung ke seluruh sekolah.
- Validasi manual dari 1 device normal dan 1 device berisiko:
  - Device normal harus tetap bisa submit absensi
  - Device dengan developer options / ADB / root / clone / instrumentation harus menghasilkan flag yang terbaca di backend

## 5. Validasi Fungsional

- Submit absensi masuk dan pulang dari mobile Android biasa.
- Cek bahwa record fraud assessment tercatat.
- Buka UI monitoring kelas dari akun wali kelas.
- Buka UI monitoring kelas dari akun wakasek kesiswaan.
- Pastikan bagian `Fraud Monitoring` tampil di layar detail kelas dan menampilkan:
  - total assessment
  - rejected
  - manual review
  - high risk
  - top flags
  - siswa tindak lanjut
  - assessment terbaru

## 6. Operasional Minggu Pertama

- Pertahankan `ATTENDANCE_SECURITY_ROLLOUT_MODE=warning_mode`.
- Review dashboard fraud minimal 2 kali sehari:
  - pagi setelah jam masuk
  - sore setelah jam pulang
- Catat false positive:
  - GPS akurasi buruk
  - package installer vendor tertentu
  - device sekolah yang memang memakai debug build internal
- Jika warning terlalu banyak, sesuaikan score pada signal yang paling bising lebih dulu, jangan langsung menonaktifkan semua proteksi.

## 7. Kriteria Naik ke Strict Mode

- Warning palsu sudah turun ke level yang bisa ditangani.
- Top flags didominasi sinyal high-confidence, bukan noise GPS biasa.
- Wali kelas dan wakasek sudah punya ritme tindak lanjut.
- Komplain dari pilot users sudah dipetakan.
- Hanya setelah itu ubah:
  - `ATTENDANCE_SECURITY_ROLLOUT_MODE=strict_mode`
  - opsional `ATTENDANCE_SECURITY_STORE_RAW_PAYLOAD=false` jika kebutuhan audit awal sudah selesai

## 8. Rollback Plan

- Jika false positive melonjak, ubah cepat ke:
  - `ATTENDANCE_SECURITY_ROLLOUT_MODE=logging_only`
  - atau `ATTENDANCE_SECURITY_ROLLOUT_MODE=warning_mode`
- Jangan rollback database kecuali ada masalah migration.
- Simpan sample fraud assessments yang bermasalah sebelum menurunkan mode rollout.

## 9. Bukti Go-Live

- Simpan snapshot nilai env final.
- Simpan nomor versi APK yang dideploy.
- Simpan hasil uji dari minimal:
  - 1 akun admin
  - 1 akun wali kelas
  - 1 akun wakasek
  - 2 akun siswa

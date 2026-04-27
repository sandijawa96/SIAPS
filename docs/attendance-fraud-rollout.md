# Attendance Anti-Fraud Rollout

Dokumen ini menjelaskan cara mengaktifkan proteksi anti-fraud absensi secara bertahap tanpa merusak flow absensi utama.

## Tujuan

Lapisan anti-fraud ini tidak mengganti status absensi utama seperti `hadir`, `terlambat`, `izin`, `sakit`, atau `alpha`.
Sistem hanya menambahkan evaluasi keamanan terpisah dengan keluaran:

- `validation_status`: `valid`, `warning`, `manual_review`, `rejected`
- `risk_level`: `low`, `medium`, `high`, `critical`
- `risk_score`: skor numerik kumulatif
- `fraud_flags_count`: jumlah sinyal yang terdeteksi

## Cakupan Yang Sudah Didukung

Backend saat ini sudah mengevaluasi sinyal berikut:

- Fake GPS dan mock provider
- Developer options
- Akurasi GPS rendah
- Di luar geofence
- Lokasi stale
- Drift waktu device vs server
- Emulator
- Root atau jailbreak
- ADB atau USB debugging
- Device spoofing
- App clone
- App tampering
- Instrumentation / hooking risk
- Signature mismatch
- Replay request
- Duplicate request frequency
- Forged metadata
- Impossible travel
- Duplicate coordinate pattern
- Suspicious network
- Pelanggaran kebijakan mobile-only

Catatan penting:

- Backend sudah siap menerima sinyal root, instrumentation, clone app, installer source, dan sejenisnya.
- Mobile app Android sekarang sudah otomatis mengirim sinyal native tambahan seperti `root_detected`, `magisk_risk`, `app_clone_risk`, `instrumentation_detected`, `frida_detected`, `xposed_detected`, `debugger_connected`, `installer_source`, dan `signature_sha256`.
- Mobile app juga sudah menampilkan fraud monitoring pada layar detail monitoring kelas untuk role wali kelas dan wakasek kesiswaan.
- Dukungan iOS masih fallback ke metadata dasar. Jika target iOS akan dipakai untuk absensi produksi, detector native iOS masih perlu ditambahkan.

## Endpoint Yang Tersedia

Admin pusat:

- `GET /api/simple-attendance/fraud-assessments`
- `GET /api/simple-attendance/fraud-assessments/summary`
- `GET /api/simple-attendance/fraud-assessments/{assessment}`

Wali kelas:

- `GET /api/wali-kelas/kelas/{id}/fraud-assessments`
- `GET /api/wali-kelas/kelas/{id}/fraud-assessments/summary`
- `GET /api/wali-kelas/kelas/{id}/fraud-assessments/{assessment}`

Monitoring wali kelas dan wakasek kesiswaan:

- `GET /api/monitoring-kelas/kelas/{id}/fraud-assessments`
- `GET /api/monitoring-kelas/kelas/{id}/fraud-assessments/summary`
- `GET /api/monitoring-kelas/kelas/{id}/fraud-assessments/{assessment}`

## Mode Rollout

### 1. `logging_only`

Efek:

- Semua sinyal dicatat
- Absensi tetap lanjut
- Tidak cocok untuk jangka panjang

Gunakan saat:

- Baru pertama kali deploy
- Ingin cek kualitas data sinyal mobile
- Tim sekolah belum siap menindaklanjuti warning

### 2. `warning_mode`

Efek:

- Absensi tetap bisa masuk untuk mayoritas kasus
- Hasil assessment muncul sebagai `warning` atau `manual_review`
- Admin, wali kelas, dan wakasek bisa mulai evaluasi siswa berisiko

Gunakan saat:

- Tahap awal produksi
- Sekolah ingin edukasi siswa dulu
- Ingin membangun kebiasaan monitoring sebelum ada blok keras

Rekomendasi default produksi awal:

- Pakai `warning_mode` minimal 1 sampai 2 minggu
- Audit 10 besar fraud flag yang paling sering muncul
- Naikkan threshold jika terlalu sensitif
- Tambah detector mobile bila banyak kasus lolos tanpa bukti cukup
- `warning_mode` tidak menonaktifkan policy device binding. Kasus `device_lock_violation` dan `device_id_required` tetap hard block karena 1 device dipakai 1 siswa

### 3. `strict_mode`

Efek:

- Kombinasi sinyal berisiko tinggi bisa memblok absensi
- Assessment `rejected` akan menghentikan submit
- Cocok hanya jika kualitas sinyal sudah stabil

Gunakan saat:

- Data `warning_mode` sudah cukup bersih
- Sekolah siap menangani komplain
- Detector mobile sudah lebih lengkap

## Konfigurasi Penting

Atur di environment backend:

```env
ATTENDANCE_SECURITY_ROLLOUT_MODE=warning_mode
ATTENDANCE_SECURITY_WARN_USER=true
ATTENDANCE_SECURITY_STORE_RAW_PAYLOAD=true

ATTENDANCE_SECURITY_WARNING_SCORE=25
ATTENDANCE_SECURITY_MANUAL_REVIEW_SCORE=50
ATTENDANCE_SECURITY_REJECT_SCORE=80
ATTENDANCE_SECURITY_CRITICAL_SCORE=100

ATTENDANCE_SECURITY_REQUEST_SIGNING_ENABLED=false
ATTENDANCE_SECURITY_EXPECTED_ANDROID_PACKAGE=com.example.mobileapp
ATTENDANCE_SECURITY_ALLOWED_INSTALLERS=com.android.vending,com.google.android.packageinstaller,com.miui.packageinstaller
```

Jika request signing akan dipakai:

```env
ATTENDANCE_SECURITY_REQUEST_SIGNING_ENABLED=true
ATTENDANCE_SECURITY_REQUEST_SIGNING_KEY=isi-kunci-rahasia-yang-kuat
ATTENDANCE_SECURITY_REQUEST_SIGNING_MAX_AGE_SECONDS=180
```

## Langkah Deploy Aman

1. Jalankan migration fraud monitoring.
2. Deploy backend lebih dulu.
3. Set `ATTENDANCE_SECURITY_ROLLOUT_MODE=logging_only` atau `warning_mode`.
4. Deploy mobile app yang sudah mengirim `request_nonce`, `request_timestamp`, dan `anti_fraud_payload`.
   Mobile build Android terbaru juga harus sudah membawa detector native channel `siaps/device_security`.
5. Pantau endpoint summary admin selama beberapa hari.
6. Libatkan wali kelas dan wakasek untuk cek kandidat follow-up.
7. Setelah sinyal stabil, pertimbangkan `strict_mode` untuk flag berkepercayaan tinggi.

## Rekomendasi Operasional Sekolah

Setiap hari:

- Admin pusat cek ringkasan fraud global
- Wali kelas cek fraud monitoring kelasnya
- Wakasek kesiswaan cek kelas dengan `manual_review` atau `rejected` tertinggi

Setiap minggu:

- Evaluasi `top_flags`
- Identifikasi siswa berulang
- Cocokkan dengan histori izin, lokasi, dan pola keterlambatan

Tindak lanjut yang disarankan:

- `warning`: edukasi siswa
- `manual_review`: panggil siswa dan verifikasi kronologi
- `rejected`: minta klarifikasi dan validasi manual oleh petugas sekolah

## Dampak Jika Diimplementasikan

Keuntungan:

- Sekolah punya audit trail nyata, bukan asumsi
- Wali kelas bisa melihat siswa yang perlu dipanggil
- Wakasek punya data untuk pembinaan disiplin
- Admin bisa mengukur tren kecurangan per kelas dan per siswa
- Rollout bisa bertahap tanpa memblok semua absensi sejak hari pertama

Risiko yang tetap ada:

- Jika mobile detector belum lengkap, sebagian tampering tingkat lanjut bisa hanya terdeteksi sebagian
- Jika threshold terlalu rendah, warning bisa terlalu banyak
- Jika langsung `strict_mode`, komplain pengguna bisa naik tajam

## Dampak Jika Tidak Diimplementasikan

Yang kemungkinan terjadi:

- Fake GPS dan spoofing hanya tertangani sebagian
- Tidak ada jejak audit yang konsisten untuk evaluasi sekolah
- Wali kelas dan wakasek sulit membedakan kesalahan biasa dengan kecurangan terstruktur
- Sekolah baru bereaksi setelah pola penyalahgunaan sudah meluas

## Rekomendasi Final

Untuk kondisi SIAPS saat ini:

1. Aktifkan backend fraud monitoring sekarang dalam `warning_mode`.
2. Gunakan laporan kelas untuk evaluasi oleh wali kelas dan wakasek.
3. Gunakan mobile build Android yang sudah membawa detector native root, clone app, instrumentation, dan app integrity dasar.
4. Tambahkan detector native iOS jika absensi iOS akan ikut masuk cakupan produksi.
5. Setelah kualitas sinyal matang, aktifkan `strict_mode` hanya untuk sinyal dengan confidence tinggi seperti `mock_location`, `request_replay`, `device_spoofing`, dan `instrumentation`.

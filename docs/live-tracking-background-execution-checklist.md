# Live Tracking Background Execution Checklist

Checklist ini adalah breakdown eksekusi dari `docs/live-tracking-background-migration-plan.md`.

## Guard Utama

- [x] `backend-api/app/Http/Controllers/Api/SimpleAttendanceController.php` tidak diubah.
- [x] Endpoint absensi tidak diubah.
- [x] Payload absensi tidak diubah.
- [x] Semua perubahan baru dipasang dengan feature flag default nonaktif.

## Fase 1 - Checklist Eksekusi Aman

### A. Dokumen dan Scope

- [x] Buat dokumen migration plan
- [x] Pecah migration plan menjadi checklist eksekusi
- [x] Tetapkan boundary bahwa flow absensi tidak boleh berubah

### B. Scaffold Mobile

- [x] Tambah dependency `flutter_background_service`
- [x] Tambah feature flag compile-time `ENABLE_BACKGROUND_LIVE_TRACKING` dengan default `false`
- [x] Buat file `mobileapp/lib/services/live_tracking_background_service.dart`
- [x] Bootstrap background service di `mobileapp/lib/main.dart`

### C. Integrasi Aman

- [x] Integrasi wrapper ke `MainDashboard` dengan fallback ke jalur lama
- [x] Integrasi stop tracking ke `AuthService.logout()` / clear auth
- [x] Tambah permission dan service declaration Android
- [x] Tambah alur UI untuk request background location bila feature diaktifkan

### D. Verifikasi

- [x] Jalankan `flutter pub get`
- [x] Jalankan analisis statis
- [x] Verifikasi build Android debug
- [x] Verifikasi absensi tetap memakai jalur lama saat flag off

## Fase 2 - Aktivasi Bertahap

- [x] Build internal testing dengan `--dart-define=ENABLE_BACKGROUND_LIVE_TRACKING=true`
- [x] Uji login siswa
- [x] Uji tracking saat app di-home-kan
- [x] Uji logout menghentikan tracking
- [x] Uji absensi masuk tetap normal
- [x] Uji absensi pulang tetap normal

## Kriteria Lulus Fase 1

- [x] Aplikasi build tanpa error
- [x] Tidak ada perubahan perilaku absensi saat feature flag off
- [x] Tidak ada crash startup
- [x] Tidak ada error logout/login karena stop tracking

## Catatan Hasil Eksekusi

- [x] `D:\flutter\bin\flutter.bat pub get` sukses
- [x] `D:\flutter\bin\flutter.bat analyze --no-fatal-infos --no-fatal-warnings ...` sukses untuk file yang diubah
- [x] `D:\flutter\bin\flutter.bat build apk --debug` sukses
- [x] `D:\flutter\bin\flutter.bat build apk --debug --dart-define=ENABLE_BACKGROUND_LIVE_TRACKING=true` sukses
- [x] Feature flag background tracking default tetap `false`, jadi flow absensi masih memakai jalur lama
- [x] `backend-api/app/Http/Controllers/Api/SimpleAttendanceController.php` tetap tidak diubah
- [x] Jalur `openAppSettings()` permission service sudah diperbaiki
- [x] Engine live tracking sudah berpindah dari `Timer.periodic` ke stream lokasi native yang ditrottle
- [x] Dialog permission background kini memuat guidance vendor Android agresif
- [x] Uji runtime device Android nyata
- [x] Uji absensi masuk/pulang end-to-end di device nyata

## Fase 3 - Android Compatibility Hardening

- [x] Audit ulang server, backend, web, dan mobile dengan fokus Android 10-14
- [x] Buat dokumen compatibility hardening plan Android
- [x] Refactor engine live tracking dari `Timer.periodic` ke stream lokasi
- [x] Tambah auto-recovery bila stream tracking terputus
- [x] Tambah guidance vendor pada dialog background permission
- [x] Uji matrix P0: Xiaomi / MIUI
- [ ] Uji matrix P0: Samsung / One UI
- [ ] Uji matrix P1: Oppo / Realme
- [ ] Uji matrix P1: Vivo
- [ ] Validasi tidak ada regresi absensi setelah refactor stream-based

## Fase 4 - Noise & Request Cleanup

- [x] Dedupe registrasi `POST /device-tokens/register`
- [x] Hapus retry registrasi push yang dobel di `AuthService`
- [x] Hapus registrasi push duplikat dari `MainDashboard`
- [x] Kurangi fetch duplikat `/academic-context/current` dengan in-flight cache
- [x] Kurangi fetch duplikat `/simple-attendance/working-hours` dengan short-lived cache
- [x] Kurangi fetch duplikat attendance policy dengan short-lived cache
- [x] Kurangi fetch duplikat attendance info dengan short-lived cache
- [x] Hindari refresh `/profile` yang tidak perlu dari `ClassProvider`
- [x] Bersihkan debug log mojibake agar log runtime bisa dibaca normal
- [x] Hapus dependency `location` yang tidak dipakai dari `mobileapp/pubspec.yaml`
- [x] Pasang override lokal `flutter_background_service_android` untuk mencegah registrasi plugin UI melempar exception di background isolate
- [x] Verifikasi di device bahwa warning `This class should only be used in the main isolate (UI App)` benar-benar hilang
- [x] Jalankan `flutter pub get` setelah cleanup dependency
- [x] Jalankan `flutter analyze --no-fatal-infos --no-fatal-warnings`
- [x] Verifikasi `flutter build apk --debug --dart-define=ENABLE_BACKGROUND_LIVE_TRACKING=true`

## Fase 5 - GPS Disabled State Handling

- [x] Tambah endpoint backend untuk menerima state tracking tanpa koordinat
- [x] Tambah status snapshot `gps_disabled` di service realtime backend
- [x] Pastikan snapshot `gps_disabled` tidak dipersist sebagai history titik lokasi
- [x] Mobile kirim state `gps_disabled` saat layanan lokasi device dimatikan
- [x] Mobile tidak spam state yang sama berulang kali
- [x] Tambah backoff recovery saat GPS tetap mati untuk mengurangi wakeup tidak perlu
- [x] Saat GPS kembali aktif dan `update-location` sukses, snapshot kembali menjadi `active`/`outside_area`
- [x] Frontend live tracking tampilkan status `GPS Mati` di list siswa
- [x] Frontend live tracking tampilkan status `GPS Mati` di filter
- [x] Frontend live tracking tampilkan status `GPS Mati` di statistik
- [x] Frontend live tracking tampilkan status `GPS Mati` di peta/marker/legenda
- [x] Dashboard mempercepat polling sementara saat ada siswa berstatus `gps_disabled`
- [x] Jalankan `php -l` untuk file backend yang diubah
- [x] Jalankan `flutter analyze --no-fatal-infos --no-fatal-warnings lib/services/live_tracking_service.dart lib/services/location_service.dart`
- [x] Jalankan `npm.cmd run build` di `frontend`
- [x] Jalankan `flutter build apk --debug --dart-define=ENABLE_BACKGROUND_LIVE_TRACKING=true`

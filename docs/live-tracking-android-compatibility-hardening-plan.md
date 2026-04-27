# Live Tracking Android Compatibility Hardening Plan

## Posisi Teknis

- Target realistis: stabil untuk Android 10 sampai Android 14 pada vendor prioritas.
- Target tidak realistis: perilaku identik di semua device Android tanpa pengecualian.
- Boundary utama: flow absensi tetap tidak disentuh.

## Boundary Yang Tetap Dijaga

- `backend-api/app/Http/Controllers/Api/SimpleAttendanceController.php` tetap untouched.
- Endpoint absensi tetap untouched.
- Payload absensi tetap untouched.
- Endpoint live tracking tetap `POST /lokasi-gps/update-location`.
- Backend tetap source of truth untuk jam sekolah dan status `stale`.

## Audit Singkat Kondisi Saat Ini

1. Server dan backend sudah cukup untuk baseline operasional.
   - Server: `docs/spek-server.md:3`, `docs/spek-server.md:14`, `docs/spek-server.md:15`, `docs/spek-server.md:23`, `docs/spek-server.md:25`
   - Redis untuk queue dan cache: `backend-api/.env:53`, `backend-api/.env:55`
   - Status `stale` ditentukan backend: `backend-api/config/attendance.php:60`, `backend-api/app/Services/LiveTrackingSnapshotService.php:108`

2. Web live tracking sudah near-realtime via polling.
   - Default 60 detik: `frontend/src/hooks/useTracking.jsx:824`
   - Minimum 30 detik: `frontend/src/hooks/useTracking.jsx:894`

3. Bottleneck utama ada di mobile.
   - Feature flag background tracking: `mobileapp/lib/utils/constants.dart:27`
   - Background service wrapper: `mobileapp/lib/services/live_tracking_background_service.dart:20`
   - Permission background location dipisah dari permission absensi biasa: `mobileapp/lib/services/permission_service_final.dart:124`, `mobileapp/lib/services/permission_service_final.dart:159`
   - Startup permission checker belum meminta background location, dan itu memang sengaja: `mobileapp/lib/services/permission_service_final.dart:342`, `mobileapp/lib/widgets/permission_checker_widget.dart:43`

## Target Hardening

1. Tracking engine tidak lagi bergantung pada `Timer.periodic`.
2. Live tracking memakai stream lokasi native dengan throttle kirim 30 detik.
3. Background service tetap menjadi owner lifecycle tracking.
4. UI memberi guidance vendor untuk OEM agresif.
5. Compatibility test dilakukan per vendor, bukan asumsi satu build cocok untuk semua.

## Perubahan Implementasi Yang Sudah Dipasang

1. `LocationService` sekarang punya stream khusus live tracking.
   - `mobileapp/lib/services/location_service.dart`
   - Menambahkan `getTrackingLocationStream(...)`
   - Kontrak `getCurrentLocation()` untuk absensi tetap dipertahankan

2. `LiveTrackingService` dipindah ke model stream-based.
   - `mobileapp/lib/services/live_tracking_service.dart`
   - Engine lama `Timer.periodic` diganti subscription stream + throttle + auto-recovery

3. Dialog background permission sekarang memberi guidance vendor.
   - `mobileapp/lib/screens/main_dashboard.dart:357`

## Device Matrix Yang Harus Dianggap Resmi

| Prioritas | Vendor / OS | Status target | Catatan |
|---|---|---|---|
| P0 | Xiaomi / MIUI, Android 13-14 | wajib lolos | paling agresif membunuh background task |
| P0 | Samsung / One UI, Android 13-14 | wajib lolos | representatif user Android mainstream |
| P1 | Oppo / Realme, Android 12-14 | wajib lolos | sering butuh autostart dan no restrictions |
| P1 | Vivo, Android 12-14 | wajib lolos | sering punya background policy sendiri |
| P2 | Pixel / Android stock, Android 13-14 | referensi baseline | patokan perilaku Android paling bersih |
| P2 | Android 10-11 perangkat lama | kompatibilitas minimum | fokus permission flow dan foreground service |

## Checklist Uji Per Device

1. Login siswa sukses.
2. Background location benar-benar `Allow all the time`.
3. Foreground notification live tracking muncul.
4. App di-home-kan 5 sampai 10 menit.
5. Log tetap menunjukkan `POST /lokasi-gps/update-location`.
6. Dashboard web tidak berubah `stale`.
7. Logout menghentikan request live tracking.
8. Login ulang tidak mewarisi session lama.
9. Absen masuk tetap normal.
10. Absen pulang tetap normal.

## OEM Guidance Minimum

### Xiaomi / MIUI

1. Location: `Allow all the time`
2. Battery: `No restrictions`
3. Autostart: `On`
4. Lock app di recent apps
5. Notifications: `On`

### Oppo / Realme / Vivo

1. Location: `Allow all the time`
2. Battery optimization: nonaktif untuk app
3. Auto launch / startup manager: aktif
4. Notifications: aktif

### Samsung

1. Location: `Allow all the time`
2. Battery: hapus dari sleeping apps
3. Notifications: aktif

## Risiko Yang Masih Tersisa

1. Vendor policy masih bisa membunuh background service walau permission lengkap.
2. Device yang tidak bergerak lama bisa mengubah frekuensi update actual tergantung provider GPS.
3. `location` package masih ada di dependency walau jalur aktif memakai `geolocator`.
4. Uji lapangan masih wajib dilakukan sebelum mengaktifkan default production.

## Langkah Berikutnya Yang Disarankan

1. Uji device nyata pada matrix P0 terlebih dahulu.
2. Jika lolos, lanjutkan ke matrix P1.
3. Setelah matrix lolos, baru pertimbangkan mengaktifkan build production default.
4. Bersihkan dependency `location` jika dipastikan tidak dipakai lagi.

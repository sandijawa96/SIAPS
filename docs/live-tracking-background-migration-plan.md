# Live Tracking Background Migration Plan

## Ringkasan Keputusan

- Keputusan: migrasi live tracking dari model lama `foreground polling` ke `flutter_background_service + geolocator`.
- Scope fase 1: Android-first.
- Scope fase 1 tidak mengubah flow absensi masuk/pulang.
- Backend live tracking tetap dipakai apa adanya.
- Backend absensi tidak disentuh pada fase ini.

## Kondisi Saat Ini

Live tracking yang aktif sekarang:

1. `mobileapp/lib/services/live_tracking_service.dart`
   - Engine loop tracking.
   - Interval 30 detik dengan `Timer.periodic`.
2. `mobileapp/lib/services/location_service.dart`
   - Ambil GPS memakai `geolocator`.
3. `mobileapp/lib/screens/main_dashboard.dart`
   - Dashboard menghidupkan dan mematikan tracking.
   - Saat app `paused/inactive/detached`, tracking dihentikan.
4. `backend-api/routes/api.php`
   - Endpoint POST `/lokasi-gps/update-location`.
5. `backend-api/app/Http/Controllers/Api/LokasiGpsController.php`
   - Menerima snapshot lokasi realtime.

Kesimpulan kondisi saat ini:

- Tracking saat ini bukan true background tracking.
- Tracking saat ini berhenti ketika app masuk background.
- Dependency `location` ada di `mobileapp/pubspec.yaml`, tetapi bukan jalur live tracking yang aktif.

## Target Arsitektur

Target arsitektur fase 1:

1. `MainDashboard` tidak lagi menjadi owner tracking.
2. `flutter_background_service` menjadi owner lifecycle tracking.
3. `LiveTrackingService` tetap dipakai sebagai engine pengambilan lokasi dan pengiriman payload.
4. `LocationService` tetap memakai `geolocator`.
5. Endpoint backend tetap `/lokasi-gps/update-location`.
6. Auth tetap memakai token yang sudah tersimpan di `FlutterSecureStorage`.

Arsitektur target:

`background service -> live_tracking_service -> location_service(geolocator) -> /lokasi-gps/update-location`

## Tujuan

1. Tracking tetap berjalan ketika aplikasi di-background-kan di Android.
2. Tidak merusak absensi masuk.
3. Tidak merusak absensi pulang.
4. Tidak mengubah payload absensi.
5. Tidak mengubah kontrak endpoint absensi.
6. Tetap memakai backend live tracking yang sudah ada.

## Non-Goal Fase 1

1. Tidak mengubah `backend-api/app/Http/Controllers/Api/SimpleAttendanceController.php`.
2. Tidak mengubah face verification.
3. Tidak mengubah device binding absensi.
4. Tidak mengubah validasi GPS absensi.
5. Tidak mengubah mekanisme upload foto absensi.
6. Tidak mengejar parity iOS penuh pada fase 1.

## Daftar File Yang Akan Diubah

### Core Required

1. `mobileapp/pubspec.yaml`
   - Tambah dependency `flutter_background_service`.
   - Jika diperlukan oleh versi package, tambahkan package Android companion yang relevan.

2. `mobileapp/lib/main.dart`
   - Inisialisasi background service saat bootstrap aplikasi.

3. `mobileapp/lib/services/live_tracking_service.dart`
   - Jadikan service ini aman dipanggil dari background service.
   - Pastikan idempotent, tidak double loop, dan tidak bergantung pada widget lifecycle.

4. `mobileapp/lib/services/location_service.dart`
   - Hanya jika diperlukan untuk membuat pembacaan GPS aman dipakai dari background isolate.
   - Perubahan harus additive, bukan mengubah kontrak method yang dipakai absensi.

5. `mobileapp/lib/screens/main_dashboard.dart`
   - Ubah ownership tracking.
   - Dashboard hanya mengirim command start/stop/sync, bukan lagi engine tracking.

6. `mobileapp/lib/services/auth_service.dart`
   - Saat logout, kirim stop command ke background service sebelum/bersamaan dengan clear auth data.

7. `mobileapp/lib/services/permission_service_final.dart`
   - Tambahkan alur permission background location Android yang benar.

8. `mobileapp/android/app/src/main/AndroidManifest.xml`
   - Tambah permission foreground service Android.
   - Tambah permission foreground location service Android.
   - Verifikasi permission background location tetap benar.

### New File

1. `mobileapp/lib/services/live_tracking_background_service.dart`
   - Wrapper untuk start, stop, bootstrap, dan komunikasi dengan background service.

### Optional But Recommended

1. `mobileapp/lib/utils/constants.dart`
   - Tambahkan feature flag rollout aman berbasis compile-time, misalnya `ENABLE_BACKGROUND_LIVE_TRACKING`.

2. `mobileapp/lib/providers/auth_provider.dart`
   - Jika ingin boundary logout lebih eksplisit di layer provider.

### Deferred / Conditional

1. `mobileapp/ios/Runner/Info.plist`
   - Hanya untuk fase iOS.

2. `mobileapp/ios/Runner/AppDelegate.swift`
   - Hanya jika dibutuhkan oleh integrasi iOS.

3. `backend-api/config/attendance.php`
   - Hanya jika ingin tuning `stale_seconds` atau runtime cleanup setelah migration stabil.

4. `backend-api/app/Http/Controllers/Api/LokasiGpsController.php`
   - Hanya jika ingin menambah observability atau throttling, bukan syarat migration fase 1.

## File Yang Tidak Boleh Diubah Pada Fase 1

1. `backend-api/app/Http/Controllers/Api/SimpleAttendanceController.php`
2. `mobileapp/lib/services/attendance_service.dart`
3. Endpoint absensi mobile yang dipakai submit masuk/pulang
4. Payload absensi masuk/pulang
5. Validasi wajah dan verifikasi absensi

Jika salah satu item di atas ikut berubah, boundary migration sudah jebol dan risiko regresi absensi naik tajam.

## Boundary Yang Memastikan Flow Absensi Tetap Tidak Rusak

Boundary ini wajib dipertahankan selama implementasi:

1. `submitAttendance` tidak diubah.
2. `validateAttendanceTime` tidak diubah.
3. `LocationService.getCurrentLocation()` tetap mengembalikan kontrak yang sama untuk flow absensi.
4. Semua perubahan pada `LocationService` harus additive.
5. Endpoint live tracking tetap `/lokasi-gps/update-location`.
6. Endpoint absensi tidak berubah.
7. Jika background service gagal start, absensi manual tetap harus bisa berjalan normal.
8. Logout wajib menghentikan tracking agar sesi lama tidak terus mengirim lokasi.
9. Hanya boleh ada satu tracking session aktif per user login.
10. Tracking realtime tidak boleh menjadi dependency wajib bagi absensi masuk/pulang.

## Urutan Migrasi Aman

### Fase 0 - Freeze Kontrak

Tujuan:

- Bekukan kontrak absensi dan live tracking sebelum migration.

Langkah:

1. Catat baseline perilaku tracking lama.
2. Catat baseline flow absensi masuk.
3. Catat baseline flow absensi pulang.
4. Pastikan endpoint `/lokasi-gps/update-location` tidak diubah.
5. Pastikan `SimpleAttendanceController` tidak masuk scope perubahan.

Exit criteria:

1. Tim sepakat bahwa migration hanya menyasar mobile live tracking.

### Fase 1 - Tambah Background Scaffolding Tanpa Mengambil Ownership

Tujuan:

- Menambahkan fondasi background service tanpa langsung memindahkan lifecycle tracking.

Langkah:

1. Tambah dependency background service di `mobileapp/pubspec.yaml`.
2. Buat `mobileapp/lib/services/live_tracking_background_service.dart`.
3. Tambah bootstrap background service di `mobileapp/lib/main.dart`.
4. Tambah permission Android yang dibutuhkan di `mobileapp/android/app/src/main/AndroidManifest.xml`.
5. Tambah feature flag lokal di `mobileapp/lib/utils/constants.dart`.
   - Rekomendasi: gunakan `bool.fromEnvironment(..., defaultValue: false)` agar internal testing tidak perlu mengubah source code.
6. Pastikan app masih bisa build dan login normal.

Exit criteria:

1. App boot normal.
2. Login normal.
3. Absensi belum berubah.
4. Background service bisa diinit tetapi belum mengambil ownership tracking produksi.

### Fase 2 - Pindahkan Ownership Dari Dashboard Ke Background Service

Tujuan:

- Dashboard berhenti menjadi engine tracking.

Langkah:

1. Ubah `mobileapp/lib/screens/main_dashboard.dart`.
2. Start/stop tracking dipindah ke command wrapper background service.
3. Hapus ketergantungan bahwa widget lifecycle adalah satu-satunya lifecycle tracking.
4. Pertahankan rule siswa-only.
5. Pertahankan rule user harus authenticated.

Exit criteria:

1. Tracking tetap hidup saat app di-background-kan.
2. Tidak ada double tracking loop.
3. Dashboard resume/pause tidak membuat duplicate session.

### Fase 3 - Ikat Stop Tracking Ke Logout Dan Auth Reset

Tujuan:

- Pastikan background service berhenti bersih ketika user keluar.

Langkah:

1. Ubah `mobileapp/lib/services/auth_service.dart`.
2. Saat logout, stop background tracking lebih dulu atau bersamaan.
3. Setelah stop tracking, baru clear token dan user data.
4. Pastikan auth refresh gagal juga berhenti ke logout bersih.

Exit criteria:

1. Setelah logout, tidak ada request `/lokasi-gps/update-location` yang masih terkirim.
2. Login user lain tidak mewarisi tracking session user lama.

### Fase 4 - Hardening Permission Dan Runtime Guard

Tujuan:

- Membuat background tracking stabil dan aman dipakai harian.

Langkah:

1. Ubah `mobileapp/lib/services/permission_service_final.dart`.
2. Tambahkan alur background location permission Android.
3. Pastikan foreground notification untuk service tampil benar.
4. Pastikan service tidak mengirim saat user bukan siswa.
5. Pastikan service tidak mengirim saat token tidak ada.
6. Pertahankan guard jam sekolah di mobile.
7. Pertahankan backend sebagai source of truth jam sekolah.

Exit criteria:

1. Permission flow jelas.
2. Service tidak hidup liar di luar session valid.
3. Request yang terkirim tetap lolos policy backend.

### Fase 5 - Regression Test Flow Absensi

Tujuan:

- Menjamin migration live tracking tidak merusak absensi.

Checklist:

1. Absen masuk dengan GPS berhasil.
2. Absen pulang dengan GPS berhasil.
3. Absen tetap menolak jika GPS invalid.
4. Absen tetap menolak jika foto wajib tidak ada.
5. Face verification tetap berjalan sesuai mode aktif.
6. Device binding absensi tetap berjalan.
7. Tracking background aktif tidak membuat request absensi berubah.

Exit criteria:

1. Tidak ada regresi di flow absensi.

### Fase 6 - Uji Operasional Sampai Siap Pakai

Tujuan:

- Menyatakan sistem siap dipakai operasional.

Checklist:

1. Login siswa.
2. Tracking jalan saat dashboard dibuka.
3. App di-home-kan, tracking tetap kirim lokasi.
4. App dibuka lagi, tidak muncul double loop.
5. Logout menghentikan tracking.
6. Login ulang memulai tracking session baru.
7. Token refresh tidak mematikan tracking secara liar.
8. Di luar jam sekolah, backend menolak tracking sesuai policy.
9. Monitoring dashboard tetap membaca snapshot terbaru.
10. Battery impact masih dapat diterima.

Exit criteria:

1. Semua checklist lolos.
2. Tidak ada regresi absensi.
3. Tidak ada duplicate request berat.

## Step Implementasi Sampai Sistem Siap Pakai

### Step 1

Tambahkan dependency dan feature flag.

Output:

- Background service library tersedia.
- Rollout bisa dinyalakan dan dimatikan dengan aman.

### Step 2

Buat `live_tracking_background_service.dart`.

Tanggung jawab file ini:

1. Initialize background service.
2. Start tracking command.
3. Stop tracking command.
4. Sinkronkan state auth aktif.
5. Memanggil `LiveTrackingService` sebagai worker inti.

### Step 3

Refactor `LiveTrackingService` agar isolate-safe.

Target:

1. Tidak bergantung pada widget.
2. Tidak membuat loop ganda.
3. Aman dipanggil berulang.
4. Tetap memakai payload live tracking yang sekarang.

### Step 4

Bootstrap background service di `main.dart`.

Target:

1. Service bisa diinisialisasi saat app start.
2. Tidak memblok startup.
3. Tidak merusak Firebase dan API bootstrap yang sudah ada.

### Step 5

Pindahkan command lifecycle dari `MainDashboard`.

Target:

1. Dashboard hanya sync state.
2. Dashboard bukan engine tracking lagi.

### Step 6

Ikat stop tracking ke `logout()`.

Target:

1. Saat user logout, service berhenti.
2. Session user lama tidak bocor.

### Step 7

Perbaiki permission dan manifest Android.

Target:

1. Background location legal di Android.
2. Foreground service location legal di Android.
3. Notifikasi foreground service tampil benar.

### Step 8

Lakukan regression test absensi.

Target:

1. `submitAttendance` tetap aman.
2. GPS absensi tetap valid.
3. Face verification tidak berubah.

### Step 9

Lakukan rollout bertahap.

Strategi:

1. Build internal test dengan `--dart-define=ENABLE_BACKGROUND_LIVE_TRACKING=true`.
2. Uji 1-3 device Android nyata.
3. Setelah stabil, aktifkan untuk pengguna siswa.

## Checklist Siap Pakai

Sistem dinyatakan siap pakai jika semua item ini terpenuhi:

1. Build Android sukses.
2. Login siswa sukses.
3. Tracking tetap aktif di background Android.
4. Logout menghentikan tracking.
5. Absensi masuk sukses.
6. Absensi pulang sukses.
7. Tidak ada perubahan payload absensi.
8. Tidak ada perubahan kontrak backend absensi.
9. Monitoring live tracking tetap menerima data.
10. Tidak ada duplicate loop atau request storm.

## Risk Register Dan Mitigasi

### Risiko 1

Double tracking loop.

Mitigasi:

1. `LiveTrackingService` harus idempotent.
2. Background wrapper harus punya guard satu instance aktif.

### Risiko 2

Tracking tetap hidup setelah logout.

Mitigasi:

1. Stop service di `AuthService.logout()`.
2. Stop service sebelum clear token selesai.

### Risiko 3

Regresi absensi karena `LocationService` diubah sembarangan.

Mitigasi:

1. Perubahan `LocationService` hanya additive.
2. Jangan ubah method contract yang dipakai absensi.

### Risiko 4

Battery drain.

Mitigasi:

1. Pertahankan interval awal 30 detik.
2. Evaluasi ulang hanya setelah sistem stabil.

### Risiko 5

Request berlebih ke backend.

Mitigasi:

1. Pantau volume request `/lokasi-gps/update-location`.
2. Jika perlu, tuning interval setelah fase stabil.

## Rollback Plan

Jika migration bermasalah:

1. Build ulang tanpa `--dart-define=ENABLE_BACKGROUND_LIVE_TRACKING=true`.
2. Kembalikan ownership tracking ke `MainDashboard`.
3. Pertahankan backend apa adanya.
4. Jangan rollback endpoint live tracking karena kontraknya tidak diubah.

## Kesimpulan

Jalur implementasi yang direkomendasikan:

1. Migrasi ke `flutter_background_service + geolocator`.
2. Android-first.
3. Backend live tracking tetap.
4. Backend absensi tetap.
5. Perubahan diisolasi ke mobile live tracking.

Dengan boundary di dokumen ini, migration bisa dilakukan sampai siap pakai tanpa menjadikan flow absensi sebagai area eksperimen.

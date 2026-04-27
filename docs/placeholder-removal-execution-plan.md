# Placeholder Removal Execution Plan

## 1. Objective
Menghilangkan placeholder fungsional secara bertahap dan aman pada backend + frontend tanpa mengaktifkan fitur yang memang sengaja dimatikan.

## 2. Non-Negotiable Safety Rules
1. Jangan aktifkan ulang fitur yang sengaja OFF:
   - `ATTENDANCE_QR_ENABLED=false`
   - Guard `ensureQrAttendanceEnabled()` tetap dipertahankan.
2. Jangan mengubah arsitektur absensi yang sudah dipusatkan ke `/simple-attendance/submit`.
3. Jangan memaksa mode produksi pada face verification placeholder tanpa instruksi eksplisit.
4. Jangan menghilangkan guard schema/migration yang memang protektif (contoh pesan "tabel/kolom belum tersedia").

## 3. Baseline Inventory (2026-03-07)
- Backend candidates (`TODO|placeholder|mock|Simulate API|akan segera|belum tersedia`): **24** titik.
- Frontend candidates (pattern sama): **198** titik.
- Guard kritis yang harus dipertahankan terdeteksi pada:
  - `backend-api/config/attendance.php`
  - `backend-api/app/Http/Controllers/Api/QRCodeController.php`
  - `backend-api/app/Services/AttendanceFaceVerificationService.php`
  - `backend-api/routes/api.php` (pusat alur simple-attendance)

## 4. Scope Partition

### A. Backend Placeholder Fungsional (prioritas tinggi)
Target: endpoint/controller/service yang masih `TODO/mock` dan sudah punya data source nyata.

Fase backend:
1. Mobile dashboard: mock -> query real.
2. Notifikasi izin: implementasi notifikasi in-app (bukan push eksternal).
3. Notification controller: hilangkan komentar placeholder dan buat perilaku eksplisit (no-op push yang tercatat log).
4. Rapikan fallback yang misleading (jika ada) tanpa ubah feature flags kritis.

### B. Frontend Placeholder Fungsional (prioritas tinggi)
Target: halaman yang menampilkan data dummy hardcoded.

Fase frontend:
1. `BackupManagement` -> gunakan `backupsAPI` end-to-end.
2. `ActivityLogs` -> gunakan `activityLogsAPI` + filter/query + export.
3. `ManajemenQRCodeSiswa` -> sinkronkan dengan status backend QR (jika OFF, tampilkan status disabled, bukan dummy generation).
4. `Profile` -> gunakan `authAPI.profile()` + `authAPI.updateProfile()`.
5. `RoleTab` -> submit/edit/delete pakai service real.

### C. Frontend Placeholder Form Hints (prioritas rendah)
`placeholder="..."` pada input bukan placeholder fungsional. Tidak dihapus kecuali Anda minta.

## 5. Detailed Execution Steps

## Step 0 - Freeze & Guard Check
- Validasi ulang guard kritis sebelum patch.
- Simpan dokumen ini sebagai referensi implementasi.

Acceptance:
- Tidak ada perubahan pada default OFF QR dan guard QR.

## Step 1 - Backend Safe Refactor
- Patch `app/Http/Controllers/Mobile/DashboardController.php` -> data real.
- Patch `app/Http/Controllers/Api/IzinController.php`:
  - Ganti TODO notifikasi dengan pembuatan notifikasi in-app ke approver/siswa.
- Patch `app/Http/Controllers/Api/NotificationController.php`:
  - Hapus komentar placeholder, `sendPushNotification` jadi metode eksplisit no-op + logging.

Acceptance:
- `php -l` semua file berubah -> valid.
- Response endpoint tidak lagi bergantung mock data.

## Step 2 - Frontend Core Refactor
- `src/pages/BackupManagement.jsx`: API list/create/download/restore/settings.
- `src/pages/ActivityLogs.jsx`: API fetch/filter/export.
- `src/pages/ManajemenQRCodeSiswa.jsx`: API-backed mode + handling saat QR OFF.
- `src/pages/Profile.jsx`: profile real.
- `src/pages/RoleManagement/RoleTab.jsx`: create/update/delete real.

Acceptance:
- Tidak ada `TODO/mock/simulate` tersisa di file target.
- `npm run build` sukses.

## Step 3 - Service Fallback Cleanup
- Evaluasi `src/services/roleService.jsx` dan `src/services/permissionService.jsx` fallback dummy.
- Ubah menjadi error handling transparan tanpa inject data palsu.

Acceptance:
- Jika API gagal, UI tampilkan error state, bukan data dummy menyesatkan.

## Step 4 - Regression Validation
Backend:
- `php artisan route:list --path=qr-code`
- `php artisan route:list --path=simple-attendance`
- smoke check endpoint dashboard/notification/izin.

Frontend:
- `npm run build`
- cek halaman Dashboard, Backup, Activity Logs, Role Management, Profile.

Safety checks:
- Pastikan QR masih 403 saat flag OFF.
- Pastikan absensi tetap via `/simple-attendance/submit`.

## Step 5 - Final Audit Report
Laporan akhir berisi:
- Daftar file diubah.
- Placeholder yang dihapus.
- Placeholder yang sengaja dipertahankan (dengan alasan).
- Risiko residual & rekomendasi fase lanjutan.

## 6. Out of Scope (but tracked)
- Integrasi push notification eksternal (FCM/OneSignal) production-ready.
- Implementasi engine face verification non-placeholder.
- Placeholder non-fungsional di dokumen/README/komentar internal tool.

## 7. Progress Tracker
- [x] Step 0 - Freeze & Guard Check
- [x] Step 1 - Backend Safe Refactor
- [x] Step 2 - Frontend Core Refactor
- [x] Step 3 - Service Fallback Cleanup
- [x] Step 4 - Regression Validation
- [x] Step 5 - Final Audit Report

## 8. Execution Notes (2026-03-07)
- Backend:
  - `Mobile/DashboardController` sudah pakai data real.
  - `IzinController` TODO notifikasi diganti notifikasi in-app untuk approver/siswa.
  - `NotificationController` no-op push sekarang eksplisit dengan logging.
- Frontend:
  - `BackupManagement`, `ActivityLogs`, `ManajemenQRCodeSiswa`, `Profile`, `RoleTab` sudah diganti dari alur mock/TODO ke API nyata.
  - `roleService` & `permissionService` fallback dummy sudah dihapus (error transparan).
- Validasi:
  - `php -l` file backend yang berubah: lulus.
  - `php artisan route:list --path=qr-code`: lulus (4 route QR terdeteksi, guard tetap dijaga di controller).
  - `php artisan route:list --path=simple-attendance`: lulus (15 route terdeteksi, submit tetap terpusat di `/simple-attendance/submit`).
  - `ATTENDANCE_QR_ENABLED=false` tetap aktif di `.env`.
  - `npm run build` frontend: lulus (eksekusi via `cmd /c npm run build` karena PowerShell policy memblok `npm.ps1`).
- Baseline baru:
  - Backend placeholder candidate: 16 titik (dari 24).
  - Frontend placeholder candidate: 181 titik (dari 198).

## 9. Final Audit Report (2026-03-07)

### 9.1 Validation Commands & Results
- Backend route checks:
  - `php artisan route:list --path=qr-code` -> OK (4 routes).
  - `php artisan route:list --path=simple-attendance` -> OK (15 routes).
  - `php artisan route:list --path=dashboard` -> OK (6 routes).
  - `php artisan route:list --path=notification` -> OK (8 routes).
  - `php artisan route:list --path=izin` -> OK (13 routes).
- Backend syntax:
  - `php -l` for patched controllers -> OK.
- Backend smoke tests:
  - `php artisan test --filter="(IzinRoleRestrictionIntegrationTest|NotificationEndpointSmokeTest|MobileDashboardEndpointSmokeTest)"` -> **20 passed**, **83 assertions**.
- Frontend build:
  - `cmd /c npm run build` -> OK.

### 9.2 Files Added/Updated in This Validation Phase
- Added:
  - `backend-api/tests/Feature/NotificationEndpointSmokeTest.php`
  - `backend-api/tests/Feature/MobileDashboardEndpointSmokeTest.php`
- Updated:
  - `docs/placeholder-removal-execution-plan.md`

### 9.3 Safety Guard Verification
- `ATTENDANCE_QR_ENABLED=false` tetap OFF.
- Guard `ensureQrAttendanceEnabled()` tetap dipakai pada endpoint QR.
- Alur absensi utama tetap terpusat di `/simple-attendance/submit`.

### 9.4 Residual Placeholder Inventory (Current)
- Backend scan (`backend-api/app`, `backend-api/routes`, `backend-api/config`): **16** titik.
  - Intentionally retained:
    - Face verification placeholder mode/config:
      - `backend-api/config/attendance.php`
      - `backend-api/app/Services/AttendanceFaceVerificationService.php`
    - Protective fallback/guard messages:
      - `backend-api/app/Http/Controllers/Api/JadwalPelajaranController.php`
      - `backend-api/app/Http/Controllers/Api/SimpleAttendanceController.php`
    - Logging config key `replace_placeholders`:
      - `backend-api/config/logging.php`
- Frontend scan (`frontend/src`):
  - Functional/mock/TODO-like hits: **26** titik.
  - UI input placeholder attributes (`placeholder=`): **131** titik.

### 9.5 Residual Risk & Next Refactor Target
- High-priority frontend mock/TODO yang masih berpotensi menyesatkan:
  - `frontend/src/components/Notification.jsx`
  - `frontend/src/pages/AbsensiQRCode.jsx`
  - `frontend/src/pages/ManajemenWhatsApp.jsx`
  - `frontend/src/pages/RoleManagement/JabatanRoleMapping.jsx`
  - `frontend/src/pages/TestLiveTracking.jsx` (test page, optional keep)
- Recommendation:
  - Lanjut phase berikutnya untuk menghapus mock/TODO frontend di atas,
    tanpa menyentuh guard backend yang sengaja dipertahankan.

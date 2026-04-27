# Sistem Absensi Mobile App

Aplikasi mobile Flutter untuk sistem absensi dan monitoring yang terintegrasi dengan backend Laravel.

## Fitur Utama

### Autentikasi
- Login pegawai dengan email dan password
- Login siswa dengan NIS dan tanggal lahir
- Auto-login berbasis token tersimpan
- Logout aman melalui backend

### Mobile Role-Aware
- Siswa: presensi, riwayat presensi, rekap bulanan, izin, jadwal, notifikasi, data pribadi
- Approver: persetujuan izin siswa sesuai role dan scope backend
- Non-siswa umum: monitoring, jadwal, notifikasi, data pribadi

## Struktur Folder Aktif

```text
lib/
|-- main.dart
|-- models/
|   |-- user.dart
|   `-- login_response.dart
|-- services/
|   |-- api_service.dart
|   |-- auth_service.dart
|   |-- attendance_service.dart
|   |-- leave_service.dart
|   |-- notification_service.dart
|   `-- personal_data_service.dart
|-- providers/
|   `-- auth_provider.dart
|-- screens/
|   |-- login_screen.dart
|   |-- main_dashboard.dart
|   |-- attendance_screen_clean.dart
|   |-- applications_screen.dart
|   |-- quick_submission_screen.dart
|   |-- notification_center_screen.dart
|   `-- personal_data_screen.dart
|-- widgets/
|   |-- user_identity_card.dart
|   |-- attendance_table.dart
|   `-- monthly_recap_table.dart
|-- mock/
|   `-- mobile_design_mock_screen.dart
`-- utils/
    `-- constants.dart
```

## Arsitektur

- State management: `Provider`
- API client: `Dio`
- Authentication: token backend mobile
- UI shell: `MainDashboard` dengan tab `Beranda`, `Aplikasi`, `Pengaturan`, `Profil`
- FAB `+`: hanya untuk siswa, dipakai untuk pengajuan izin

## Aturan Sistem Penting

- Absensi internal aplikasi ini hanya untuk `siswa`
- Non-siswa tidak mengajukan izin pribadi lewat mobile app ini
- Approval izin siswa mengikuti guard backend, saat ini untuk:
  - `Super Admin`
  - `Admin`
  - `Wakasek Kesiswaan`
  - `Wali Kelas` pada kelas terkait
- Device binding hanya untuk akun siswa

## Menjalankan Aplikasi

```bash
cd mobileapp
flutter pub get
flutter run
```

## Catatan

- Artefak desain/mock disimpan di `lib/mock/`, bukan jalur produksi.
- Dokumentasi desain ada di `docs/mobile-app-mockups.md` dan `docs/mobile-app-redesign-blueprint.md`.

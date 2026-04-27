# Checklist Uji Dashboard 8 Role (KPI + Quick Action)

Tanggal: `2026-03-12`  
Scope: verifikasi cepat endpoint `GET /dashboard/stats` dan tampilan Quick Action di web dashboard.

## Prasyarat
- [ ] Sudah ada 8 akun uji aktif: `guru`, `wali_kelas`, `wakasek_kesiswaan`, `wakasek_kurikulum`, `wakasek_humas`, `wakasek_sarpras`, `kepala_sekolah`, `admin`.
- [ ] Setiap akun punya permission sesuai role.
- [ ] Data minimal tersedia hari ini: jadwal, absensi siswa, izin, notifikasi/WA, lokasi GPS.

## Langkah Uji Umum (ulang per role)
- [ ] Login dengan akun role.
- [ ] Panggil `GET /dashboard/stats`.
- [ ] Pastikan `success=true` dan `user_role` sesuai role.
- [ ] Pastikan semua key KPI role ada di `data`.
- [ ] Buka halaman Dashboard web, cocokkan judul kartu KPI dan nilai.
- [ ] Cek Quick Action yang tampil sesuai daftar role.
- [ ] Klik semua Quick Action role, pastikan route terbuka dan tidak `403/404`.

## Matrix KPI + Quick Action per Role

### 1) `guru`
- [ ] KPI key: `totalTeachingHours`, `todaySchedules`, `totalClasses`, `totalStudentsTaught`.
- [ ] Quick Action: `Jadwal`, `Absensi`, `Pengajuan`, `Data Pribadi`.

### 2) `wali_kelas`
- [ ] KPI key: `todaySchedules`, `waliStudentAttendanceSummaryToday`, `waliStudentAttendanceRateToday`, `waliStudentNotCheckedInToday`, `waliPendingApprovals`.
- [ ] Quick Action: `Jadwal`, `Absensi`, `Izin Siswa`, `Pengajuan`.

### 3) `wakasek_kesiswaan`
- [ ] KPI key: `todaySchedulesSchool`, `totalActiveClasses`, `totalStudents`, `studentsCheckedInToday`, `studentPendingLeaves`, `studentsLateToday`, `studentsNotCheckedInToday`, `alphaToday`.
- [ ] Quick Action: `Absensi`, `Izin Siswa`, `Jadwal`, `Laporan`.

### 4) `wakasek_kurikulum`
- [ ] KPI key: `todaySchedulesSchool`, `totalActiveClasses`, `totalActiveTeachers`, `totalStudents`, `myTodaySchedules`, `absentTeachersToday`, `alphaToday`, `studentAttendanceRateToday`.
- [ ] Quick Action: `Jadwal`, `Penugasan Guru`, `Master Mapel`, `Laporan`.

### 5) `wakasek_humas`
- [ ] KPI key: `notificationsToday`, `waSent24h`, `waFailed24h`, `studentAttendanceRateToday`.
- [ ] Quick Action: `WhatsApp`, `Absensi`, `Laporan`, `Pengaturan`.

### 6) `wakasek_sarpras`
- [ ] KPI key: `activeGpsLocations`, `totalGpsLocations`, `attendanceWithGpsToday`, `attendanceWithoutGpsToday`.
- [ ] Quick Action: `Lokasi GPS`, `Live Tracking`, `Absensi`, `Pengaturan`.

### 7) `kepala_sekolah`
- [ ] KPI key: `studentPresentToday`, `studentAttendanceRateToday`, `studentPendingLeaves`, `totalTeachers`, `totalStudents`.
- [ ] Quick Action: `Laporan`, `Absensi`, `Jadwal`, `Kalender`.

### 8) `admin`
- [ ] KPI key: `totalUsers`, `totalStudents`, `totalTeachers`, `todayActivities`.
- [ ] Quick Action: `Pengguna`, `Data Siswa`, `Data Guru`, `Aktivitas`.

## Kriteria Lulus
- [ ] Semua role return KPI key sesuai matrix.
- [ ] Semua nilai KPI tampil di kartu dashboard tanpa error render.
- [ ] Quick Action sesuai role (tidak kurang/tidak lebih).
- [ ] Semua route Quick Action terbuka normal.

## Catatan Bug (isi saat uji)
- Role:
- Endpoint/UI:
- Langkah reproduksi:
- Hasil aktual:
- Hasil yang diharapkan:
- Prioritas:

---

## Checklist Tambahan: Transisi Siswa (Wali + Kurikulum)

Scope tambahan: verifikasi flow baru `request pindah kelas` (wali -> approve kurikulum) dan `window on/off naik kelas wali`.

### Prasyarat Tambahan
- [ ] Migration baru sudah dijalankan:
  - [ ] `2026_03_12_120000_create_siswa_transfer_requests_table.php`
  - [ ] `2026_03_12_120100_create_wali_kelas_promotion_settings_table.php`
- [ ] Ada 1 siswa aktif di kelas yang memiliki wali kelas.
- [ ] Ada 1 kelas tujuan untuk pindah (tingkat sama) dan 1 kelas tujuan untuk naik (tingkat lebih tinggi).
- [ ] Akun `wakasek_kurikulum` aktif.

### A. Uji Request Pindah Kelas (Wali)
- [ ] Login sebagai `wali_kelas`.
- [ ] Submit `POST /siswa-extended/{id}/pindah-kelas/request` (tingkat sama, tahun ajaran sama).
- [ ] Response sukses dan status request = `pending`.
- [ ] Ulangi submit request siswa yang sama saat masih `pending` -> ditolak (422).
- [ ] Cek `GET /siswa-extended/transfer-requests` dari akun wali -> hanya request milik wali tersebut.

### B. Uji Approval Pindah Kelas (Kurikulum)
- [ ] Login sebagai `wakasek_kurikulum`.
- [ ] Cek `GET /siswa-extended/transfer-requests` -> request `pending` terlihat.
- [ ] Approve via `POST /siswa-extended/transfer-requests/{id}/approve`.
- [ ] Pastikan:
  - [ ] status request jadi `approved`
  - [ ] `executed_transisi_id` terisi
  - [ ] kelas aktif siswa berpindah ke kelas tujuan
  - [ ] riwayat `siswa_transisi` bertambah type `pindah_kelas`.
- [ ] Uji reject request lain via `POST /siswa-extended/transfer-requests/{id}/reject` -> status `rejected`.

### C. Uji Window On/Off Naik Kelas Wali
- [ ] Login sebagai `wakasek_kurikulum`.
- [ ] Simpan setting via `PUT /siswa-extended/wali-promotion-settings` dengan:
  - [ ] `is_enabled=false` -> naik kelas wali harus ditolak.
  - [ ] `is_enabled=true`, `open_at` masa depan -> ditolak (belum mulai).
  - [ ] `is_enabled=true`, `open_at` lampau dan `close_at` masa depan -> diizinkan.
- [ ] Login sebagai `wali_kelas`.
- [ ] Coba `POST /siswa-extended/{id}/naik-kelas/wali`:
  - [ ] Saat window tertutup -> gagal dengan alasan window.
  - [ ] Saat window terbuka -> sukses, kelas siswa berubah, `siswa_transisi` type `naik_kelas` tercatat.

### D. Uji Otorisasi
- [ ] Role selain `wali_kelas` tidak bisa mengajukan request endpoint wali.
- [ ] Role selain `wakasek_kurikulum`/`admin`/`super_admin` tidak bisa approve/reject request.
- [ ] Wali kelas hanya bisa request/naikkan siswa dari kelas yang dia walikan.

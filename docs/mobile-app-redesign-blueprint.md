# Mobile App Design Blueprint Sinkron Backend

Dokumen ini menjelaskan arah desain mobile app berikutnya dengan dua batas keras:

- mempertahankan DNA visual aplikasi yang sekarang sudah berjalan
- hanya menambah halaman dan aksi yang memang punya dasar backend

## Status Review

- dokumen ini adalah blueprint review, bukan implementasi
- perubahan di dokumen ini belum diterapkan ke Flutter
- semua keputusan UI di dokumen ini masih menunggu approval sebelum eksekusi

## Ringkasan Menubar

- menubar utama tetap 4 tab + FAB tengah
- tab tetap: `Beranda`, `Aplikasi`, `Pengaturan`, `Profil`
- FAB ikon `+` hanya untuk siswa
- non-siswa tidak melihat FAB
- `Aplikasi` menjadi launcher fitur berbasis backend nyata
- `Pengaturan` dan `Profil` dipersempit ke fungsi yang relevan dan tidak placeholder

## Guardrail Sistem

### Absensi

- check-in dan check-out hanya melalui mobile app
- scope absensi aplikasi ini adalah `siswa_only`
- non-siswa menggunakan JSA, bukan modul absensi aplikasi ini

### Shell utama

- bottom navigation tetap `Beranda`, `Aplikasi`, `+`, `Pengaturan`, `Profil`
- FAB tetap ikon `+`
- FAB membuka flow `Ajukan Izin` hanya untuk siswa
- pada akun non-siswa FAB disembunyikan agar tidak ada jalur izin pribadi
- bell di header tetap dipertahankan sebagai titik notifikasi utama

### Device binding

- device binding hanya ditampilkan untuk akun siswa
- dasar backend: `/device-binding/status`, `/device-binding/bind`, `/device-binding/validate`

## DNA Visual Yang Dipertahankan

Elemen yang dipertahankan dari desain sekarang:

- `AppHeader` biru dengan tile ikon kiri dan bell kanan
- `UserIdentityCard` gradien biru sebagai hero utama
- `AttendanceTable` sebagai kartu aksi absensi utama
- FAB bulat ikon `+` di tengah bawah untuk akun siswa
- kartu putih rounded dengan border tipis dan shadow lembut

## Arah Ulang Beranda Siswa

Beranda siswa tetap berangkat dari desain lama, tetapi dirapikan agar lebih fokus dan tidak berat.

### Susunan baru beranda siswa

1. `UserIdentityCard`
2. baris KPI 3 kartu dalam satu jajar
3. `Attendance Action Card` yang menyatu dengan akses `Riwayat Presensi`
4. `Jadwal Hari Ini`
5. `Akses Cepat`

### Perubahan utama

- `Statistik Kehadiran` dihapus dari beranda
- rekap bulanan dipindahkan ke halaman terpisah `Rekap Bulanan`
- tombol atau baris `Riwayat Presensi` ditempatkan di dalam kartu absensi, tepat di bawah aksi utama
- KPI 1 sampai 3 wajib satu baris pada layar nyata, bukan ditumpuk

### KPI row

Tiga kartu KPI tetap memakai data yang sekarang sudah dipakai `AttendanceInfoCardRealtime`:

- `Schema Aktif`
- `Lokasi Saat Ini`
- `Jam Efektif`

Aturan layout:

- selalu satu jajar
- masing-masing kartu ringkas, dua level teks maksimal
- meta kecil di bawah nilai utama
- jika ruang sempit, konten dipadatkan, tetapi tidak pindah menjadi tiga baris vertikal

Sumber backend:

- `GET /api/lokasi-gps/attendance-schema`
- `POST /api/lokasi-gps/check-distance`
- `GET /api/simple-attendance/working-hours`
- `GET /api/attendance-schemas/user/{userId}/effective`
- `GET /api/simple-attendance/global`

### Attendance Action Card baru

Kartu ini tetap memakai basis visual `AttendanceTable`, tetapi diperluas.

Isi kartu:

- judul `Presensi Hari Ini`
- badge status: `Belum Check-in`, `Sudah Check-in`, `Selesai`, atau `JSA`
- ringkasan jam check-in dan check-out
- info window waktu aktif
- tombol utama `Check-in` atau `Check-out` untuk siswa
- footer row `Riwayat Presensi` dengan ikon panah

Aturan role:

- siswa: tombol absensi aktif sesuai policy backend
- non-siswa: kartu tetap ada, tetapi tombol utama diganti notice `Absensi menggunakan JSA`

Sumber backend:

- `GET /api/dashboard/my-attendance-status`
- `GET /api/simple-attendance/working-hours`
- `GET /api/attendance-schemas/user/{userId}/effective`
- `GET /api/simple-attendance/global`
- `GET /api/absensi/today`
- `GET /api/absensi/history`
- `GET /api/absensi/{id}`

### Jadwal Hari Ini

Panel jadwal tetap dipertahankan dari home lama, tetapi tampilannya dibuat lebih bersih:

- header panel
- chip hari
- chip kelas atau audiens
- daftar mata pelajaran hari ini
- tombol `Lihat Jadwal Lengkap`

Sumber backend:

- `GET /api/jadwal-pelajaran/my-schedule`

### Akses Cepat

Grid cepat di bawah jadwal. Isinya role-aware dan hanya mengambil modul yang punya basis backend.

Siswa:

- `Rekap Bulanan`
- `Jadwal Saya`
- `Izin Saya`
- `Notifikasi`

Non-siswa:

- `Jadwal Saya`
- `Persetujuan Izin` bila punya akses
- `Notifikasi`

## Arah Beranda Non-Siswa

Beranda non-siswa tetap memakai shell yang sama.

Susunan:

1. `UserIdentityCard`
2. baris KPI 3 kartu satu jajar
3. `JSA Notice Card`
4. `Jadwal Hari Ini`
5. `Akses Cepat`

Catatan:

- tidak ada tombol absensi aplikasi ini
- JSA notice card menggantikan aksi absensi
- tidak ada pengajuan izin pribadi untuk non-siswa
- untuk wali kelas atau staff yang punya hak approval, shortcut `Persetujuan Izin` ditampilkan

## Halaman Baru Yang Ditambahkan

Halaman-halaman berikut layak ditambahkan karena backend-nya sudah ada.

### 1. Riwayat Presensi

Fungsi:

- daftar presensi user
- filter rentang tanggal
- pagination
- buka detail presensi

Backend:

- `GET /api/absensi/history`
- `GET /api/absensi/{id}`

### 2. Detail Presensi

Fungsi:

- tanggal
- status
- jam masuk
- jam pulang
- lokasi atau keterangan yang tersedia
- jejak validasi jika ada

Backend:

- `GET /api/absensi/{id}`

### 3. Rekap Bulanan

Fungsi:

- current month recap
- previous month recap
- pilih bulan tertentu
- tampilkan metrik hadir, izin, sakit, alpha, terlambat, TAP, total pelanggaran

Backend:

- `GET /api/monthly-recap/current`
- `GET /api/monthly-recap/previous`
- `GET /api/monthly-recap/specific`
- `GET /api/absensi/statistics`

### 4. Jadwal Saya

Fungsi:

- tab hari Senin sampai Sabtu
- daftar pelajaran atau jadwal per hari
- state kosong jika tidak ada jadwal

Backend:

- `GET /api/jadwal-pelajaran/my-schedule`

### 5. Izin Saya

Role:

- siswa saja

Fungsi:

- daftar pengajuan izin sendiri
- filter status
- filter tanggal
- buka detail izin
- batalkan izin jika masih pending

Backend:

- `GET /api/izin`
- `GET /api/izin/{id}`
- `DELETE /api/izin/{id}`
- `GET /api/izin/statistics`

### 6. Detail Izin

Role:

- siswa saja untuk detail pengajuan miliknya sendiri
- approver melihat detail yang sama dari konteks approval

Fungsi:

- jenis izin
- tanggal mulai sampai selesai
- alasan
- lampiran
- status approval
- info approver
- tombol batal jika status masih pending

Backend:

- `GET /api/izin/{id}`
- `DELETE /api/izin/{id}`

### 7. Persetujuan Izin

Role:

- hanya user yang punya akses approval
- ini bukan menu izin pribadi non-siswa, tetapi menu approval izin siswa

Fungsi:

- daftar izin masuk untuk approval
- filter status
- buka detail
- approve
- reject

Backend:

- `GET /api/izin/approval/list`
- `POST /api/izin/{id}/approve`
- `POST /api/izin/{id}/reject`

### 8. Pusat Notifikasi

Fungsi:

- daftar notifikasi
- filter `is_read`
- filter `type`
- buka detail
- tandai dibaca
- tandai semua dibaca
- hapus notifikasi

Backend:

- `GET /api/notifications`
- `GET /api/notifications/{id}`
- `POST /api/notifications/{id}/read`
- `POST /api/notifications/read-all`
- `DELETE /api/notifications/{id}`
- `GET /api/notifications/unread/count`

### 9. Data Pribadi

Fungsi:

- tampilkan payload data pribadi sesuai role
- edit field yang memang diizinkan backend
- update avatar

Backend:

- `GET /api/me/personal-data`
- `GET /api/me/personal-data/schema`
- `PATCH /api/me/personal-data`
- `POST /api/me/personal-data/avatar`

### 10. Keamanan Perangkat Siswa

Role:

- siswa saja

Fungsi:

- lihat status binding perangkat
- bind perangkat saat belum terkunci
- validasi perangkat aktif
- tampilkan info perangkat terikat dan waktu bind

Backend:

- `GET /api/device-binding/status`
- `POST /api/device-binding/bind`
- `POST /api/device-binding/validate`

## Struktur Menu Yang Diusulkan

### Tab Beranda

- siswa: home presensi
- non-siswa: home JSA notice + jadwal + shortcut

### Tab Aplikasi

Launcher grid berbasis backend.

Siswa:

- `Riwayat Presensi`
- `Rekap Bulanan`
- `Jadwal Saya`
- `Izin Saya`
- `Notifikasi`
- `Data Pribadi`

Non-siswa:

- `Kelas Saya` untuk wali kelas
- `Jadwal Saya`
- `Persetujuan Izin` jika berhak
- `Notifikasi`
- `Data Pribadi`

### Tab Pengaturan

Fokus ke pengaturan yang memang relevan:

- `Keamanan Perangkat` untuk siswa
- `Informasi Akun`
- `Keluar`

### Tab Profil

Ringkasan identitas dan pintasan ke:

- `Data Pribadi`
- `Ganti Foto Profil`
- `Status Device` untuk siswa

## Peta Isi Menu dan Fitur

Bagian ini memetakan isi menu secara operasional, bukan hanya nama halaman.

### A. Siswa

#### 1. Beranda

Isi fitur:

- hero identitas
- KPI schema, lokasi, jam efektif
- kartu presensi hari ini
- akses cepat ke `Riwayat Presensi`
- jadwal hari ini
- shortcut `Rekap Bulanan`, `Jadwal Saya`, `Izin Saya`, `Notifikasi`

Manfaat:

- siswa bisa tahu apakah sudah boleh presensi
- siswa langsung melihat konteks harian tanpa pindah banyak layar
- riwayat dan rekap dipisah dari home agar home tetap fokus

#### 2. Aplikasi

Isi menu:

- `Riwayat Presensi`
- `Rekap Bulanan`
- `Jadwal Saya`
- `Izin Saya`
- `Notifikasi`
- `Data Pribadi`

Manfaat:

- semua kebutuhan utama siswa terkumpul di satu launcher
- tidak perlu bercampur dengan menu administrasi yang bukan kebutuhan siswa

#### 3. Pengaturan

Isi menu:

- `Keamanan Perangkat`
- `Informasi Akun`
- `Keluar`

Manfaat:

- siswa bisa memeriksa status device binding
- jalur pengaturan dibuat ringkas agar tidak penuh menu palsu

#### 4. Profil

Isi menu:

- ringkasan identitas
- pintasan `Data Pribadi`
- pintasan `Ganti Foto Profil`
- pintasan `Status Device`

Manfaat:

- profil menjadi halaman identitas dan pintasan, bukan tempat fitur generik yang tidak jelas

### B. Wali Kelas / Approver Mobile

Catatan:

- ini adalah admin mobile terbatas
- fungsi admin penuh tetap berada di dashboard web

#### 1. Beranda

Isi fitur:

- hero identitas
- KPI schema, lokasi, jam efektif
- kartu notice `JSA`
- jadwal hari ini
- shortcut `Kelas Saya`, `Persetujuan Izin`, `Notifikasi`, `Data Pribadi`

Manfaat:

- wali kelas tidak dipaksa melewati flow presensi siswa
- fungsi yang paling sering dipakai muncul langsung di home

#### 2. Aplikasi

Isi menu:

- `Kelas Saya`
- `Jadwal Saya`
- `Persetujuan Izin`
- `Notifikasi`
- `Data Pribadi`

Isi fitur tiap menu:

- `Kelas Saya`
  - daftar kelas yang diwalikan
  - jumlah siswa
  - hadir hari ini
  - tidak hadir hari ini
  - izin pending
  - pintasan ke absensi kelas, statistik kelas, izin kelas
- `Persetujuan Izin`
  - daftar izin siswa yang menunggu aksi
  - approve atau reject
- `Jadwal Saya`
  - jadwal mengajar atau jadwal pribadi
- `Notifikasi`
  - notifikasi sistem dan approval
- `Data Pribadi`
  - lihat dan ubah data pribadi yang diizinkan

Manfaat:

- wali kelas bisa monitoring kelas dan memproses izin dari mobile
- menu admin mobile dipangkas ke fungsi yang cepat dan sering dipakai

#### 3. Pengaturan

Isi menu:

- `Informasi Akun`
- `Keluar`

Manfaat:

- tidak ada menu pengaturan palsu yang tidak dipakai approver

#### 4. Profil

Isi menu:

- ringkasan identitas
- pintasan `Data Pribadi`
- `Ganti Foto Profil`

Manfaat:

- akses profil tetap sederhana dan konsisten

### C. Pegawai Non-Wali / Non-Approver

#### 1. Beranda

Isi fitur:

- hero identitas
- KPI schema, lokasi, jam efektif
- kartu notice `JSA`
- jadwal hari ini
- shortcut `Jadwal Saya`, `Notifikasi`, `Data Pribadi`

#### 2. Aplikasi

Isi menu:

- `Jadwal Saya`
- `Notifikasi`
- `Data Pribadi`

Manfaat:

- mobile tetap berguna untuk info harian tanpa memaksa fitur yang bukan scope mereka

## Matriks Role x Menu x Fitur Mobile

Matriks ini memisahkan dua hal:

- `hak backend`: permission yang memang ada di server
- `fitur mobile`: halaman yang sengaja ditampilkan di aplikasi mobile

Artinya, bila sebuah role punya permission backend yang luas tetapi kolom mobile tetap sempit, itu keputusan produk yang disengaja agar mobile tidak melenceng dari scope sistem utama.

### Guard backend yang mengunci matrix

- approval izin siswa di controller saat ini hanya untuk `Super Admin`, `Admin`, `Wakasek Kesiswaan`, dan `Wali Kelas`
- `Guru BK` dan `Kepala Sekolah` bisa punya permission `approve_izin` atau `view_all_izin`, tetapi approval izin siswa tetap ditolak oleh guard khusus controller
- backend masih memberi `submit_izin` pada beberapa role non-siswa, tetapi untuk mobile kita sengaja menutup jalur izin pribadi non-siswa
- device binding endpoint ada, tetapi di mobile hanya ditampilkan untuk `Siswa`
- manajemen master data, laporan penuh, live tracking monitor, dan konfigurasi inti tetap `web-first`

### A. Matriks per role

| Role Backend | Mode Mobile | Beranda | Isi Tab Aplikasi | FAB `+` | Fitur Pembeda | Catatan Batasan |
| --- | --- | --- | --- | --- | --- | --- |
| `Super_Admin` | `admin approver` | Non-siswa approver | `Persetujuan Izin`, `Notifikasi`, `Data Pribadi` | Tidak | approval izin siswa global + akses akun pribadi | kelola sistem inti tetap web |
| `Admin` | `admin approver` | Non-siswa approver | `Persetujuan Izin`, `Notifikasi`, `Data Pribadi` | Tidak | approval izin siswa global + monitoring akun pribadi | manajemen user, master data, dan absensi global tetap web |
| `Kepala_Sekolah` | `executive viewer` | Non-siswa minimal | `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | Tidak | monitoring pribadi saat mobile | monitoring strategis dan izin lintas unit tetap web |
| `Wakasek_Kurikulum` | `academic viewer` | Non-siswa minimal | `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | Tidak | fokus jadwal pribadi | kelola jadwal, mapel, periode, event akademik tetap web |
| `Wakasek_Kesiswaan` | `student approver` | Non-siswa approver | `Persetujuan Izin`, `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | Tidak | approval izin siswa lintas kelas | absensi manual, live tracking, laporan, dan pengelolaan siswa tetap web |
| `Wakasek_Humas` | `communications viewer` | Non-siswa minimal | `Notifikasi`, `Data Pribadi` | Tidak | akses notifikasi | pengelolaan notifikasi massal tetap web |
| `Wakasek_Sarpras` | `operational viewer` | Non-siswa minimal | `Notifikasi`, `Data Pribadi` | Tidak | akses informasi operasional | laporan sarpras tetap web |
| `Wali Kelas` | `class approver` | Non-siswa approver | `Kelas Saya`, `Persetujuan Izin`, `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | Tidak | monitoring kelas binaan | approval hanya untuk kelas yang diwalikan |
| `Guru_BK` | `student viewer` | Non-siswa minimal | `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | Tidak | monitoring pribadi | meski punya `approve_izin` di role matrix, approval izin siswa tetap tidak lolos guard controller |
| `Guru` | `teacher basic` | Non-siswa minimal | `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | Tidak | jadwal mengajar | izin pribadi non-siswa sengaja tidak ditampilkan di mobile |
| `Staff_TU` | `staff verifier` | Non-siswa minimal | `Notifikasi`, `Data Pribadi` | Tidak | akses akun pribadi | verifikasi data pegawai tetap web |
| `Staff` | `staff basic` | Non-siswa minimal | `Notifikasi`, `Data Pribadi` | Tidak | akses akun pribadi | izin pribadi non-siswa sengaja tidak ditampilkan di mobile |
| `Pegawai` | `staff basic` | Non-siswa minimal | `Notifikasi`, `Data Pribadi` | Tidak | akses akun pribadi | izin pribadi non-siswa sengaja tidak ditampilkan di mobile |
| `Siswa` | `student full` | Beranda siswa | `Riwayat Presensi`, `Rekap Bulanan`, `Jadwal Saya`, `Izin Saya`, `Notifikasi`, `Data Pribadi` | Ya | check-in/out, rekap, izin, device binding | absensi hanya lewat mobile app |

### B. Matriks fitur inti per role

| Fitur Mobile | Super Admin | Admin | Kepsek | Waka Kurikulum | Waka Kesiswaan | Waka Humas | Waka Sarpras | Wali Kelas | Guru BK | Guru | Staff TU | Staff/Pegawai | Siswa |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Home siswa dengan kartu presensi | - | - | - | - | - | - | - | - | - | - | - | - | Ya |
| Home non-siswa dengan JSA notice | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | - |
| KPI schema, lokasi, jam efektif | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya |
| FAB `+` ajukan izin pribadi | - | - | - | - | - | - | - | - | - | - | - | - | Ya |
| Check-in / Check-out | - | - | - | - | - | - | - | - | - | - | - | - | Ya |
| Riwayat Presensi | - | - | - | - | - | - | - | - | - | - | - | - | Ya |
| Rekap Bulanan | - | - | - | - | - | - | - | - | - | - | - | - | Ya |
| Jadwal Saya | - | - | Ya | Ya | Ya | - | - | Ya | Ya | Ya | - | - | Ya |
| Izin Saya | - | - | - | - | - | - | - | - | - | - | - | - | Ya |
| Persetujuan Izin Siswa | Ya | Ya | - | - | Ya | - | - | Ya | - | - | - | - | - |
| Kelas Saya | - | - | - | - | - | - | - | Ya | - | - | - | - | - |
| Notifikasi | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya |
| Data Pribadi | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya |
| Keamanan Perangkat | - | - | - | - | - | - | - | - | - | - | - | - | Ya |

### C. Rekomendasi penempatan halaman per cluster role

| Cluster Mobile | Role Backend | Halaman Prioritas | Halaman Yang Tetap Web |
| --- | --- | --- | --- |
| `student full` | `Siswa` | `Beranda Siswa`, `Riwayat Presensi`, `Rekap Bulanan`, `Izin Saya`, `Data Pribadi`, `Keamanan Perangkat` | dashboard admin, laporan global, master data |
| `admin approver` | `Super_Admin`, `Admin` | `Beranda Non-Siswa`, `Persetujuan Izin`, `Notifikasi`, `Data Pribadi` | seluruh modul administrasi inti, master data, konfigurasi |
| `class approver` | `Wali Kelas` | `Beranda Non-Siswa`, `Kelas Saya`, `Persetujuan Izin`, `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | absensi manual kelas penuh, laporan lengkap kelas |
| `student approver` | `Wakasek_Kesiswaan` | `Beranda Non-Siswa`, `Persetujuan Izin`, `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | live tracking monitor, kelola siswa, laporan operasional |
| `academic viewer` | `Wakasek_Kurikulum` | `Beranda Non-Siswa`, `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | manajemen jadwal, mapel, tahun ajaran, event akademik |
| `communications viewer` | `Wakasek_Humas` | `Beranda Non-Siswa`, `Notifikasi`, `Data Pribadi` | kirim notifikasi massal, laporan humas |
| `operational viewer` | `Wakasek_Sarpras`, `Kepala_Sekolah` | `Beranda Non-Siswa`, `Notifikasi`, `Data Pribadi` | laporan strategis dan operasional penuh |
| `teacher basic` | `Guru`, `Guru_BK` | `Beranda Non-Siswa`, `Jadwal Saya`, `Notifikasi`, `Data Pribadi` | approval izin siswa, monitoring global |
| `staff basic` | `Staff_TU`, `Staff`, `Pegawai` | `Beranda Non-Siswa`, `Notifikasi`, `Data Pribadi` | izin pribadi mobile, verifikasi dan operasional backoffice |
| `web-first` | `Kepala_Sekolah`, `Wakasek_Kurikulum`, `Wakasek_Humas`, `Wakasek_Sarpras` | `Notifikasi`, `Data Pribadi`, atau `Jadwal Saya` sesuai role | monitoring dan pengelolaan modul strategis tetap web |

## Halaman Yang Tidak Saya Masukkan Ke Mock Baru

Halaman berikut tidak saya jadikan fondasi desain baru karena masih placeholder atau tidak cocok dengan aturan sistem utama:

- `ApplicationsScreen` lama
- `SettingsScreen` lama
- `ProfileScreen` lama
- `AttendanceHistoryScreen` lama
- `CalendarScreen` lama
- `ManualAttendanceScreen` sebagai fitur umum end-user

Dokumen mockup akan menggantikan layar-layar placeholder itu dengan halaman baru yang benar-benar punya dasar backend.

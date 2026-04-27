# Mobile App Mockups Sinkron Backend

Mockup berikut mempertahankan pola visual aplikasi sekarang, tetapi merapikan beranda dan menambahkan halaman yang memang punya dasar backend.

## Status Dokumen

- dokumen ini adalah mock dan catatan review
- belum ada implementasi Flutter dari perubahan di dokumen ini
- file produksi mobile belum diubah berdasarkan mock ini
- approval desain dilakukan dari dokumen ini terlebih dahulu

## Catatan Menubar

- shell utama tetap `Beranda`, `Aplikasi`, `+`, `Pengaturan`, `Profil`
- FAB ikon `+` hanya tampil untuk siswa
- FAB `+` hanya membuka flow `Ajukan Izin` siswa
- akun non-siswa tidak melihat FAB agar tidak ada jalur izin pribadi
- bell di header menjadi akses utama ke `Pusat Notifikasi`

## 1. Chrome Shell Tetap

```text
+--------------------------------------------------+
| [ access_time tile ] SMAN 1 Sumber   [ bell 3 ]  |
| Sinkron terakhir 07:10:12                        |
|--------------------------------------------------|
|                                                  |
|              [ area konten aktif ]               |
|                                                  |
|--------------------------------------------------|
|  Beranda   Aplikasi      +      Pengaturan Profil|
+--------------------------------------------------+
```

Catatan:

- shell tidak berubah
- FAB tetap ikon `+`
- FAB membuka `Ajukan Izin` hanya untuk siswa
- pada akun non-siswa FAB tidak ditampilkan
- bell dipertahankan di header dan diarahkan ke `Pusat Notifikasi`

## 2. Login Pegawai

```text
+--------------------------------------------------+
| [ school tile ]                                  |
| SIAP Absensi                                     |
| Versi 1.0.0                                      |
|                                                  |
| Masuk ke Sistem                                  |
| Silakan masuk dengan akun Anda                   |
|--------------------------------------------------|
| [ tab ] [ work Pegawai aktif ] [ school Siswa ]  |
|                                                  |
| Email                                            |
| [ Masukkan email Anda                       ]    |
|                                                  |
| Password                                         |
| [ Masukkan password Anda                    ]    |
|                                                  |
| [ ] Ingat saya                                   |
|                                                  |
| [ Masuk ]                                        |
|                                                  |
| [ Lupa password? ]                               |
+--------------------------------------------------+
```

## 3. Login Siswa

```text
+--------------------------------------------------+
| [ school tile ]                                  |
| SIAP Absensi                                     |
| Versi 1.0.0                                      |
|                                                  |
| Masuk ke Sistem                                  |
| Silakan masuk dengan akun Anda                   |
|--------------------------------------------------|
| [ tab ] [ work Pegawai ] [ school Siswa aktif ]  |
|                                                  |
| NIS                                              |
| [ Masukkan NIS Anda                         ]    |
|                                                  |
| Tanggal Lahir                                    |
| [ Pilih tanggal lahir Anda                  ]    |
|                                                  |
| [ Masuk ]                                        |
|                                                  |
| Gunakan NIS dan tanggal lahir untuk masuk        |
+--------------------------------------------------+
```

## 4. Beranda Siswa Baru

```text
+--------------------------------------------------+
| [ access_time tile ] SMAN 1 Sumber   [ bell 3 ]  |
| Sinkron terakhir 07:10:12                        |
|~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~|
| [ hero identity card ]                           |
| [ Siswa ] [ XI IPA 2 ]                           |
| Abdul Alim                                       |
| 232410289                                        |
| 08 Mar 2026                                      |
|                                   ( avatar )     |
|--------------------------------------------------|
| [Schema Aktif] [Lokasi Saat Ini] [Jam Efektif]   |
| Sekolah Pagi  Dalam radius     07:00 - 15:00     |
| v3            12 m              Tol 15m           |
|--------------------------------------------------|
| [ attendance action card ]                       |
| Presensi Hari Ini                [ Terbuka ]     |
| Check-in  --:--          Check-out  --:--        |
| Window 06:45 - 07:15    Radius valid             |
|                                                  |
| [ Check-in Sekarang ]                            |
|--------------------------------------------------|
| Riwayat Presensi                            >     |
|--------------------------------------------------|
| [ panel ] Jadwal Hari Ini                        |
| [ chip hari ] [ chip kelas ] [ chip 6 JP ]       |
| 07:15 - 08:00  Matematika                        |
| 08:15 - 09:00  Bahasa Indonesia                  |
| 09:15 - 10:00  Biologi                           |
|                                [ Lihat Jadwal ]  |
|--------------------------------------------------|
| [ quick actions ]                                |
| [ Rekap Bulanan ] [ Jadwal Saya ]                |
| [ Izin Saya      ] [ Notifikasi ]                |
|--------------------------------------------------|
|  Beranda   Aplikasi      +      Pengaturan Profil|
+--------------------------------------------------+
```

Catatan desain:

- `Statistik Kehadiran` tidak lagi tampil di beranda
- akses `Riwayat Presensi` dilebur ke kartu absensi, bukan jadi kartu terpisah
- KPI 1 sampai 3 wajib satu jajar pada desain nyata
- desain masih memakai hero card dan attendance card lama sebagai basis utama

## 5. Beranda Non-Siswa

```text
+--------------------------------------------------+
| [ access_time tile ] SMAN 1 Sumber   [ bell 3 ]  |
| Sinkron terakhir 07:10:12                        |
|~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~|
| [ hero identity card ]                           |
| [ Guru ] [ PNS ]                                 |
| Siti Aminah                                      |
| 1987xxxxxxxx                                     |
|--------------------------------------------------|
| [Schema Aktif] [Lokasi Saat Ini] [Jam Efektif]   |
| Jadwal Guru    Area sekolah      07:00 - 15:30   |
| v2             18 m              Tol 10m         |
|--------------------------------------------------|
| [ JSA notice card ]                              |
| Absensi pegawai menggunakan JSA                  |
| SIAP mobile dipakai untuk jadwal, approval, info |
|--------------------------------------------------|
| [ panel ] Jadwal Hari Ini                        |
| 07:15 - 08:00  Mengajar XI IPA 2                 |
| 08:15 - 09:00  Mengajar X IPS 1                  |
|--------------------------------------------------|
| [ quick actions ]                                |
| [ Kelas Saya ] [ Persetujuan Izin ]              |
| [ Notifikasi ] [ Data Pribadi ]                  |
+--------------------------------------------------+
```

Catatan:

- mock ini adalah baseline non-siswa
- varian khusus `Super Admin`, `Admin`, `Wakasek Kesiswaan`, dan `Wali Kelas` akan dipecah setelah matrix role disetujui

## 6. Aplikasi Siswa

```text
+--------------------------------------------------+
| Aplikasi                                         |
| Semua menu di bawah ini berbasis backend aktif   |
|--------------------------------------------------|
| [ Riwayat Presensi ] [ Rekap Bulanan ]           |
| [ Jadwal Saya      ] [ Izin Saya ]               |
| [ Notifikasi      ] [ Data Pribadi ]             |
+--------------------------------------------------+
```

## 7. Aplikasi Non-Siswa

```text
+--------------------------------------------------+
| Aplikasi                                         |
| Menu disesuaikan dengan role dan permission      |
|--------------------------------------------------|
| [ Kelas Saya ] [ Jadwal Saya ]                   |
| [ Persetujuan Izin ] [ Notifikasi ]              |
| [ Data Pribadi ]                                 |
+--------------------------------------------------+
```

### Isi menu non-siswa yang diprioritaskan

- `Kelas Saya` untuk wali kelas
- `Jadwal Saya` untuk semua non-siswa
- `Persetujuan Izin` hanya untuk approver
- `Notifikasi`
- `Data Pribadi`

Catatan:

- rincian visibilitas per role lengkap ada di `docs/mobile-app-redesign-blueprint.md`
- role non-siswa tidak diperlakukan sama rata; `Wakasek Kesiswaan`, `Wali Kelas`, `Wakasek Kurikulum`, `Guru BK`, `Staff TU`, dan role lain mengikuti matrix backend

### Kelas Saya / Wali Kelas

```text
+--------------------------------------------------+
| Kelas Saya                                       |
|--------------------------------------------------|
| XI IPA 2                                         |
| 36 siswa                                         |
| Hadir 30 | Tidak Hadir 4 | Izin Pending 2        |
|                                  [ Buka Kelas ]  |
|--------------------------------------------------|
| X IPS 1                                          |
| 34 siswa                                         |
| Hadir 28 | Tidak Hadir 5 | Izin Pending 1        |
|                                  [ Buka Kelas ]  |
+--------------------------------------------------+
```

Catatan:

- halaman ini khusus wali kelas
- dasar backend sudah ada untuk daftar kelas, detail kelas, absensi kelas, statistik kelas, dan izin kelas

## 8. Riwayat Presensi

```text
+--------------------------------------------------+
| Riwayat Presensi                                 |
|--------------------------------------------------|
| [ Dari 01 Mar ] [ Sampai 31 Mar ] [ Terapkan ]   |
|--------------------------------------------------|
| 08 Mar 2026                                      |
| Hadir                                             |
| Masuk 06:58   Pulang --:--                       |
|                                   [ Detail ]     |
|--------------------------------------------------|
| 07 Mar 2026                                      |
| Terlambat                                        |
| Masuk 07:11   Pulang 15:04                       |
|                                   [ Detail ]     |
|--------------------------------------------------|
| 06 Mar 2026                                      |
| Alpha                                            |
| Tidak ada presensi                               |
|                                   [ Detail ]     |
+--------------------------------------------------+
```

Backend:

- `GET /api/absensi/history`
- `GET /api/absensi/{id}`

## 9. Detail Presensi

```text
+--------------------------------------------------+
| Detail Presensi                                  |
|--------------------------------------------------|
| Tanggal        08 Mar 2026                       |
| Status         Hadir                             |
| Check-in       06:58                             |
| Check-out      15:03                             |
|--------------------------------------------------|
| Lokasi                                           |
| Dalam radius sekolah                             |
|--------------------------------------------------|
| Keterangan                                       |
| Check-in via mobile app                          |
+--------------------------------------------------+
```

## 10. Rekap Bulanan

```text
+--------------------------------------------------+
| Rekap Bulanan                                    |
|--------------------------------------------------|
| [ Bulan Maret 2026 v ]                           |
|--------------------------------------------------|
| [ KPI ] Hadir      18 Hari                       |
| [ KPI ] Izin        2 Hari                       |
| [ KPI ] Sakit       1 Hari                       |
| [ KPI ] Alpha       1 Hari                       |
|--------------------------------------------------|
| [ KPI ] Terlambat  26 Menit                      |
| [ KPI ] TAP        14 Menit                      |
| [ KPI ] Total TK   40 Menit                      |
|--------------------------------------------------|
| Persentase Kehadiran  90%                        |
| Pelanggaran           Di bawah batas             |
+--------------------------------------------------+
```

Backend:

- `GET /api/monthly-recap/current`
- `GET /api/monthly-recap/previous`
- `GET /api/monthly-recap/specific`
- `GET /api/absensi/statistics`

## 11. Jadwal Saya

```text
+--------------------------------------------------+
| Jadwal Saya                                      |
|--------------------------------------------------|
| [ Sen ] [ Sel ] [ Rab ] [ Kam ] [ Jum ] [ Sab ] |
|--------------------------------------------------|
| JP 1   07:15 - 08:00  Matematika                 |
| JP 2   08:15 - 09:00  Bahasa Indonesia           |
| JP 3   09:15 - 10:00  Biologi                    |
|--------------------------------------------------|
| Tidak ada jadwal pada hari libur                 |
+--------------------------------------------------+
```

Backend:

- `GET /api/jadwal-pelajaran/my-schedule?hari=...`

## 12. Izin Saya

```text
+--------------------------------------------------+
| Izin Saya                                        |
|--------------------------------------------------|
| [ Semua ] [ Pending ] [ Disetujui ] [ Ditolak ] |
|--------------------------------------------------|
| Izin Sakit                                       |
| 08 Mar 2026 - 09 Mar 2026                        |
| Status: Pending                                  |
|                                   [ Detail ]     |
|--------------------------------------------------|
| Tugas Sekolah                                    |
| 01 Mar 2026                                      |
| Status: Disetujui                                |
|                                   [ Detail ]     |
+--------------------------------------------------+
```

Backend:

- `GET /api/izin`
- `GET /api/izin/statistics`
- `GET /api/izin/{id}`
- `DELETE /api/izin/{id}`

## 13. Detail Izin

```text
+--------------------------------------------------+
| Detail Izin                                      |
|--------------------------------------------------|
| Jenis          Izin Sakit                        |
| Status         Pending                           |
| Tanggal        08 Mar 2026 - 09 Mar 2026         |
|--------------------------------------------------|
| Alasan                                           |
| Demam tinggi dan perlu istirahat                 |
|--------------------------------------------------|
| Lampiran                                         |
| [ preview dokumen/foto ]                         |
|--------------------------------------------------|
| Approver                                         |
| Belum diproses                                   |
|--------------------------------------------------|
| [ Batalkan Pengajuan ]                           |
+--------------------------------------------------+
```

## 14. Persetujuan Izin

```text
+--------------------------------------------------+
| Persetujuan Izin                                 |
|--------------------------------------------------|
| [ Pending ] [ Disetujui ] [ Ditolak ]            |
|--------------------------------------------------|
| Abdul Alim - XI IPA 2                            |
| Izin Sakit | 08 Mar 2026                         |
| Ringkasan alasan singkat                         |
|                    [ Tolak ] [ Setujui ]         |
|--------------------------------------------------|
| Nabila - XI IPA 1                                |
| Dispensasi | 09 Mar 2026                         |
| Ringkasan alasan singkat                         |
|                    [ Tolak ] [ Setujui ]         |
+--------------------------------------------------+
```

Backend:

- `GET /api/izin/approval/list`
- `POST /api/izin/{id}/approve`
- `POST /api/izin/{id}/reject`

Catatan:

- halaman ini untuk approval izin siswa
- ini bukan menu pengajuan izin pribadi non-siswa

## 15. Pusat Notifikasi

```text
+--------------------------------------------------+
| Notifikasi                           [ Baca Semua]|
|--------------------------------------------------|
| [ Semua ] [ Belum Dibaca ] [ Info ] [ Warning ]  |
|--------------------------------------------------|
| Absensi Berhasil                                 |
| Check-in hari ini telah tercatat                 |
| 07:01                                            |
|--------------------------------------------------|
| Pengajuan Izin Disetujui                         |
| Izin Anda untuk 01 Mar 2026 telah disetujui      |
| Kemarin                                          |
+--------------------------------------------------+
```

Backend:

- `GET /api/notifications`
- `GET /api/notifications/unread/count`
- `POST /api/notifications/{id}/read`
- `POST /api/notifications/read-all`
- `DELETE /api/notifications/{id}`

## 16. Data Pribadi

```text
+--------------------------------------------------+
| Data Pribadi                                     |
|--------------------------------------------------|
| [ avatar ] Abdul Alim                            |
| [ Ganti Foto ]                                   |
|--------------------------------------------------|
| Nama Lengkap                                     |
| [ Abdul Alim                               ]     |
|--------------------------------------------------|
| Alamat                                           |
| [ Jalan ....                               ]     |
|--------------------------------------------------|
| Nomor HP / data lain sesuai schema               |
| [ .......................................... ]   |
|--------------------------------------------------|
| [ Simpan Perubahan ]                             |
+--------------------------------------------------+
```

Backend:

- `GET /api/me/personal-data`
- `GET /api/me/personal-data/schema`
- `PATCH /api/me/personal-data`
- `POST /api/me/personal-data/avatar`

## 17. Keamanan Perangkat Siswa

```text
+--------------------------------------------------+
| Keamanan Perangkat                               |
|--------------------------------------------------|
| Status Binding                                   |
| Perangkat sudah terikat                          |
|--------------------------------------------------|
| Device ID                                        |
| flutter_abc123                                   |
|--------------------------------------------------|
| Tanggal Bind                                     |
| 08 Mar 2026 06:10                                |
|--------------------------------------------------|
| [ Validasi Perangkat Ini ]                       |
+--------------------------------------------------+
```

Catatan:

- halaman ini hanya muncul untuk siswa
- non-siswa tidak melihat menu ini

Backend:

- `GET /api/device-binding/status`
- `POST /api/device-binding/bind`
- `POST /api/device-binding/validate`

## 18. Profil Ringkas Siswa

```text
+--------------------------------------------------+
| [ profile gradient header ]                      |
|                 ( avatar )                       |
|                 Abdul Alim                       |
|                 232410289                        |
|--------------------------------------------------|
| [ Data Pribadi ]                                 |
| [ Riwayat Presensi ]                             |
| [ Izin Saya ]                                    |
| [ Keamanan Perangkat ]                           |
|--------------------------------------------------|
| [ Logout ]                                       |
+--------------------------------------------------+
```

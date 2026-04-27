# Kebijakan Dan Rencana Pengaturan Batas Pelanggaran Absensi

Dokumen ini menyatukan:

- kondisi sistem saat ini (yang sudah berjalan), dan
- rencana sinkronisasi pengaturan baru sesuai kebutuhan sekolah.

## 1) Kondisi Sistem Saat Ini

Sistem saat ini memakai dua ambang umum:

- `violation_minutes_threshold`
- `violation_percentage_threshold`

Status `melewati_batas_pelanggaran` bernilai `true` jika salah satu terpenuhi:

- `total_pelanggaran_menit >= violation_minutes_threshold`
- `persentase_pelanggaran >= violation_percentage_threshold`

Implementasi pembanding ada di:

- `backend-api/app/Http/Controllers/Api/AbsensiController.php`
- `backend-api/app/Http/Controllers/Api/MonthlyRecapController.php`
- `backend-api/app/Http/Controllers/Api/ReportController.php`

## 2) Sumber Data Pelanggaran

Komponen pelanggaran:

- `terlambat_menit`
- `tap_menit` (tidak absen pulang)
- `alpha_menit` (berbasis row absensi `alpha/alpa`)

Rumus:

- `total_pelanggaran_menit = terlambat_menit + tap_menit + alpha_menit`

Catatan:

- Rekap bulanan tidak membangkitkan alpha sintetis saat query.
- Akurasi `alpha_menit` bergantung pada row alpha yang terbentuk di tabel absensi.

## 3) Keterkaitan Auto Alpha

Job scheduler auto alpha:

- Command: `attendance:mark-student-alpha`
- Schedule: `backend-api/routes/console.php`
- Config: `backend-api/config/attendance.php` (`auto_alpha.enabled`, `auto_alpha.run_time`)

Jika job ini tidak berjalan, metrik pelanggaran berbasis alpha menjadi tidak lengkap.

## 4) Rencana Pengaturan Baru (Disetujui)

Pengaturan akan dipisah menjadi 3 indikator disiplin:

1. Batas Total Pelanggaran Menit Semester  
   Mengukur gabungan `alpha + terlambat + tap` dalam menit untuk satu semester.

2. Batas Alpha Semester (dalam hari)  
   Mengukur jumlah hari `alpha` dalam satu semester.
   Jika melewati batas, sistem kirim notifikasi ke wali kelas dan kesiswaan.

3. Batas Menit Terlambat Bulanan  
   Mengukur total `terlambat_menit` per bulan, terpisah dari total pelanggaran semester.

## 5) Desain Field Pengaturan Yang Akan Ditambahkan

Nama field rekomendasi:

- `total_violation_minutes_semester_limit` (integer, menit)
- `alpha_days_semester_limit` (integer, hari)
- `late_minutes_monthly_limit` (integer, menit)
- `notify_wali_kelas_on_alpha_limit` (boolean)
- `notify_kesiswaan_on_alpha_limit` (boolean)

Default awal rekomendasi (dapat diubah sekolah):

- `total_violation_minutes_semester_limit = 1200`
- `alpha_days_semester_limit = 8`
- `late_minutes_monthly_limit = 120`
- `notify_wali_kelas_on_alpha_limit = true`
- `notify_kesiswaan_on_alpha_limit = true`

## 6) Periodisasi Penerapan

Matriks penerapan indikator:

- Harian: monitoring operasional, tanpa evaluasi ambang semester.
- Bulanan: evaluasi `late_minutes_monthly_limit`.
- Semester: evaluasi `total_violation_minutes_semester_limit` dan `alpha_days_semester_limit`.
- Tahunan: rekap agregat lintas semester (opsional untuk dashboard manajemen).

## 7) Aturan Notifikasi

Aturan notifikasi yang disepakati:

- Jika `alpha_days_semester_limit` terlewati:
  - kirim notifikasi ke wali kelas (jika aktif),
  - kirim notifikasi ke kesiswaan (jika aktif).
- Admin tidak mendapatkan notifikasi pop-up; cukup tercatat di histori/audit log.

## 8) Dampak UI Yang Direncanakan

Panel pengaturan absensi akan memiliki blok baru:

- Kartu A: Batas Disiplin Semester
- Kartu B: Batas Alpha Semester + toggle notifikasi
- Kartu C: Batas Keterlambatan Bulanan

Dashboard/rekap siswa akan menampilkan:

- status total pelanggaran semester,
- status alpha semester,
- status keterlambatan bulan berjalan.

## 9) Status Implementasi Dokumen Ini

Status saat ini: **implementasi inti selesai**.

Yang sudah diterapkan:

- field threshold baru sudah ditambahkan ke `attendance_settings`,
- endpoint global settings dan attendance schema sudah menerima field threshold baru,
- endpoint statistik absensi, rekap bulanan, dan laporan sudah membaca threshold baru,
- frontend web pengaturan absensi sudah memakai 3 indikator baru,
- frontend web laporan statistik sudah membaca status threshold bulanan/semester,
- mobile app rekap bulanan sudah membaca payload `discipline_thresholds`,
- command notifikasi otomatis `attendance:notify-discipline-thresholds` sudah dibuat,
- notifikasi internal ke wali kelas dan kesiswaan saat batas alpha semester terlewati sudah aktif,
- kontrol automation WhatsApp untuk alert threshold sudah tersedia di halaman `WhatsApp Gateway`,
- histori admin untuk kasus pelanggaran sudah tersedia di modul `Broadcast Message`,
- histori admin tersebut sudah bisa difilter berdasarkan status, nomor orang tua, dan pencarian siswa/kelas,
- histori admin tersebut sudah bisa difilter juga berdasarkan rentang tanggal trigger,
- histori admin tersebut sudah bisa diekspor ke CSV dan PDF,
- setiap kasus sudah menampilkan detail broadcast terakhir yang terhubung,
- halaman detail kasus pelanggaran tersendiri sudah tersedia untuk audit jejak alert internal dan broadcast orang tua,
- riwayat broadcast umum sudah bisa difilter berdasarkan kategori pesan, status kampanye, dan rentang tanggal,
- workflow broadcast lanjutan ke orang tua dari histori alert sudah aktif.

Yang belum diterapkan:

- notifikasi threshold non-alpha selain workflow alpha semester.

## 10) Source Of Truth Semester

Agar indikator disiplin semester konsisten lintas backend, frontend, dan mobile:

- Source of truth semester diambil dari tabel `tahun_ajaran`.
- Basis perhitungan periode memakai:
  - `tahun_ajaran.status = active`
  - `tanggal_mulai` dan `tanggal_selesai`
  - `semester` (`ganjil` / `genap` / `full`)

Catatan penting kondisi saat ini:

- Laporan endpoint `attendance/semester` masih menerima input manual `tahun` + `semester`.
- Mapping semester pada endpoint laporan saat ini berbeda dengan helper semester di model `TahunAjaran`.

Rekomendasi kebijakan teknis:

1. Rekap disiplin semester wajib mengacu ke `tahun_ajaran` aktif, bukan input manual semester.
2. Jika tidak ada tahun ajaran aktif, endpoint mengembalikan error bisnis yang jelas (misalnya: "tahun ajaran aktif belum ditetapkan").
3. Endpoint laporan tetap boleh menerima filter manual hanya untuk kebutuhan audit/arsip, tetapi default UI harus otomatis memakai tahun ajaran aktif.

## 11) Rekomendasi Alur Broadcast Notifikasi Pelanggaran

Kebutuhan operasional:

- Saat ambang terlampaui, wali kelas dan kesiswaan harus tahu lebih dulu.
- Humas meneruskan komunikasi resmi ke orang tua.

Alur yang direkomendasikan:

1. Trigger sistem membuat event pelanggaran ambang (alpha semester, terlambat bulanan, total menit semester).
2. Event masuk antrean `discipline_alerts` (status: `pending_internal`).
3. Sistem kirim notifikasi internal (in-app) ke:
   - wali kelas siswa terkait
   - role kesiswaan
4. Setelah diverifikasi internal, status event jadi `ready_for_parent_broadcast`.
5. Humas membuka halaman Broadcast, pilih event, cek template, lalu kirim WA ke orang tua.
6. Semua pengiriman dan kegagalan tercatat di histori/audit log.

## 12) Catatan Integrasi Dengan Sistem Yang Sudah Ada

Yang sudah tersedia saat ini:

- Endpoint in-app broadcast: `/api/notifications/broadcast`
- Endpoint WA broadcast: `/api/whatsapp/broadcast`
- Service WA notifikasi ke nomor orang tua dari data siswa sudah ada.

Gap yang perlu ditutup:

1. Belum ada halaman khusus Broadcast Operasional (masih konfigurasi + test).
2. Belum ada workflow status event (`pending_internal` -> `ready_for_parent_broadcast` -> `sent`).
3. Role `WAKASEK_HUMAS` saat ini belum punya permission `manage_whatsapp`.

Rekomendasi implementasi bertahap:

1. Tambah modul `Broadcast Message` (list event, filter kelas, preview template, kirim massal, retry gagal).
2. Tambah tabel event broadcast disiplin (id siswa, jenis pelanggaran, periode, status, approved_by, sent_by, sent_at).
3. Tambah permission granular:
   - `view_discipline_alerts`
   - `approve_discipline_alerts`
   - `send_parent_broadcast`
4. Beri `send_parent_broadcast` ke HUMAS, dan `approve_discipline_alerts` ke Kesiswaan.
5. Tambah validasi data kontak orang tua sebelum event bisa dikirim.

## 13) Hardening Yang Disetujui

Seluruh rekomendasi audit disetujui untuk diterapkan.

Ruang lingkup hardening:

1. Samakan rumus keterlambatan di semua modul.
   - Perhitungan `terlambat_menit` harus memakai `jam_masuk + toleransi`.
   - Source of truth harus sama di:
     - rekap bulanan,
     - statistik absensi,
     - laporan,
     - engine threshold,
     - alert otomatis.

2. Aktif/nonaktif threshold baru harus eksplisit.
   - Sistem tidak lagi menebak mode baru dari field nullable.
   - Mode threshold baru harus dikontrol oleh flag eksplisit pada schema absensi.

3. Status tiap indikator harus eksplisit: monitoring atau alert.
   - `late_minutes_monthly_limit`
   - `total_violation_minutes_semester_limit`
   - `alpha_days_semester_limit`
   Setiap indikator harus bisa ditandai:
   - `monitor_only`
   - `alertable`

4. Routing alert internal harus bisa diatur per indikator.
   - Toggle penerima untuk:
     - wali kelas
     - kesiswaan
   - Tidak hanya berlaku untuk alpha semester.

5. Histori perhitungan harus lebih stabil.
   - Engine pelanggaran harus memakai `settings_snapshot` jika tersedia.
   - Auto alpha dan absensi manual baru wajib menyimpan:
     - `attendance_setting_id`
     - `settings_snapshot`
   - Snapshot minimal membekukan:
     - jam masuk
     - jam pulang
     - toleransi
     - hari kerja
     - kelas/tahun ajaran saat dicatat

6. Case dan alert pelanggaran harus generik lintas periode.
   - Bukan hanya semester alpha.
   - Sistem harus membedakan:
     - kasus bulanan
     - kasus semester
   - Deduplikasi tidak boleh lagi hanya berbasis `semester`.

7. Health-check absensi harus mendeteksi scheduler yang stale.
   - Auto alpha
   - Alert threshold
   Jika job tidak jalan sesuai jadwal, panel admin harus memunculkan warning.

8. Wording web/mobile harus sesuai automasi nyata.
   - Jika indikator hanya monitoring, UI tidak boleh memberi kesan ada notifikasi otomatis.
   - Jika indikator alertable dan terpicu, baru tampil target notifikasi yang benar.

9. Regression test harus ditambah.
   - threshold dengan toleransi > 0,
   - konsistensi report vs threshold engine,
   - alert bulanan/semester non-alpha,
   - snapshot record auto alpha/manual,
   - health state scheduler.

## 14) Target Implementasi Hardening

Target hasil sesudah hardening:

1. Angka pelanggaran konsisten di report, rekap, statistik, dan alert.
2. Admin bisa memilih indikator mana yang hanya dipantau dan mana yang memicu alert.
3. Alert otomatis tidak lagi terbatas ke alpha semester.
4. Histori kasus pelanggaran bisa memuat:
   - keterlambatan bulanan,
   - total pelanggaran semester,
   - alpha semester.
5. Broadcast orang tua tetap memakai workflow yang sama, tetapi sumber kasusnya menjadi generik.
6. Panel pengaturan absensi dan mobile recap tidak lagi menampilkan wording yang menyesatkan.

## 15) Status Dokumen

Status saat ini: **hardening diimplementasikan dan tervalidasi di backend/web**.

Yang sudah diterapkan:

1. Perhitungan keterlambatan sudah disatukan memakai `jam_masuk + toleransi`.
2. Aktivasi threshold baru memakai flag eksplisit `discipline_thresholds_enabled`.
3. Tiga indikator threshold sekarang punya mode eksplisit:
   - `monitor_only`
   - `alertable`
4. Routing alert internal bisa diatur per indikator untuk:
   - wali kelas
   - kesiswaan
5. Snapshot absensi baru menyimpan konteks schema/working hours untuk menjaga stabilitas histori.
6. Case dan alert pelanggaran sudah generik lintas periode:
   - bulanan
   - semester
7. Health-check admin sudah mendeteksi automation scheduler yang stale.
8. Wording web dan mobile sudah disesuaikan agar hanya menampilkan target notifikasi untuk indikator yang benar-benar `alertable`.
9. Workflow notifikasi otomatis dan kasus admin sudah berjalan untuk:
   - keterlambatan bulanan
   - total pelanggaran semester
   - alpha semester
10. Jam automasi operasional sekarang bisa diatur dari pengaturan absensi global untuk:
   - auto alpha siswa
   - evaluasi alert threshold pelanggaran
11. Pengaturan operasional tambahan sekarang juga bisa diatur dari pengaturan absensi global untuk:
   - retensi history live tracking
   - jam cleanup live tracking
   - kebijakan fallback verifikasi wajah:
     - hasil saat template wajah belum tersedia
     - redirect reject ke manual review
     - skip verifikasi saat foto tidak tersedia

Yang masih perlu tindak lanjut operasional:

1. Jalankan migration terbaru di server.
2. Build ulang mobile app agar perubahan wording dan banner recap ikut aktif di perangkat pengguna.
3. Pastikan Laravel scheduler aktif di server agar:
   - `attendance:mark-student-alpha`
   - `attendance:notify-discipline-thresholds`
   benar-benar berjalan sesuai jadwal.

## 16) Audit Sumber Pengaturan Absensi

Status umum saat ini:

- Pengaturan pelanggaran dan jam automasi **sudah DB-driven** melalui `attendance_settings`.
- File `.env` / `config/attendance.php` masih dipakai sebagai **default/fallback**, bukan source utama, untuk setting runtime yang baru dipindahkan.
- Beberapa area teknis memang **masih sengaja config-driven** karena lebih aman dikontrol dari server daripada dari panel admin.

### 16.1 Yang Sudah DB-Driven

| Area | Setting | Source utama saat ini | Fallback | Keterangan |
| --- | --- | --- | --- | --- |
| Threshold disiplin | `total_violation_minutes_semester_limit` | `attendance_settings` | default model/controller | Diatur dari panel pengaturan absensi |
| Threshold disiplin | `alpha_days_semester_limit` | `attendance_settings` | default model/controller | Diatur dari panel pengaturan absensi |
| Threshold disiplin | `late_minutes_monthly_limit` | `attendance_settings` | default model/controller | Diatur dari panel pengaturan absensi |
| Mode indikator | `discipline_thresholds_enabled` | `attendance_settings` | default model/controller | Flag eksplisit threshold v2 |
| Mode indikator | `semester_total_violation_mode` | `attendance_settings` | default model/controller | `monitor_only` / `alertable` |
| Mode indikator | `semester_alpha_mode` | `attendance_settings` | default model/controller | `monitor_only` / `alertable` |
| Mode indikator | `monthly_late_mode` | `attendance_settings` | default model/controller | `monitor_only` / `alertable` |
| Routing alert | `notify_wali_kelas_on_total_violation_limit` | `attendance_settings` | default model/controller | Diatur per indikator |
| Routing alert | `notify_kesiswaan_on_total_violation_limit` | `attendance_settings` | default model/controller | Diatur per indikator |
| Routing alert | `notify_wali_kelas_on_late_limit` | `attendance_settings` | default model/controller | Diatur per indikator |
| Routing alert | `notify_kesiswaan_on_late_limit` | `attendance_settings` | default model/controller | Diatur per indikator |
| Routing alert | `notify_wali_kelas_on_alpha_limit` | `attendance_settings` | default model/controller | Diatur per indikator |
| Routing alert | `notify_kesiswaan_on_alpha_limit` | `attendance_settings` | default model/controller | Diatur per indikator |
| Automasi harian | `auto_alpha_enabled` | `attendance_settings` | `config/attendance.php` | Scheduler baca DB lebih dulu |
| Automasi harian | `auto_alpha_run_time` | `attendance_settings` | `config/attendance.php` | Scheduler baca DB lebih dulu |
| Automasi harian | `discipline_alerts_enabled` | `attendance_settings` | `config/attendance.php` | Scheduler baca DB lebih dulu |
| Automasi harian | `discipline_alerts_run_time` | `attendance_settings` | `config/attendance.php` | Scheduler baca DB lebih dulu |
| Live tracking operasional | `live_tracking_retention_days` | `attendance_settings` | `config/attendance.php` | Cleanup command baca DB lebih dulu |
| Live tracking operasional | `live_tracking_cleanup_time` | `attendance_settings` | `config/attendance.php` | Scheduler cleanup baca DB lebih dulu |
| Kebijakan face verification | `face_verification_enabled` | `attendance_settings` | `config/attendance.php` | Toggle utama, mobile app membaca payload efektif |
| Kebijakan face verification | `face_result_when_template_missing` | `attendance_settings` | `config/attendance.php` | Diatur dari panel global absensi |
| Kebijakan face verification | `face_reject_to_manual_review` | `attendance_settings` | `config/attendance.php` | Diatur dari panel global absensi |
| Kebijakan face verification | `face_skip_when_photo_missing` | `attendance_settings` | `config/attendance.php` | Diatur dari panel global absensi |
| Scope kebijakan | `attendance_scope` | `attendance_settings` | hard lock controller | Saat ini dikunci `siswa_only` |
| Target kebijakan | `target_tingkat_ids` | `attendance_settings` | null | Diatur dari panel pengaturan absensi |
| Target kebijakan | `target_kelas_ids` | `attendance_settings` | null | Diatur dari panel pengaturan absensi |
| Verifikasi absensi | `verification_mode` | `attendance_settings` | `config/attendance.php` untuk beberapa health summary | Diatur dari panel pengaturan absensi |

### 16.2 Yang Masih Config / Env-Driven

| Area | Setting | Source saat ini | Dipakai oleh | Rekomendasi |
| --- | --- | --- | --- | --- |
| QR attendance | `ATTENDANCE_QR_ENABLED` | `.env` / `config/attendance.php` | `QRCodeController` | Tetap config-driven. Ini feature flag teknis. |
| Face verification | `ATTENDANCE_FACE_ENABLED` | `.env` / `config/attendance.php` | service + job face | Tetap config-driven. Dampaknya lintas queue dan service. |
| Face verification | `ATTENDANCE_FACE_MODE_DEFAULT` | `.env` / `config/attendance.php` | health summary + fallback schema | Bisa tetap config-driven. Kalau dipindah ke UI harus diselaraskan dengan schema policy. |
| Face verification | `ATTENDANCE_FACE_QUEUE` | `.env` / `config/attendance.php` | queue job | Tetap config-driven. Ini deployment concern. |
| Face verification | `ATTENDANCE_FACE_THRESHOLD` | `.env` / `config/attendance.php` | face service | Bisa dipindah ke UI hanya jika sekolah memang sering tuning threshold. Jika tidak, lebih aman tetap di config. |
| Face verification | `ATTENDANCE_FACE_ENGINE_VERSION` | `.env` / `config/attendance.php` | health summary + service | Tetap config-driven. Ini metadata engine/deployment. |
| Live tracking | `LIVE_TRACKING_STALE_SECONDS` | `.env` / `config/attendance.php` | snapshot/controller | Tetap config-driven. Ini runtime teknis. |
| Live tracking | `LIVE_TRACKING_SNAPSHOT_EXPIRE_HOURS_AFTER_MIDNIGHT` | `.env` / `config/attendance.php` | snapshot service | Tetap config-driven. |
| Live tracking | `LIVE_TRACKING_GPS_*` | `.env` / `config/attendance.php` | quality classification | Tetap config-driven. Ini tuning teknis. |

### 16.3 Kesimpulan Operasional

Kesimpulan kondisi sekarang:

1. Untuk **pelanggaran absensi**, source utama sudah pindah ke pengaturan database.
2. Untuk **jam automasi auto alpha dan alert threshold**, source utama juga sudah pindah ke pengaturan database.
3. Untuk **retensi/cleanup live tracking** dan **kebijakan fallback verifikasi wajah**, source utama juga sudah pindah ke pengaturan database.
4. `.env` masih diperlukan sebagai fallback aman saat:
   - migration belum dijalankan,
   - row global setting belum terbentuk,
   - atau nilai runtime belum terisi.
5. Tidak semua setting attendance perlu dipindah ke UI.
   - Setting yang menyentuh queue, engine, feature flag teknis, dan tuning runtime rendah sebaiknya tetap di config.
   - Setting yang benar-benar operasional untuk admin sekolah layak dipindah ke UI.

### 16.4 Rekomendasi Tahap Berikutnya

Prioritas rekomendasi:

1. **Pertahankan di UI**:
   - threshold disiplin,
   - mode alert,
   - routing notifikasi,
   - jam automasi auto alpha,
   - jam automasi evaluasi threshold,
   - retensi/cleanup live tracking,
   - kebijakan fallback verifikasi wajah.

2. **Sebaiknya tetap di config/env**:
   - queue face verification,
   - engine version,
   - QR feature flag,
   - stale window live tracking,
   - GPS quality thresholds,
   - setting teknis lain yang lebih dekat ke deployment daripada kebijakan sekolah.

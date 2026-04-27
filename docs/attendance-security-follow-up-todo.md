# Attendance Security Follow-Up TODO

Dokumen ini adalah TODO implementasi untuk menutup gap operasional setelah sistem anti-fraud dan security event mencatat indikasi keamanan absensi.

Fokus dokumen:

- Menindaklanjuti siswa yang punya indikasi keamanan.
- Mengumpulkan dan mengunci bukti agar tidak mudah dibantah.
- Membuat data terbaca per siswa, bukan terasa global/bertumpuk.
- Membedakan presensi masuk dan pulang.
- Menjaga monitoring tetap praktis untuk wali kelas, wakasek kesiswaan, dan admin.

## Prinsip Desain

- Data mentah tetap disimpan sebagai evidence teknis, tidak langsung dianggap pelanggaran.
- Satu siswa dapat punya banyak event, tetapi UI harus mengelompokkan event itu menjadi riwayat yang mudah dibaca.
- Tindak lanjut harus punya status, PIC, catatan, lampiran, dan audit trail.
- `attempt_type` wajib dipakai untuk membedakan `masuk` dan `pulang`.
- `source` atau `stage` wajib dipakai untuk membedakan `attendance_precheck` dan `attendance_submit`.
- Bukti teknis tidak boleh hilang walau kasus sudah ditutup.
- False positive harus bisa dicatat tanpa menghapus event asli.

## Data Yang Sudah Ada

Backend sudah punya sumber bukti berikut:

- `attendance_fraud_assessments`
  - `user_id`
  - `attendance_id`
  - `kelas_id`
  - `assessment_date`
  - `source`
  - `attempt_type`
  - `validation_status`
  - `decision_code`
  - `decision_reason`
  - `recommended_action`
  - `latitude`
  - `longitude`
  - `accuracy`
  - `distance_meters`
  - `device_id`
  - `device_fingerprint`
  - `ip_address`
  - `request_nonce`
  - `request_timestamp`
  - `client_timestamp`
  - `raw_payload`
  - `normalized_payload`
  - `metadata`

- `attendance_security_events`
  - `user_id`
  - `attendance_id`
  - `kelas_id`
  - `category`
  - `event_key`
  - `severity`
  - `status`
  - `attempt_type`
  - `event_date`
  - `latitude`
  - `longitude`
  - `accuracy`
  - `distance_meters`
  - `device_id`
  - `ip_address`
  - `metadata`

## TODO 1 - Case Management Tindak Lanjut

### Backend

- Buat tabel `attendance_security_cases`.
- Field minimal:
  - `id`
  - `case_number`
  - `user_id`
  - `kelas_id`
  - `opened_by`
  - `assigned_to`
  - `status`
  - `priority`
  - `case_date`
  - `summary`
  - `student_statement`
  - `staff_notes`
  - `resolution`
  - `resolved_by`
  - `resolved_at`
  - `created_at`
  - `updated_at`

- Status minimal:
  - `new`
  - `in_review`
  - `waiting_student_clarification`
  - `confirmed_violation`
  - `false_positive`
  - `resolved`
  - `escalated`

- Priority minimal:
  - `low`
  - `medium`
  - `high`
  - `critical`

- Buat tabel pivot `attendance_security_case_items`.
- Relasi item harus bisa menunjuk ke:
  - `attendance_fraud_assessment`
  - `attendance_security_event`
  - `absensi`

- Buat tabel `attendance_security_case_activities`.
- Activity minimal:
  - status changed
  - note added
  - evidence uploaded
  - student clarification recorded
  - case resolved
  - case reopened

### API

- `GET /api/attendance-security-cases`
- `POST /api/attendance-security-cases`
- `GET /api/attendance-security-cases/{case}`
- `PATCH /api/attendance-security-cases/{case}`
- `POST /api/attendance-security-cases/{case}/items`
- `POST /api/attendance-security-cases/{case}/notes`
- `POST /api/attendance-security-cases/{case}/evidence`
- `POST /api/attendance-security-cases/{case}/resolve`
- `POST /api/attendance-security-cases/{case}/reopen`

### Access Control

- Wali kelas hanya boleh melihat kasus siswa di kelas yang bisa dia akses.
- Wakasek kesiswaan boleh melihat semua kelas aktif.
- Super admin boleh melihat semua kasus.
- Siswa tidak boleh melihat evidence internal, kecuali nanti dibuat portal klarifikasi khusus.

## TODO 2 - Evidence/Bukti

### Backend

- Buat tabel `attendance_security_case_evidence`.
- Field minimal:
  - `case_id`
  - `uploaded_by`
  - `evidence_type`
  - `title`
  - `description`
  - `file_disk`
  - `file_path`
  - `file_original_name`
  - `file_mime_type`
  - `file_size_bytes`
  - `checksum_sha256`
  - `metadata`

- Evidence type minimal:
  - `system_snapshot`
  - `student_statement`
  - `staff_note`
  - `screenshot`
  - `photo`
  - `document`
  - `other`

- Saat case dibuat dari event/assessment, simpan snapshot bukti teknis ke evidence `system_snapshot`.
- Snapshot tidak boleh hanya referensi ID, karena data relasi bisa berubah.
- Snapshot minimal berisi:
  - waktu server
  - nama siswa dan identitas saat itu
  - kelas saat itu
  - jenis presensi
  - tahap pemeriksaan
  - issue/flag
  - device
  - lokasi
  - akurasi
  - jarak dari geofence
  - IP
  - request nonce/timestamp jika tersedia

### Frontend

- Di detail kasus tampilkan panel "Bukti Sistem".
- Di detail kasus tampilkan panel "Lampiran dan Klarifikasi".
- Tampilkan hash/checksum untuk file evidence agar bukti tidak mudah diperdebatkan.
- Tambahkan export PDF/CSV ringkas kasus untuk kebutuhan rapat/klarifikasi.

## TODO 3 - Per Siswa, Bukan Global Bertumpuk

### Backend

- Tambahkan endpoint ringkasan per siswa:
  - `GET /api/monitoring-kelas/kelas/{id}/security-students`
  - `GET /api/monitoring-kelas/kelas/{id}/security-students/{user}`

- Response list per siswa minimal:
  - `user_id`
  - `student_name`
  - `student_identifier`
  - `kelas`
  - `total_events`
  - `total_assessments`
  - `warning_count`
  - `security_event_count`
  - `masuk_warning_count`
  - `pulang_warning_count`
  - `precheck_warning_count`
  - `submit_warning_count`
  - `highest_severity`
  - `last_seen_at`
  - `open_case_count`
  - `resolved_case_count`
  - `latest_recommendation`

- Detail per siswa minimal:
  - profil siswa
  - ringkasan risiko
  - timeline gabungan assessment, security event, dan absensi
  - daftar kasus terbuka/selesai
  - filter tanggal
  - filter masuk/pulang
  - filter tahap precheck/submit

### Frontend

- Monitoring kelas tidak hanya tabel event global.
- Tambahkan tab:
  - `Ringkasan Siswa`
  - `Event Keamanan`
  - `Fraud Assessment`
  - `Kasus Tindak Lanjut`

- `Ringkasan Siswa` menjadi default untuk wali kelas.
- Klik siswa membuka drawer/detail timeline.
- Timeline per siswa harus mengelompokkan kejadian berdasarkan tanggal dan jenis presensi.
- Jangan tampilkan 30 event siswa yang sama sebagai 30 baris global tanpa grouping.

## TODO 4 - Masuk vs Pulang

### Backend

- Pastikan semua query monitoring menerima filter:
  - `attempt_type=masuk`
  - `attempt_type=pulang`

- Endpoint yang perlu dipastikan:
  - fraud assessment admin
  - fraud assessment monitoring kelas
  - security event admin
  - security event monitoring kelas
  - summary fraud
  - summary security
  - export fraud
  - export security

- Summary wajib menampilkan:
  - `masuk_events`
  - `pulang_events`
  - `masuk_warnings`
  - `pulang_warnings`
  - `masuk_blocked`
  - `pulang_blocked`

### Frontend

- Tambahkan filter/chip:
  - `Semua`
  - `Masuk`
  - `Pulang`

- Tabel wajib punya kolom/chip:
  - `Jenis Presensi`
  - `Tahap`

- Label kombinasi:
  - `Masuk - Pra-cek aplikasi`
  - `Masuk - Submit presensi`
  - `Pulang - Pra-cek aplikasi`
  - `Pulang - Submit presensi`

- Export CSV wajib menambahkan kolom:
  - `Jenis Presensi`
  - `Tahap Pemeriksaan`

## TODO 5 - Workflow Operasional

- Wali kelas membuka Monitoring Kelas.
- Sistem menampilkan siswa yang paling perlu ditindaklanjuti, bukan event mentah dulu.
- Wali kelas klik siswa.
- Wali kelas melihat timeline dan bukti teknis.
- Wali kelas membuat kasus tindak lanjut atau menambahkan ke kasus yang sudah ada.
- Siswa dipanggil/klarifikasi.
- Wali kelas mencatat pernyataan siswa.
- Jika perlu, unggah dokumen/foto/screenshot.
- Wali kelas memilih hasil:
  - false positive
  - perlu pembinaan
  - pelanggaran terkonfirmasi
  - eskalasi ke kesiswaan
- Kasus ditutup dengan audit trail.

## TODO 6 - Audit Trail dan Integritas

- Semua perubahan kasus harus dicatat.
- Catat:
  - `actor_id`
  - aksi
  - nilai sebelum
  - nilai sesudah
  - waktu server
  - IP
  - user agent

- Evidence file harus punya `checksum_sha256`.
- Evidence tidak boleh hard delete secara default.
- Gunakan soft delete untuk case dan evidence jika diperlukan.
- Tambahkan permission khusus:
  - `view_attendance_security_cases`
  - `manage_attendance_security_cases`
  - `resolve_attendance_security_cases`
  - `export_attendance_security_cases`

## TODO 7 - UI Prioritas

- Gunakan badge risiko, bukan hanya warna.
- Urutan default:
  - critical/high severity
  - kasus belum ditindaklanjuti
  - kejadian berulang
  - kejadian terbaru

- Hindari tampilan terlalu global:
  - global summary tetap ada untuk wakasek/admin
  - wali kelas default ke daftar siswa
  - event mentah tetap tersedia sebagai tab audit

- Tampilkan kartu ringkas:
  - siswa perlu klarifikasi
  - siswa berulang
  - mock location
  - device/app integrity
  - outside geofence
  - masuk vs pulang

## Acceptance Criteria

- Wali kelas bisa melihat daftar siswa yang perlu tindak lanjut tanpa membaca tabel event global satu per satu.
- Setiap warning bisa dibuat menjadi kasus.
- Setiap kasus punya bukti sistem snapshot.
- Setiap kasus bisa ditutup dengan hasil dan catatan.
- Satu siswa punya timeline gabungan yang rapi.
- Masuk dan pulang dapat difilter, diringkas, dan diexport terpisah.
- Export kasus cukup kuat untuk rapat klarifikasi.
- Tidak ada penghapusan bukti tanpa jejak audit.

## Prioritas Implementasi

1. Backend case management dan evidence snapshot.
2. Endpoint ringkasan per siswa.
3. Filter/summary `attempt_type` untuk security events kelas.
4. UI tab `Ringkasan Siswa` dan drawer timeline.
5. UI kasus tindak lanjut dan upload evidence.
6. Export kasus.
7. Audit trail lanjutan dan permission granular.

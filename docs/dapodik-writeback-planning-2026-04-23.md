# Rencana Kirim Data SIAPS ke Dapodik

Tanggal: 2026-04-23

## Tujuan

Dokumen ini merancang jalur kerja agar data dari SIAPS bisa disiapkan untuk dikirim kembali ke Dapodik dengan aman. Fokus awal bukan langsung menulis ke database Dapodik, tetapi membangun proses matching, audit, preview, konfirmasi, dan log perubahan.

Target utama:

- Mengurangi input manual operator Dapodik.
- Memastikan data yang dikirim sudah cocok dengan data Dapodik saat ini.
- Mencegah update salah orang, salah rombel, atau salah relasi.
- Menyediakan audit trail sebelum dan sesudah perubahan.

## Prinsip Utama

1. Dapodik tetap dianggap sebagai sistem referensi resmi untuk data yang wajib sinkron pusat.
2. SIAPS boleh menjadi sumber usulan/update operasional setelah data divalidasi.
3. Semua proses kirim harus dimulai dari snapshot Dapodik terbaru.
4. Data yang hanya cocok dari nama tidak boleh dikirim otomatis.
5. Data yang dikirim otomatis hanya data dengan status `exact`.
6. Semua operasi harus punya mode `preview/dry-run`.
7. Direct write ke database Dapodik hanya boleh dipertimbangkan setelah ada uji clone, backup, mapping tabel lengkap, dan prosedur rollback.

## Ruang Lingkup Awal

Data yang bisa direncanakan untuk tahap awal:

- Siswa: identitas dasar, NIS, NISN, NIK, kontak, data orang tua/wali.
- GTK/pegawai: NIP, NUPTK, NIK, kontak, jenis/status kepegawaian.
- Rombel/kelas: nama rombel, tingkat, jurusan, wali kelas.
- Anggota rombel: relasi siswa ke rombel per tahun ajaran/semester.

Data yang tidak boleh masuk tahap awal:

- Penghapusan data Dapodik.
- Perubahan ID internal Dapodik.
- Perubahan struktur referensi yang belum dipetakan.
- Update massal tanpa preview dan konfirmasi.
- Write langsung ke tabel yang relasinya belum dipahami penuh.

## Arsitektur Alur

Alur aman yang diinginkan:

1. Ambil snapshot Dapodik terbaru.
2. Ambil data SIAPS yang akan dibandingkan.
3. Jalankan matching SIAPS vs Dapodik.
4. Hitung diff per entitas.
5. Tampilkan preview perubahan.
6. Operator memilih data yang akan diproses.
7. Sistem menjalankan validasi akhir.
8. Kirim atau export data sesuai mode yang dipilih.
9. Simpan log hasil proses.
10. Ambil ulang snapshot Dapodik untuk verifikasi hasil.

Mode pengiriman yang perlu disediakan:

- `audit_only`: hanya menampilkan perbedaan.
- `export_package`: menghasilkan Excel/CSV/paket data untuk operator.
- `official_api`: memakai endpoint write resmi jika tersedia.
- `direct_db_lab`: uji tulis ke clone database Dapodik.
- `direct_db_production`: opsi terakhir, hanya jika semua kontrol keamanan terpenuhi.

## Matching Data

### Siswa

Urutan matching:

1. `peserta_didik_id` Dapodik yang sudah pernah mapping exact.
2. `NISN` unik.
3. `NIK` unik.
4. `NIS/NIPD` unik.
5. `nama + tanggal_lahir` sebagai `probable`, bukan otomatis.

Status:

- `exact`: aman untuk preview kirim.
- `probable`: perlu review manual.
- `conflict`: ada lebih dari satu kandidat atau role tidak sesuai.
- `unmatched`: belum ada pasangan Dapodik.

Aturan:

- NIS/NISN harus tetap ditampilkan di semua preview.
- Perbedaan nama tidak otomatis memblokir jika NISN/NIS exact.
- Data yang hanya cocok dari nama wajib review manual.

### GTK/Pegawai

Urutan matching:

1. `ptk_id` Dapodik yang sudah pernah mapping exact.
2. `NIP` unik.
3. `NUPTK` unik.
4. `NIK` unik.
5. `nama + tanggal_lahir` sebagai `probable`.

Aturan:

- Role siswa vs pegawai wajib divalidasi.
- Kepala sekolah, admin, dan role sensitif tidak boleh diubah otomatis.
- Jika NIP/NUPTK kosong, jangan mengandalkan nama saja untuk write otomatis.

### Kelas/Rombel

Urutan matching:

1. `rombongan_belajar_id` Dapodik yang sudah pernah mapping exact.
2. `nama_rombel + tahun_ajaran/semester aktif`.
3. `tingkat + jurusan + nama_rombel`.

Aturan:

- Kelas tahun ajaran baru harus diperlakukan sebagai konteks baru.
- Jangan menimpa histori kelas tahun sebelumnya.
- Wali kelas hanya boleh diisi jika GTK wali sudah exact.

### Anggota Rombel

Matching anggota rombel harus memenuhi:

- Rombel exact.
- Siswa exact.
- Tahun ajaran/semester target jelas.
- Tidak ada konflik aktif di rombel lain untuk konteks yang sama.

Aturan:

- Relasi anggota rombel tidak boleh dibuat dari nama siswa saja.
- NIS/NISN siswa harus ditampilkan di preview.
- Perpindahan kelas dalam tahun ajaran yang sama harus masuk jalur mutasi/review, bukan update diam-diam.

## Preview Perubahan

Setiap item preview harus menampilkan:

- Data SIAPS saat ini.
- Data Dapodik saat ini.
- Field yang berbeda.
- Nilai lama.
- Nilai baru.
- Alasan status matching.
- Risiko atau blocker.
- Rekomendasi aksi.

Contoh aksi:

- `no_change`: tidak ada perubahan.
- `update_candidate`: bisa dikirim setelah konfirmasi.
- `create_candidate`: data SIAPS belum ada di Dapodik, perlu review.
- `manual_review`: perlu keputusan operator.
- `blocked`: tidak boleh diproses.

## Konfirmasi dan Kunci Proses

Sebelum proses kirim/export berjalan:

- Operator harus memilih item yang akan diproses.
- Sistem menampilkan popup konfirmasi.
- Popup menampilkan jumlah data per kategori.
- Tombol proses harus dikunci selama eksekusi.
- Progress proses harus tampil per tahap.

Tahap progress minimal:

1. Validasi pilihan.
2. Ambil ulang snapshot pembanding.
3. Hitung diff final.
4. Siapkan payload/export.
5. Kirim atau generate file.
6. Catat log.
7. Verifikasi hasil.

## Strategi Export Paket Data

Tahap paling aman untuk implementasi awal adalah export paket data, bukan direct write.

Format yang disarankan:

- Excel per entitas.
- Sheet `Ringkasan`.
- Sheet `Siswa`.
- Sheet `GTK`.
- Sheet `Rombel`.
- Sheet `Anggota Rombel`.
- Sheet `Blocked`.
- Sheet `Manual Review`.

Kolom wajib:

- Status matching.
- ID SIAPS.
- ID Dapodik.
- Nama.
- NIS/NISN/NIK untuk siswa.
- NIP/NUPTK/NIK untuk GTK.
- Rombel/tahun ajaran untuk kelas.
- Field yang berubah.
- Nilai SIAPS.
- Nilai Dapodik.
- Catatan validasi.

## Opsi Direct DB Dapodik

Direct DB hanya boleh masuk tahap riset/lab lebih dulu.

Syarat minimum:

- Backup database Dapodik sebelum proses.
- Uji di clone database Dapodik.
- Mapping tabel dan relasi sudah terdokumentasi.
- Transaksi database wajib atomic.
- Tidak ada delete pada tahap awal.
- Ada log before/after.
- Ada batas maksimal batch.
- Ada tombol dry-run.
- Ada verifikasi setelah write.
- Ada prosedur rollback manual.

Risiko utama:

- Struktur database Dapodik berubah antar versi.
- Validasi aplikasi Dapodik bisa terlewati.
- Data lokal terlihat berubah tetapi gagal sinkron pusat.
- Salah relasi ID bisa merusak rombel atau anggota rombel.

## Log dan Audit

Setiap proses harus mencatat:

- ID batch.
- Operator.
- Waktu mulai dan selesai.
- Mode proses.
- Entitas yang diproses.
- Jumlah sukses, gagal, blocked, skipped.
- Payload ringkas.
- Before/after value.
- Error detail.
- Snapshot Dapodik yang dipakai.

Log harus bisa dibuka ulang dari UI.

## Tahapan Implementasi Yang Disarankan

### Tahap 1: Audit SIAPS vs Dapodik

Hasil:

- Menu audit perbedaan.
- Matching exact/probable/conflict/unmatched.
- Detail field yang berbeda.
- Filter dan pencarian.

Belum ada kirim data.

### Tahap 2: Export Paket Data

Hasil:

- Export Excel/CSV.
- Dipakai operator sebagai bahan input manual/semimanual.
- Data blocked dan manual review ikut diekspor.

Belum ada write otomatis.

### Tahap 3: Write Adapter Abstraction

Hasil:

- Interface internal untuk target pengiriman.
- Target awal: file export.
- Target lanjutan: official API jika tersedia.
- Target lab: direct DB clone.

Belum ke production direct DB.

### Tahap 4: Direct DB Lab

Hasil:

- Uji tulis ke clone Dapodik.
- Verifikasi dengan aplikasi Dapodik lokal.
- Verifikasi sinkron pusat jika memungkinkan di lingkungan aman.

### Tahap 5: Production Write Dengan Batasan Ketat

Hanya jika tahap lab terbukti aman.

Pembatasan awal:

- Tidak ada delete.
- Hanya field whitelist.
- Hanya data exact.
- Maksimal batch kecil.
- Wajib backup.
- Wajib konfirmasi.
- Wajib verifikasi ulang.

## Pertanyaan Terbuka

- Apakah ada endpoint write resmi Dapodik untuk data yang dibutuhkan?
- Data apa yang paling sering membuat operator input manual lama?
- Apakah target awal cukup export paket data, atau butuh direct DB lab?
- Versi Dapodik production yang dipakai sekolah saat ini apa?
- Apakah server SIAPS bisa menjangkau mesin Dapodik lokal saat mode write dibutuhkan?
- Siapa operator yang berwenang menyetujui perubahan?

## Rekomendasi Saat Ini

Prioritas implementasi nanti:

1. Buat menu `Audit Perbedaan SIAPS vs Dapodik`.
2. Buat export paket data untuk operator.
3. Dokumentasikan struktur DB Dapodik dari clone/lab, bukan production.
4. Baru evaluasi opsi kirim langsung.

Dengan pendekatan ini, SIAPS tetap bisa mengurangi pekerjaan manual, tetapi risiko kerusakan data Dapodik bisa dikontrol bertahap.

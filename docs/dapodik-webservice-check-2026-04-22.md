# Cek Web Service Dapodik

Tanggal cek: 2026-04-22

## Parameter

- Base URL: `http://182.253.36.196:5885`
- NPSN: `20214989`
- Token: disamarkan, tidak ditulis ke dokumentasi
- Header autentikasi yang dicoba: `Authorization: Bearer <token>`
- Query yang dicoba: `?npsn=20214989`

## Hasil Cek Langsung Dari Workspace

Pengecekan ulang dari workspace ini berhasil mengambil data dari endpoint Dapodik dengan NPSN `20214989`.

Catatan teknis: endpoint valid tetap mengembalikan `content-type: text/html; charset=UTF-8`, tetapi isi respons berupa payload JSON Dapodik. Parser sebaiknya tetap membaca body sebagai teks lalu decode JSON dari isi respons.

## Endpoint Yang Dicek

| Endpoint | Hasil Dari Workspace | Catatan |
| --- | --- | --- |
| `/` | HTTP 200, HTML | Halaman/root service, bukan JSON data |
| `/WebService/getSekolah?npsn=20214989` | HTTP 200, JSON body | `results: 1`, data sekolah |
| `/WebService/getPesertaDidik?npsn=20214989` | HTTP 200, JSON body | `results: 1444`, sumber data siswa |
| `/WebService/getRombonganBelajar?npsn=20214989` | HTTP 200, JSON body | `results: 204`, sumber kelas/tingkat/anggota rombel |
| `/WebService/getPengguna?npsn=20214989` | HTTP 200, JSON body | `results: 102`, referensi pengguna Dapodik |
| `/WebService/getGtk?npsn=20214989` | HTTP 200, JSON body | `results: 93`, sumber data guru/pegawai |
| `/WebService/getPTK?npsn=20214989` | HTTP 404 | Endpoint ini tidak tersedia di service tersebut |
| `/WebService/getGTK?npsn=20214989` | HTTP 404 | Endpoint ini tidak tersedia di service tersebut |

## Endpoint Valid Dari Cek Ulang

Endpoint berikut terbaca 200 dari workspace ini ketika dipanggil dengan `Authorization: Bearer <token>` dan `?npsn=20214989`.

| Endpoint | ID | Rows | Results | Pemakaian SIAPS |
| --- | --- | ---: | ---: | --- |
| `/WebService/getSekolah` | `sekolah_id` | 1 | 1 | Verifikasi sekolah/NPSN |
| `/WebService/getPesertaDidik` | `registrasi_id` | 1444 | 1444 | Sumber data siswa |
| `/WebService/getRombonganBelajar` | `rombongan_belajar_id` | 204 | 204 | Sumber kelas, tingkat, wali/GTK rombel, anggota rombel |
| `/WebService/getPengguna` | `pengguna_id` | 102 | 102 | Referensi username/email guru; password tidak dipakai |
| `/WebService/getGtk` | `ptk_terdaftar_id` | 93 | 93 | Sumber data guru/pegawai |

Pola respons JSON Dapodik:

```json
{
  "results": 1444,
  "id": "registrasi_id",
  "start": 0,
  "limit": 20,
  "rows": []
}
```

Catatan bentuk `rows`:

- `/WebService/getSekolah`: `rows` berupa object tunggal.
- `/WebService/getPesertaDidik`, `/WebService/getGtk`, `/WebService/getRombonganBelajar`, `/WebService/getPengguna`: `rows` berupa array.

## Pembanding DB Lokal SIAPS

DB PostgreSQL lokal belum bisa diintrospeksi langsung dari CLI saat cek ini karena koneksi ke `127.0.0.1:5432` ditolak. Pembanding schema di bawah diambil dari migrasi aktif backend SIAPS.

Tabel utama yang menjadi target mapping:

- `users`: akun utama, identitas ringkas, login, role via Spatie.
- `data_pribadi_siswa`: detail profil siswa.
- `data_kepegawaian`: detail profil guru/pegawai.
- `tingkat`: master tingkat lokal.
- `kelas`: master kelas lokal per tahun ajaran.
- `kelas_siswa`: relasi siswa ke kelas per tahun ajaran.
- `tahun_ajaran`: konteks tahun ajaran aktif untuk kelas dan relasi kelas siswa.

Keputusan mapping akun:

- `users.email` siswa tetap digenerate SIAPS dari `nis`, contoh format `nis@sman1sumbercirebon.sch.id`.
- `data_pribadi_siswa.email_siswa` boleh diisi dari field Dapodik `email` jika formatnya valid.
- `users.email` guru/pegawai memakai `getPengguna.username` jika nilainya email valid. Jika tidak valid/kosong, SIAPS generate dari kandidat username.
- Password dari `/WebService/getPengguna.password` tidak dipakai.

## Struktur Data Yang Dipakai

### Peserta Didik

Endpoint: `/WebService/getPesertaDidik`

Field utama untuk mapping:

- `peserta_didik_id`
- `registrasi_id`
- `nipd`
- `nisn`
- `nik`
- `nama`
- `jenis_kelamin`
- `tempat_lahir`
- `tanggal_lahir`
- `agama_id_str`
- `nomor_telepon_rumah`
- `nomor_telepon_seluler`
- `email`
- `nama_ayah`
- `pekerjaan_ayah_id_str`
- `nama_ibu`
- `pekerjaan_ibu_id_str`
- `nama_wali`
- `pekerjaan_wali_id_str`
- `anak_keberapa`
- `tinggi_badan`
- `berat_badan`
- `kebutuhan_khusus`
- `sekolah_asal`
- `tanggal_masuk_sekolah`
- `rombongan_belajar_id`
- `tingkat_pendidikan_id`
- `nama_rombel`
- `kurikulum_id_str`

Mapping SIAPS:

- `users.nama_lengkap` dari `nama`
- `users.nis` dari `nipd`
- `users.nisn` dari `nisn`
- `users.nik` dari `nik`
- `users.jenis_kelamin` dari `jenis_kelamin`
- `data_pribadi_siswa.*` dari data lahir, agama, kontak, orang tua, dan asal sekolah
- `data_pribadi_siswa.email_siswa` dari `email` jika valid
- `data_pribadi_siswa.nama_ibu` dari `nama_ibu`
- `data_pribadi_siswa.pekerjaan_ibu` dari `pekerjaan_ibu_id_str`
- `data_pribadi_siswa.nama_wali` dari `nama_wali`
- `data_pribadi_siswa.pekerjaan_wali` dari `pekerjaan_wali_id_str`
- `data_pribadi_siswa.anak_ke` dari `anak_keberapa`
- `data_pribadi_siswa.tinggi_badan` dari `tinggi_badan`
- `data_pribadi_siswa.berat_badan` dari `berat_badan`
- `data_pribadi_siswa.kebutuhan_khusus` dari `kebutuhan_khusus`
- `data_pribadi_siswa.tahun_masuk` dari tahun pada `tanggal_masuk_sekolah`
- Email siswa tetap digenerate SIAPS dari `nis`, misalnya `nis@sman1sumbercirebon.sch.id`
- `rombongan_belajar_id`, `nama_rombel`, `tingkat_pendidikan_id`, dan `kurikulum_id_str` dipakai sebagai referensi kelas/preview, belum disimpan permanen di tabel siswa

### GTK / Guru / Pegawai

Endpoint: `/WebService/getGtk`

Field utama untuk mapping:

- `ptk_id`
- `ptk_terdaftar_id`
- `nip`
- `nuptk`
- `nik`
- `nama`
- `jenis_kelamin`
- `tempat_lahir`
- `tanggal_lahir`
- `agama_id_str`
- `jenis_ptk_id_str`
- `jabatan_ptk_id_str`
- `status_kepegawaian_id_str`
- `pendidikan_terakhir`
- `bidang_studi_terakhir`
- `pangkat_golongan_terakhir`
- `rwy_pend_formal`
- `rwy_kepangkatan`

Mapping SIAPS:

- `users.nama_lengkap` dari `nama`
- `users.nip` dari `nip`
- `users.nik` dari `nik`
- `data_kepegawaian.nuptk` dari `nuptk`
- `data_kepegawaian.jenis_ptk` dari `jenis_ptk_id_str`
- `data_kepegawaian.jabatan` dari `jabatan_ptk_id_str`
- `data_kepegawaian.pendidikan_terakhir` dari `pendidikan_terakhir`
- `data_kepegawaian.bidang_studi` dari `bidang_studi_terakhir`
- `data_kepegawaian.pangkat_golongan` dari `pangkat_golongan_terakhir`
- `status_kepegawaian_id_str` dipetakan ke `ASN` atau `Honorer`
- Email guru/pegawai diambil dari `/WebService/getPengguna.username` jika username tersebut valid sebagai email
- Jika username Dapodik bukan email valid, email guru/pegawai digenerate oleh SIAPS

### Rombongan Belajar

Endpoint: `/WebService/getRombonganBelajar`

Field utama untuk mapping:

- `rombongan_belajar_id`
- `nama`
- `tingkat_pendidikan_id`
- `tingkat_pendidikan_id_str`
- `semester_id`
- `jenis_rombel_str`
- `jurusan_id_str`
- `ptk_id`
- `ptk_id_str`
- `anggota_rombel`
- `pembelajaran`

Mapping SIAPS:

- `tingkat_pendidikan_id_str` dicocokkan ke `tingkat`
- `nama` dicocokkan ke `kelas.nama_kelas`
- `jurusan_id_str` dicatat sebagai jurusan jika relevan
- `ptk_id` dipakai untuk mencocokkan wali/guru lokal
- `anggota_rombel` dipakai untuk kandidat assignment `kelas_siswa`
- `semester_id` dipakai sebagai referensi Dapodik, tetapi relasi SIAPS tetap memakai `tahun_ajaran` aktif lokal
- `pembelajaran` sudah tersedia untuk tahap berikutnya jika jadwal/mapel/guru-mapel akan disinkronkan

### Pengguna Dapodik

Endpoint: `/WebService/getPengguna`

Field yang terlihat:

- `pengguna_id`
- `sekolah_id`
- `username`
- `nama`
- `peran_id_str`
- `password`
- `alamat`
- `no_telepon`
- `no_hp`
- `ptk_id`
- `peserta_didik_id`

Pemakaian SIAPS:

- `username` dipakai sebagai email guru/pegawai jika formatnya email valid.
- Untuk siswa, `username` hanya menjadi referensi cadangan; akun siswa tetap memakai email generate SIAPS.
- `password` Dapodik tidak boleh dipakai untuk akun SIAPS.

## Matriks Mapping SIAPS vs Dapodik

| Area | Dapodik | DB SIAPS | Status |
| --- | --- | --- | --- |
| Akun siswa | `nama`, `nipd`, `nisn`, `nik`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama_id_str` | `users.nama_lengkap`, `users.nis`, `users.nisn`, `users.nik`, `users.jenis_kelamin`, `users.tempat_lahir`, `users.tanggal_lahir`, `users.agama` | Dipakai |
| Email akun siswa | `nipd` sebagai basis generate | `users.email` | Dipakai, tetap generate SIAPS |
| Detail siswa | `email`, `nomor_telepon_rumah`, `nomor_telepon_seluler`, `nama_ayah`, `pekerjaan_ayah_id_str`, `nama_ibu`, `pekerjaan_ibu_id_str`, `nama_wali`, `pekerjaan_wali_id_str`, `anak_keberapa`, `tinggi_badan`, `berat_badan`, `kebutuhan_khusus`, `sekolah_asal`, `tanggal_masuk_sekolah` | `data_pribadi_siswa.email_siswa`, `no_telepon_rumah`, `no_hp_siswa`, `nama_ayah`, `pekerjaan_ayah`, `nama_ibu`, `pekerjaan_ibu`, `nama_wali`, `pekerjaan_wali`, `anak_ke`, `tinggi_badan`, `berat_badan`, `kebutuhan_khusus`, `asal_sekolah`, `tahun_masuk` | Dipakai |
| Akun guru/pegawai | `nama`, `nip`, `nik`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama_id_str`, `status_kepegawaian_id_str` | `users.nama_lengkap`, `users.nip`, `users.nik`, `users.jenis_kelamin`, `users.tempat_lahir`, `users.tanggal_lahir`, `users.agama`, `users.status_kepegawaian` | Dipakai |
| Email guru/pegawai | `getPengguna.username` | `users.email` | Dipakai jika email valid |
| Detail guru/pegawai | `nuptk`, `jenis_ptk_id_str`, `jabatan_ptk_id_str`, `pendidikan_terakhir`, `bidang_studi_terakhir`, `pangkat_golongan_terakhir` | `data_kepegawaian.nuptk`, `jenis_ptk`, `jabatan`, `pendidikan_terakhir`, `bidang_studi`, `pangkat_golongan` | Dipakai |
| Tingkat | `tingkat_pendidikan_id`, `tingkat_pendidikan_id_str` | `tingkat.kode`, `tingkat.nama`, `tingkat.urutan` | Dicocokkan `10/X`, `11/XI`, `12/XII` |
| Kelas | `rombongan_belajar_id`, `nama`, `jurusan_id_str`, `ptk_id`, `anggota_rombel` | `kelas.nama_kelas`, `kelas.jurusan`, `kelas.wali_kelas_id`, `kelas_siswa` | Dipakai untuk preview |
| Tahun ajaran | `semester_id`, `tahun_ajaran_id` | `tahun_ajaran.id`, `tahun_ajaran.status`, `tahun_ajaran.is_active` | SIAPS tetap memakai tahun ajaran aktif lokal |
| ID Dapodik | `peserta_didik_id`, `ptk_id`, `rombongan_belajar_id`, `anggota_rombel_id`, `pengguna_id` | Belum ada kolom khusus | Dipakai untuk matching sementara, belum persist |

## Implikasi Untuk Sinkronisasi

Urutan sinkronisasi operasional sekarang dibagi dua jalur:

1. Ambil snapshot Dapodik ke staging.
2. Cocokkan ID Dapodik ke ID SIAPS di tabel mapping.
3. Jalur **Update Data** memproses mapping `exact` untuk user yang sudah ada.
4. Jalur **Input Data Baru** memproses mapping `unmatched` untuk membuat user baru.
5. `probable`, `conflict`, kelas, dan rombel tetap ditahan untuk review manual.

Kunci pencocokan:

- Siswa: `nisn`, lalu `nipd/nis`, lalu `nik`, lalu `nama + tanggal_lahir`.
- Pegawai: `nip`, lalu `nuptk`, lalu `nik`, lalu `nama + tanggal_lahir`.
- Kelas: `nama rombel + tahun ajaran aktif`, lalu `nama rombel`.

## Tahap Staging SIAPS

Implementasi saat ini menambahkan tahap pengambilan data tanpa menulis ke tabel final SIAPS.

Endpoint backend:

- `POST /api/dapodik/staging-batches`
- `GET /api/dapodik/staging-batches/{batch}`
- `POST /api/dapodik/staging-batches/{batch}/sources`
- `POST /api/dapodik/staging-batches/{batch}/finalize`
- `GET /api/dapodik/staging-batches/{batch}/review`
- `GET /api/dapodik/staging-batches/{batch}/apply-preview`
- `POST /api/dapodik/staging-batches/{batch}/apply`
- `GET /api/dapodik/staging-batches/{batch}/input-preview`
- `POST /api/dapodik/staging-batches/{batch}/input`

UI operasional hanya memakai alur batch progress di atas.

Urutan batch progress:

1. Buat batch dengan `POST /api/dapodik/staging-batches`.
2. Ambil `school` dari `/WebService/getSekolah`.
3. Ambil `dapodik_users` dari `/WebService/getPengguna`.
4. Ambil `students` dari `/WebService/getPesertaDidik`.
5. Ambil `employees` dari `/WebService/getGtk`.
6. Ambil `classes` dari `/WebService/getRombonganBelajar`.
7. Finalisasi mapping lokal dengan `POST /api/dapodik/staging-batches/{batch}/finalize`.
8. Review hasil mapping dengan `GET /api/dapodik/staging-batches/{batch}/review`.
9. Hitung preview Update Data dengan `GET /api/dapodik/staging-batches/{batch}/apply-preview`.
10. Hitung preview Input Data Baru dengan `GET /api/dapodik/staging-batches/{batch}/input-preview`.
11. Jalankan Update Data dengan `POST /api/dapodik/staging-batches/{batch}/apply`.
12. Jalankan Input Data Baru dengan `POST /api/dapodik/staging-batches/{batch}/input`.

Alasan `dapodik_users` diambil sebelum `students` dan `employees`: data pengguna dipakai sebagai referensi email/username saat normalisasi siswa dan pegawai.

Payload status batch memuat:

- `batch.status`: `running`, `completed`, `partial`, atau `failed`.
- `batch.progress.percentage`: persentase langkah yang sudah selesai.
- `batch.source_statuses`: status tiap sumber, termasuk `queued`, `running`, `completed`, atau `failed`.
- `batch.totals.records_by_source`: jumlah record staging per sumber.
- `batch.errors`: error per sumber jika ada endpoint gagal.

## Tahap Review Mapping

Setelah staging selesai, UI membaca hasil mapping dari endpoint:

- `GET /api/dapodik/staging-batches/{batch}/review`

Query yang didukung:

- `entity_type`: `student`, `employee`, atau `class`.
- `confidence`: `exact`, `probable`, `conflict`, atau `unmatched`.
- `limit`: jumlah item review yang dikembalikan, maksimal 100.

Review menampilkan:

- ringkasan jumlah mapping per entity dan confidence;
- record staging per sumber;
- pasangan SIAPS jika ditemukan;
- field utama yang berubah;
- rekomendasi aksi seperti `update_candidate`, `manual_review`, `resolve_conflict`, `create_candidate`, atau `review_class_mapping`.

Kebijakan apply dari hasil review:

- `exact`: boleh menjadi kandidat apply otomatis setelah dibuat preview apply.
- `probable`: wajib review manual.
- `conflict`: tidak boleh apply sebelum konflik diselesaikan.
- `unmatched`: tidak boleh update data existing; hanya kandidat create setelah validasi.
- `class`: tetap wajib review khusus karena menyentuh histori kelas dan `kelas_siswa`.

## Jalur Update Data

Update Data adalah tahap untuk mengubah user existing yang sudah cocok `exact`.

Endpoint:

- `GET /api/dapodik/staging-batches/{batch}/apply-preview`

Query yang didukung:

- `entity_type`: `student` atau `employee`.
- `limit`: jumlah item preview yang dikembalikan, maksimal 100.

Yang masuk preview apply:

- mapping `student` dengan confidence `exact` dan sudah punya pasangan user SIAPS;
- mapping `employee` dengan confidence `exact` dan sudah punya pasangan user SIAPS;
- perubahan pada tabel `users`, `data_pribadi_siswa`, dan `data_kepegawaian`.

Yang belum boleh di-apply otomatis:

- mapping `probable`, `conflict`, dan `unmatched`;
- pembuatan user baru;
- perubahan `kelas` dan `kelas_siswa`;
- perubahan email pegawai yang berdampak ke login, kecuali sudah direview manual.

Payload preview memuat ringkasan total eligible, update, no change, blocked, jumlah field diff, dan diff per tabel. Tahap preview belum mengubah tabel final SIAPS.

### Eksekusi Update Data

Endpoint:

- `POST /api/dapodik/staging-batches/{batch}/apply`

Payload:

```json
{
  "entity_type": "student",
  "confirm_apply": true
}
```

`entity_type` boleh dikosongkan untuk apply siswa dan GTK sekaligus. `confirm_apply` wajib `true` agar endpoint tidak terpanggil tidak sengaja.

Yang diubah:

- `users`: nama, NIS/NISN/NIP/NIK, jenis kelamin, tempat/tanggal lahir, agama, dan status kepegawaian;
- `data_pribadi_siswa`: field pribadi siswa yang tersedia dari Dapodik;
- `data_kepegawaian`: field GTK yang tersedia dari Dapodik.

Yang tetap tidak diubah:

- email pegawai atau field lain yang ditandai `safe_auto_apply=false`;
- mapping `probable`, `conflict`, dan `unmatched`;
- pembuatan user baru;
- `kelas` dan `kelas_siswa`.

Update berjalan dalam transaksi database. Jika terjadi error, perubahan parsial dibatalkan.

## Jalur Input Data Baru

Input Data Baru membuat akun dan data detail dari mapping `unmatched` yang lolos validasi ulang.

Endpoint:

- `GET /api/dapodik/staging-batches/{batch}/input-preview`
- `POST /api/dapodik/staging-batches/{batch}/input`

Payload eksekusi:

```json
{
  "entity_type": "student",
  "confirm_input": true
}
```

Ketentuan:

- `student unmatched` dibuat menjadi user role `Siswa` dan `data_pribadi_siswa`.
- `employee unmatched` dibuat menjadi user role aman (`Guru`, `Guru_BK`, atau `Pegawai`) dan `data_kepegawaian`.
- Role berisiko tinggi seperti kepala sekolah tidak diberikan otomatis dari Dapodik.
- Username/email/identifier dicek ulang sebelum create.
- Kelas dan `kelas_siswa` tidak diisi otomatis.
- Setelah user dibuat, mapping Dapodik diperbarui menjadi `exact` dengan `match_key = created_from_dapodik`.

Password awal:

- Siswa: tanggal lahir format `DDMMYYYY`.
- GTK: default pegawai sesuai standar import pegawai SIAPS.

Tabel staging:

- `dapodik_sync_batches`: metadata batch pengambilan data Dapodik.
- `dapodik_sync_records`: raw JSON per sumber dan per row.
- `dapodik_entity_mappings`: kandidat pasangan ID Dapodik ke ID lokal SIAPS.

Sumber yang diambil:

- `school` dari `/WebService/getSekolah`
- `students` dari `/WebService/getPesertaDidik`
- `employees` dari `/WebService/getGtk`
- `classes` dari `/WebService/getRombonganBelajar`
- `dapodik_users` dari `/WebService/getPengguna`

Safeguard:

- Endpoint staging tidak mengubah `users`.
- Endpoint staging tidak mengubah `data_pribadi_siswa`.
- Endpoint staging tidak mengubah `data_kepegawaian`.
- Endpoint staging tidak mengubah `kelas`.
- Endpoint staging tidak mengubah `kelas_siswa`.
- Mapping rombel wajib direview sebelum ada apply ke histori kelas.

Status mapping:

- `exact`: cocok lewat identifier kuat seperti NISN, NIS, NIK, NIP, NUPTK, atau nama kelas + tahun ajaran aktif.
- `probable`: cocok lewat nama + tanggal lahir atau nama kelas saja.
- `conflict`: match ambigu atau role lokal tidak sesuai.
- `unmatched`: belum ada pasangan lokal.

Ketentuan kelas SIAPS:

- `kelas_siswa` adalah histori kelas, bukan sekadar nilai kelas sekarang.
- Kelas sekarang ditentukan oleh `is_active = true` dan `status = aktif`.
- Pindah kelas, naik kelas, lulus, keluar, dan aktif kembali sudah punya mekanisme transisi SIAPS.
- Data rombel Dapodik tidak boleh langsung menimpa `kelas_siswa`; harus menjadi diff/usulan terlebih dahulu.

## Catatan Operasional

Untuk menjalankan sinkronisasi dari server SIAPS, IP server SIAPS tetap harus sesuai dengan pengaturan Web Service Dapodik. Jika nanti dijalankan dari server berbeda dan IP belum didaftarkan, endpoint bisa terlihat HTTP 200 dari sisi HTTP client, tetapi isi respons menolak dengan internal `403 Forbidden`.

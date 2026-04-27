# Implementasi Geofence Per Lokasi

Dokumen ini mencatat scope implementasi geofence per lokasi agar perubahan backend, frontend, dan mobile tetap sinkron.

## Tujuan

- Menambah selector `Tipe Area` per lokasi di aplikasi.
- Opsi yang didukung pada fase ini:
  - `Circle`
  - `Polygon`
- Menjaga kompatibilitas data lama berbasis `radius`.
- Menjaga kemampuan multi lokasi aktif sekaligus.

## Keputusan Desain

- `radius` tidak dihapus.
- `polygon` ditambahkan sebagai opsi baru, bukan pengganti `circle`.
- Selector berada di level record lokasi, bukan toggle global aplikasi.
- Jika ada banyak lokasi aktif, absensi valid jika pengguna berada di salah satu lokasi aktif yang diizinkan.
- Validasi final area absensi harus diputuskan backend.

## Perubahan Backend

- [x] Tambah kolom `geofence_type` pada tabel `lokasi_gps`
- [x] Tambah kolom `geofence_geojson` pada tabel `lokasi_gps`
- [x] Tambah cast/fillable baru pada model `LokasiGps`
- [x] Tambah helper geofence terpusat untuk:
  - [x] normalisasi tipe geofence
  - [x] validasi GeoJSON polygon
  - [x] point in polygon
  - [x] hitung jarak ke area
- [x] Ubah validasi create/update lokasi GPS agar mendukung `circle` dan `polygon`
- [x] Ubah endpoint berikut agar memakai evaluator geofence per lokasi:
  - [x] `lokasi-gps/validate`
  - [x] `lokasi-gps/check-distance`
  - [x] `lokasi-gps/attendance-schema`
  - [x] `lokasi-gps/active-users`
  - [x] `lokasi-gps/{id}/users`
  - [x] submit absensi sederhana
  - [x] evaluasi attendance schema
  - [x] live tracking ingest
- [x] Tambah field geofence baru pada export/import lokasi GPS

## Perubahan Frontend Web

- [x] Tambah selector `Tipe Area` di manajemen lokasi GPS
- [x] Jika `Circle`:
  - [x] tampilkan input `radius`
  - [x] pertahankan interaksi map lama
- [x] Jika `Polygon`:
  - [x] tampilkan editor polygon di peta
  - [x] sediakan aksi tambah titik, geser titik, hapus titik, reset polygon
- [x] Perbarui daftar lokasi/grid/table untuk menampilkan tipe area
- [x] Perbarui peta monitoring agar bisa menggambar polygon maupun circle
- [x] Normalisasi payload frontend untuk `geofence_type` dan `geofence_geojson`
- [x] Perbarui daftar/detail skema absensi dan info skema per user agar menampilkan ringkasan area lokasi terpilih

## Perubahan Mobile

- [x] Tambah parsing field `geofence_type` dan `geofence_geojson`
- [x] Hindari pre-validation lokal yang hardcoded radius saja
- [x] Gunakan endpoint backend untuk validasi area agar hasil konsisten
- [x] Sesuaikan popup/error agar istilahnya menjadi `area absensi`, bukan selalu `radius`
- [ ] Jalankan formatter/analyzer mobile jika toolchain `dart/flutter` tersedia di environment

## Pengujian

- [x] Test backend untuk lokasi `circle` tetap lolos seperti sebelumnya
- [x] Test backend untuk lokasi `polygon` valid jika titik di dalam area
- [x] Test backend untuk lokasi `polygon` ditolak jika titik di luar area
- [x] Test live tracking `is_in_school_area` untuk polygon
- [x] Verifikasi UI manajemen lokasi circle masih berfungsi
- [x] Verifikasi UI manajemen lokasi polygon bisa simpan dan edit
- [ ] Verifikasi mobile tidak salah menolak lokasi polygon di perangkat nyata

## Catatan Risiko

- Banyak area sistem saat ini masih memakai istilah `radius`; perlu normalisasi bertahap di UI.
- Mobile saat ini melakukan pre-check lokal berbasis radius; ini berisiko false reject jika tidak diubah.
- Data lama harus default ke `geofence_type = circle`.

## Tahap Eksekusi

1. Backend geofence engine
2. Frontend web admin lokasi
3. Consumer web/mobile
4. Verifikasi dan regression test

## Status Saat Ini

- Backend circle/polygon sudah aktif dan backward-compatible dengan data radius lama.
- Frontend admin lokasi sudah bisa memilih `Circle` atau `Polygon` per lokasi.
- Halaman pengaturan absensi sudah memakai istilah area per lokasi, bukan lagi mengasumsikan radius untuk semua lokasi.
- Halaman daftar/detail skema absensi dan info skema efektif per user sudah menampilkan ringkasan lokasi terpilih beserta tipe areanya.
- Monitoring web sudah bisa menggambar shape sesuai tipe geofence.
- Mobile sudah dipindahkan ke validasi area berbasis backend untuk mencegah false reject polygon.
- Regression backend dan build frontend sudah lulus.
- Verifikasi mobile di perangkat nyata masih perlu dilakukan setelah toolchain/run target tersedia.

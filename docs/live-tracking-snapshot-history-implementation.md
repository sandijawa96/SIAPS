# Live Tracking Snapshot + History Refactor

## Tujuan
- Menjadikan cache snapshot sebagai source of truth untuk status live tracking saat ini.
- Menjaga tabel `live_tracking` sebagai history/audit/export, bukan sumber status realtime.
- Menyimpan metadata operasional yang sebelumnya hilang: lokasi terdeteksi, sumber device, kualitas GPS, dan sesi pengirim.
- Menyiapkan scope akses terbatas jika `view_live_tracking` nanti diberikan ke peran seperti `Wali Kelas`.
- Menurunkan risiko drift data antar halaman dashboard, monitoring lokasi, history, dan export.

## Keputusan Arsitektur
- `snapshot current state` disimpan di cache per user dan diindeks untuk query dashboard realtime.
- `history` tetap ditulis ke tabel `live_tracking` dengan throttle 60 detik per user.
- Endpoint realtime membaca snapshot cache terlebih dahulu.
- Endpoint history/export tetap membaca tabel `live_tracking`.
- Metadata snapshot dan history diseragamkan:
  - `location_id`
  - `location_name`
  - `device_source`
  - `gps_quality_status`
  - `device_info.session_id`
  - `speed`
  - `heading`

## Status Model
- `tracking_status`:
  - `active`
  - `outside_area`
  - `stale`
  - `no_data`
- `gps_quality_status`:
  - `good`
  - `moderate`
  - `poor`
  - `unknown`

## Eksekusi
### Phase 1 - Dokumentasi dan fondasi
- [x] Buat dokumen implementasi dan acceptance criteria
- [x] Definisikan arsitektur `snapshot + history`

### Phase 2 - Backend current snapshot
- [x] Tambah migration metadata `live_tracking`
- [x] Tambah service snapshot cache terpusat
- [x] Tambah service context resolver untuk lokasi aktif + kualitas GPS
- [x] Refactor `LokasiGpsController::updateUserLocation`
- [x] Refactor `LokasiGpsController::getActiveUsersLocations`
- [x] Refactor `LokasiGpsController::getUsersInLocation`

### Phase 3 - Backend realtime dashboard
- [x] Refactor `LiveTrackingController::getCurrentTracking` ke snapshot cache
- [x] Refactor `LiveTrackingController::getCurrentLocation` ke snapshot cache
- [x] Refactor `LiveTrackingController::getUsersInRadius` ke snapshot cache
- [x] Tambah filter server-side (`search`, `status`, `area`, `class`)
- [x] Tambah pagination opsional yang tetap backward compatible
- [x] Tambah scope akses siswa berdasarkan `RoleDataScope`

### Phase 4 - History, retention, audit
- [x] Perluas ingest history dengan metadata lokasi/device/GPS quality
- [x] Tambah command cleanup retention `live_tracking`
- [x] Jadwalkan cleanup harian di scheduler
- [x] Perbarui export agar ikut metadata baru

### Phase 5 - Client integration
- [x] Web sender kirim `device_source`, `session_id`, `speed`, `heading`
- [x] Mobile sender kirim `device_source`, `session_id`, `speed`, `heading`
- [x] Dashboard frontend tampilkan status `stale`, `outside_area`, GPS quality, device source
- [x] Dashboard frontend konsumsi summary/filter server-side

### Phase 6 - Verifikasi
- [x] Update integration test live tracking
- [x] Tambah test cleanup retention
- [x] Jalankan test backend target
- [x] Jalankan build frontend
- [x] Catat keterbatasan verifikasi mobile jika toolchain tidak tersedia

### Phase 7 - Report dan detail history
- [x] Samakan dialog export dengan kolom yang benar-benar didukung backend
- [x] Buat backend export menghormati pilihan grup kolom
- [x] Tampilkan metadata history harian pada dialog detail siswa
- [x] Tambah test export untuk pilihan kolom

## Acceptance Criteria
- Dashboard current tracking dan monitoring lokasi membaca data realtime yang sama.
- `location_name` tidak lagi kosong pada data realtime/history jika lokasi aktif terdeteksi.
- History tetap tercatat maksimal 1 titik per 60 detik per user.
- Snapshot realtime tetap bisa tampil sebagai `stale`, bukan hilang mendadak setelah beberapa menit.
- Data live tracking dapat dibatasi ke siswa yang boleh dilihat actor.
- Sistem memiliki cleanup retention terjadwal untuk tabel `live_tracking`.
- Web/mobile menyertakan identitas sumber pengirim (`web`/`mobile`) dan session id.

## Catatan Implementasi
- Pagination current tracking dibuat opsional agar kontrak endpoint lama tidak langsung pecah.
- `session_id` disimpan di `device_info` agar tidak menambah kolom yang belum dibutuhkan untuk query.
- Snapshot TTL dibuat lebih panjang dari stale window supaya status `stale` tetap bisa dihitung.
- Build frontend berhasil.
- Verifikasi static analyzer/formatter mobile belum dijalankan karena toolchain `dart/flutter` tidak tersedia di environment ini saat dicek (`CommandNotFound`).
- Dialog export sekarang hanya menawarkan grup kolom yang benar-benar dipakai backend: informasi dasar, status tracking, data lokasi, waktu, dan info perangkat.
- Dialog detail siswa sekarang menampilkan ringkasan history tracking hari ini berikut metadata lokasi, device source, kualitas GPS, akurasi, dan IP.

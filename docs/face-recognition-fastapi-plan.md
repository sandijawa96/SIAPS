# Face Recognition FastAPI Plan

## 1. Tujuan
Dokumen ini menetapkan arsitektur target untuk face recognition real pada sistem absensi, dengan mempertimbangkan:

- source code yang berjalan saat ini,
- spesifikasi server pada `docs/spek-server.md`,
- stack web saat ini yang memakai `Nginx`, bukan Apache,
- kebutuhan agar solusi tetap gratis/open-source,
- kebutuhan verifikasi tetap cepat untuk operasional sekolah.

Dokumen ini sudah diperbarui mengikuti implementasi yang sedang berjalan. Anti-spoofing sengaja tidak diterapkan pada fase ini agar latency verifikasi tetap rendah.

## Status Implementasi Saat Ini
Per 2026-03-19, fondasi implementasi sudah mulai dikerjakan dengan scope berikut:

1. service `FastAPI` sudah ditambahkan di folder `face-service/`,
2. endpoint dasar sudah tersedia:
   - `GET /health`
   - `POST /enroll`
   - `POST /verify`
3. backend Laravel sudah terintegrasi ke face-service,
4. endpoint enrollment template dasar sudah tersedia di backend,
5. placeholder verification di backend sudah diganti ke HTTP call ke face-service,
6. anti-spoofing sengaja belum diaktifkan,
7. queue default Laravel sudah diarahkan ke `redis` agar jalur verifikasi wajah tidak lagi database-backed,
8. health-check `face-service` sudah tampil di pengaturan absensi,
9. UI admin untuk upload/reset template wajah sudah tersedia di manajemen pengguna,
10. status `punya template / belum` sudah tampil langsung di tabel siswa,
11. deploy template untuk `systemd` dan `supervisor` sudah tersedia di `face-service/deploy/`,
12. image resizing sebelum inferensi sudah diterapkan agar verifikasi lebih ringan di CPU.

Catatan penting:
- implementasi yang sudah masuk code saat ini memakai `FastAPI + OpenCV YuNet + OpenCV SFace`,
- model yang dipakai tetap format `ONNX`,
- tetapi `ONNX Runtime` dedicated pipeline belum di-wire terpisah pada tahap ini.

Artinya:
- face recognition real sudah mulai berjalan,
- anti-spoofing belum,
- dan optimasi awal untuk beban CPU sudah masuk.

## 2. Kondisi Sistem Saat Ini

### 2.1 Backend Saat Ini
Berdasarkan source code:

- engine face verification backend sudah diarahkan ke face-service:
  - `backend-api/app/Services/AttendanceFaceVerificationService.php`
- mode environment sudah memakai engine real:
  - `backend-api/.env`
  - `ATTENDANCE_FACE_ENGINE_VERSION=opencv-yunet-sface-v1`
- queue default sudah diarahkan ke redis:
  - `backend-api/.env`
  - `QUEUE_CONNECTION=redis`
- cache juga masih database:
  - `backend-api/.env`
  - `CACHE_STORE=database`
- job queue verifikasi wajah sudah ada:
  - `backend-api/app/Jobs/ProcessAttendanceFaceVerification.php`
- model template wajah sudah ada:
  - `backend-api/app/Models/UserFaceTemplate.php`
- model hasil verifikasi wajah sudah ada:
  - `backend-api/app/Models/AttendanceFaceVerification.php`

### 2.2 Temuan Penting
1. Arsitektur absensi dan audit verifikasi wajah sudah siap untuk diintegrasikan ke engine real.
2. Engine real backend sudah ada, tetapi anti-spoofing belum ada.
3. Anti-spoofing/liveness memang sengaja ditunda agar verifikasi tidak melambat.
4. Enrollment/template management operasional dasar sudah tersedia, walau audit massal template belum dibuat.
5. Infrastruktur queue sudah diarahkan ke Redis, tetapi optimasi worker/concurrency tetap perlu dituning saat deployment.

## 3. Spesifikasi Server yang Dijadikan Acuan
Berdasarkan `docs/spek-server.md`:

### 3.1 Host
- Intel Xeon E2324G
- RAM 16 GB
- Storage 2 TB
- VGA integrated in processor
- Bandwidth 1 Gb

### 3.2 VM Ubuntu di Proxmox
- 1 socket, 4 cores
- RAM 12 GB
- HDD 500 GB
- Bandwidth 1 Gb

### 3.3 Service yang Sudah Jalan di VM
- Nginx
- MySQL / PostgreSQL
- Redis
- Supervisor
- Mail Server
- aaPanel

## 4. Asumsi Beban Operasional
Asumsi yang dipakai untuk desain:

1. Total pengguna sekitar 1500.
2. Absensi pagi tidak benar-benar serentak 1500 request pada detik yang sama.
3. Beban riil datang bergelombang per kelas/rombongan.
4. Use case yang dibutuhkan adalah `1:1 verification`, bukan `1:N identification`.

Kesimpulan dari asumsi ini:

- server saat ini masih realistis untuk face recognition real,
- tetapi hanya jika arsitektur dan engine yang dipilih memang ringan untuk CPU,
- dan tetap ada fallback saat service AI lambat atau gagal.

## 5. Perbandingan Opsi

### 5.1 Arsitektur
| Opsi | Kelebihan | Kekurangan | Cocok untuk server saat ini | Rekomendasi |
|---|---|---|---|---|
| Laravel/PHP langsung mengerjakan inference | Integrasi sederhana | PHP bukan tempat ideal untuk pipeline vision/ONNX, lebih susah di-tune, risiko mengganggu request absensi | Rendah | Tidak dipilih |
| Laravel + FastAPI service terpisah | Pemisahan concern jelas, inference lebih mudah di-tune, timeout dan worker bisa diatur, kegagalan AI tidak merusak alur web utama | Tambah service baru untuk dikelola | Tinggi | Dipilih |

### 5.2 Engine Face Recognition
| Opsi | Status Gratis/Open Source | Cocok untuk CPU | Cocok untuk 1:1 verification | Rekomendasi |
|---|---|---|---|---|
| Placeholder sekarang | Ya | Tidak relevan | Tidak | Harus diganti |
| OpenCV YuNet + SFace (model ONNX via OpenCV DNN) | Ya | Ya | Ya | Dipilih |

## 6. Keputusan Arsitektur
Arsitektur target yang dipilih:

1. `Laravel` tetap menjadi pusat alur absensi, policy, audit, queue, dan status hasil.
2. `FastAPI` menjadi inference service terpisah.
3. Engine face recognition menggunakan:
   - `YuNet` untuk detection/alignment,
   - `SFace` untuk embedding/matching,
   - model `ONNX` untuk inference CPU via OpenCV pada tahap implementasi saat ini.
4. Anti-spoofing tidak diterapkan pada fase ini.
5. Proses operasional menggunakan:
   - `sync-first`
   - `async fallback`
   - `manual review` hanya untuk exception.

## 7. Kenapa FastAPI Tetap Dipilih
FastAPI dipilih bukan karena web server saat ini kurang cocok, tetapi karena tugas AI-nya memang lebih tepat dipisah dari PHP.

### 7.1 Kecocokan dengan Nginx
Pemakaian `Nginx` tidak menjadi masalah. Justru stack ini cocok:

- `Nginx + php-fpm` tetap melayani Laravel seperti sekarang.
- `FastAPI` berjalan sebagai service internal di port lokal.
- Laravel memanggil FastAPI via internal HTTP.
- FastAPI tidak perlu dibuka ke publik.

Artinya:

- tidak ada ketergantungan pada Apache,
- tidak perlu migrasi web server,
- Nginx tetap dipakai seperti sekarang.

## 8. Komponen yang Harus Tetap Gratis/Open Source
Target stack:

| Komponen | Target |
|---|---|
| API inference service | FastAPI |
| Face detection/alignment | OpenCV YuNet |
| Face recognition embedding | OpenCV SFace |
| Format model | ONNX |
| Runtime inference saat ini | OpenCV DNN |
| Queue | Redis |
| Reverse proxy web | Nginx |

Catatan:
- seluruh komponen di atas ditargetkan memakai stack gratis/open-source,
- verifikasi akhir lisensi model dan dependency tetap dilakukan saat implementasi fase setup,
- tidak memakai layanan cloud berbayar sebagai syarat utama sistem.

## 9. Anti-Spoofing: Posisi Sistem Saat Ini
Untuk fase implementasi sekarang:

1. anti-spoofing tidak diaktifkan,
2. verifikasi fokus pada identity match agar latency tetap rendah,
3. risiko spoofing sederhana masih harus disadari secara operasional,
4. jika nanti dibutuhkan, anti-spoofing hanya akan dipertimbangkan sebagai fase lanjutan setelah baseline performa stabil.

## 10. Rekomendasi Konfigurasi Server dan Proxmox

### 10.1 Rekomendasi yang Disetujui
1. Tetap gunakan satu VM Ubuntu yang sekarang untuk fase awal.
2. Jalankan FastAPI sebagai service tambahan di VM yang sama.
3. Jangan buka FastAPI langsung ke internet publik.
4. Pindahkan queue dari `database` ke `redis`.
5. Tetap mulai dari CPU-only lebih dulu.

### 10.2 Pengaturan VM yang Direkomendasikan
1. `CPU type` VM disarankan `host` jika tidak ada kebutuhan live migration lintas host heterogen.
2. RAM VM sebaiknya tetap stabil, jangan mengandalkan ballooning untuk workload inference.
3. FastAPI dijalankan dengan worker terbatas.
4. Worker inference dibatasi agar tidak memonopoli seluruh core VM.

### 10.3 Rekomendasi Runtime Awal
Untuk VM 4 core:

- FastAPI worker awal: `1`
- inference concurrency awal: `1`
- ONNX intra-op threads awal: `2` atau `3`
- execution mode: `sequential`

Ini baseline aman. Setelah itu baru lakukan load test dan naikkan jika hasilnya aman.

### 10.4 Soal iGPU
Karena dokumen server saat ini hanya menyebut integrated GPU tanpa bukti passthrough ke VM, maka fase awal harus dianggap `CPU-only`.

Jika nanti ingin eksplor iGPU:
- lakukan setelah CPU baseline stabil,
- jangan menjadikannya syarat awal implementasi.

## 11. Mode Operasional yang Direkomendasikan
Mode yang dipilih:

1. `sync-first`
   - Laravel mencoba memanggil FastAPI secara langsung dengan timeout pendek.
2. jika timeout / gagal
   - fallback ke queue async.
3. hasil akhir:
   - `verified`
   - `rejected`
   - `manual_review` hanya untuk exception

Alasan:
- operator tidak dibebani review massal,
- pengguna normal tetap mendapat hasil cepat,
- sistem tetap punya pengaman saat service AI sedang lambat.

## 12. Manual Review Harus Tetap Kecil
Target desain:

- mayoritas kasus otomatis selesai,
- manual review hanya untuk:
  - wajah tidak jelas,
  - multi-face,
  - template bermasalah,
  - service error.

Ini penting. Masalah operasional utama bukan async, tetapi jika terlalu banyak kasus jatuh ke manual review.

## 13. Gap yang Masih Harus Ditutup Sebelum Produksi
1. Enrollment/template management operasional belum lengkap.
2. Worker Redis di server belum tentu sudah disetel operasional.
3. Health-check face-service baru level konektivitas dan engine info, belum latency benchmark.
4. Anti-spoofing memang belum ada.
5. Kalibrasi threshold sekolah belum ada.
6. Load test belum ada.
7. Dashboard review exception belum dipastikan cukup ringkas untuk operator.

## 14. Tahapan Implementasi yang Direkomendasikan

### Fase 1 - Fondasi Infrastruktur
1. Pindahkan queue ke Redis.
2. Tambahkan konfigurasi service face inference.
3. Buat service skeleton FastAPI.
4. Tambahkan health endpoint dan auth internal antar service.
5. Sediakan template deploy `systemd` dan `supervisor`.

### Fase 2 - Enrollment dan Template
1. Rapikan alur enrollment template wajah.
2. Simpan embedding/template aktif.
3. Tambahkan audit siapa yang enroll dan kapan.
4. Pastikan template rotasi dan penonaktifan aman.
5. Tampilkan status template aktif langsung di UI admin.

### Fase 3 - Engine Face Recognition
1. Implementasikan YuNet detection/alignment.
2. Implementasikan SFace embedding.
3. Implementasikan compare result.
4. Sambungkan ke Laravel service yang sekarang masih placeholder.
5. Terapkan resize gambar agar inferensi lebih ringan di CPU.

### Fase 4 - Operasional
1. Aktifkan sync-first dengan timeout ketat.
2. Tambahkan fallback async.
3. Tambahkan monitoring latency dan error rate.
4. Tambahkan dashboard review exception jika diperlukan.

### Fase 5 - Kalibrasi
1. Kumpulkan sampel selfie nyata sekolah.
2. Uji threshold identity.
3. Sesuaikan policy verified/rejected/manual_review.

## 15. Acceptance Criteria
Face recognition baru dianggap siap jika:

1. verifikasi real tidak lagi memakai placeholder engine,
2. queue inference tidak lagi memakai database,
3. response normal tetap cepat pada jam absensi,
4. manual review tetap rendah,
5. audit hasil verifikasi tetap terekam di Laravel,
6. health-check service dan template management operasional sudah tersedia.

## 16. Keputusan Final Dokumen Ini
Keputusan yang dipakai untuk fase implementasi berikutnya:

1. Arsitektur: `Laravel + FastAPI`
2. Engine FR: `YuNet + SFace` dengan model `ONNX`
3. Anti-spoofing: tidak diterapkan pada fase ini
4. Queue: `Redis`
5. Web server: tetap `Nginx`
6. Deployment awal: tetap di VM Ubuntu yang sekarang
7. Mode runtime: `sync-first + async fallback`
8. Fokus awal: `1:1 verification`, bukan `1:N`

## 17. Referensi Resmi
- FastAPI: `https://github.com/fastapi/fastapi`
- OpenCV: `https://github.com/opencv/opencv`
- OpenCV YuNet + SFace tutorial: `https://docs.opencv.org/4.x/d0/dd4/tutorial_dnn_face.html`
- OpenCV Zoo: `https://github.com/opencv/opencv_zoo`
- Proxmox CPU model guidance: `https://pve.proxmox.com/pve-docs/qm.1.html`
- Proxmox dynamic memory management: `https://pve.proxmox.com/wiki/Dynamic_Memory_Management`

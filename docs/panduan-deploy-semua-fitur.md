# Panduan Deploy di aaPanel

Panduan ini khusus untuk kondisi server Anda yang memakai `aaPanel`.

Yang dibahas hanya:

1. frontend Vite/PWA
1. backend Laravel
2. queue worker di `Supervisor`
3. scheduler di `Cron`
4. `face-service` di `Supervisor`

Hal lain di luar itu sengaja tidak dibahas di dokumen ini supaya tidak membingungkan.

## 1. Path yang Dipakai

- Laravel root: `/www/wwwroot/load.sman1sumbercirebon.sch.id`
- Laravel public root: `/www/wwwroot/load.sman1sumbercirebon.sch.id/public`
- Face service root: `/www/wwwroot/face-siaps`
- PHP CLI: `/www/server/php/83/bin/php`

## 2. Yang Harus Sudah Ada di aaPanel

Sesuai kondisi server Anda, bagian ini diasumsikan sudah ada:

1. `Redis`
2. `Supervisor`
3. `Cron`
4. menu `Terminal`

Jadi Anda tidak perlu install layanan itu lagi. Anda hanya perlu menambahkan konfigurasi aplikasi.

## 3. Langkah 1: Set Root Website Laravel

Di aaPanel:

1. buka `Website`
2. pilih site `load.sman1sumbercirebon.sch.id`
3. buka `Site Directory`
4. pastikan document root mengarah ke:

```text
/www/wwwroot/load.sman1sumbercirebon.sch.id/public
```

### Jika sukses tampilnya seperti apa

1. di halaman `Site Directory`, path yang aktif adalah `/public`
2. saat domain dibuka, yang tampil adalah aplikasi Laravel, bukan isi folder mentah
3. file `.env` tidak bisa diakses dari browser

## 4. Langkah 1A: Deploy Frontend Vite di aaPanel

Error seperti ini:

```text
GET /assets/utils-xxxx.js 404
workbox ... bad-precaching-response
```

berarti frontend yang terpasang di server tidak utuh atau service worker masih menyimpan referensi asset hash lama.

Yang harus di-deploy ke site frontend adalah **seluruh isi** folder hasil build Vite, bukan upload sebagian file saja.

Di lokal atau di server, build dulu frontend:

```bash
cd frontend
npm install
npm run build
```

Hasil build ada di:

```text
frontend/build
```

Isi minimal yang harus ikut ter-upload ke root site frontend:

1. `index.html`
2. folder `assets`
3. `manifest.webmanifest` atau `manifest.json` jika ada
4. `sw.js`
5. `workbox-*.js`
6. icon/logo PWA yang ikut keluar saat build

Di aaPanel untuk site frontend `siap.sman1sumbercirebon.sch.id`:

1. buka `File`
2. masuk ke root site frontend Anda
3. hapus isi deploy frontend lama yang sudah tidak dipakai
4. upload **seluruh isi** `frontend/build/` ke root site frontend
5. jangan upload hanya `index.html` saja
6. jangan sisakan campuran build lama dan build baru

### Jika sukses tampilnya seperti apa

Di root site frontend akan ada minimal:

```text
index.html
assets/
sw.js
workbox-xxxxx.js
manifest.webmanifest atau manifest.json
```

Kalau dicek dari browser:

1. request ke file hash di `/assets/...js` tidak lagi `404`
2. halaman login bisa load normal
3. error `bad-precaching-response` hilang

### Jika sebelumnya sudah terlanjur error PWA

Di browser:

1. buka `DevTools`
2. masuk tab `Application`
3. buka `Service Workers`
4. klik `Unregister`
5. buka `Storage`
6. klik `Clear site data`
7. hard refresh halaman

### Contoh config Nginx frontend di aaPanel

Untuk site frontend `siap.sman1sumbercirebon.sch.id`, bentuk yang aman seperti ini:

```nginx
root /www/wwwroot/siap.sman1sumbercirebon.sch.id;
index index.html index.htm;

location /assets/ {
    try_files $uri =404;
    expires 1y;
    add_header Cache-Control "public, immutable";
}

location = /sw.js {
    try_files $uri =404;
    add_header Cache-Control "no-cache";
}

location = /registerSW.js {
    try_files $uri =404;
    add_header Cache-Control "no-cache";
}

location ~* ^/(workbox-.*\\.js|manifest\\.webmanifest|manifest\\.json|favicon\\.ico|robots\\.txt|icon\\.png|logo192\\.png|logo512\\.png|firebase-messaging-sw\\.js)$ {
    try_files $uri =404;
}

location /api/ {
    proxy_pass https://load.sman1sumbercirebon.sch.id;
    proxy_set_header Host load.sman1sumbercirebon.sch.id;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header Origin https://siap.sman1sumbercirebon.sch.id;

    proxy_connect_timeout 30s;
    proxy_send_timeout 30s;
    proxy_read_timeout 30s;

    proxy_hide_header Access-Control-Allow-Origin;
    proxy_hide_header Access-Control-Allow-Methods;
    proxy_hide_header Access-Control-Allow-Headers;
    proxy_hide_header Access-Control-Allow-Credentials;

    add_header Access-Control-Allow-Origin $http_origin always;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH" always;
    add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin" always;
    add_header Access-Control-Allow-Credentials "true" always;
    add_header Access-Control-Max-Age "86400" always;

    if ($request_method = 'OPTIONS') {
        add_header Access-Control-Allow-Origin "https://siap.sman1sumbercirebon.sch.id";
        add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin";
        add_header Access-Control-Allow-Credentials "true";
        add_header Access-Control-Max-Age "86400";
        add_header Content-Length 0;
        add_header Content-Type text/plain;
        return 204;
    }
}

location / {
    try_files $uri $uri/ /index.html;
}
```

Kalau `/assets/*.js` masih `404` padahal file ada di disk, cek lagi dua hal:

1. `root` site frontend benar-benar menunjuk ke `/www/wwwroot/siap.sman1sumbercirebon.sch.id`
2. config yang aktif memang config site frontend `siap.sman1sumbercirebon.sch.id`, bukan site lain

Ini perlu jika browser masih menyimpan service worker lama yang mengarah ke asset hash build sebelumnya.

## 5. Langkah 2: Jalankan Perintah Dasar Backend

Buka `Terminal` di aaPanel, lalu jalankan:

```bash
cd /www/wwwroot/load.sman1sumbercirebon.sch.id
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Jika sukses tampilnya seperti apa

Yang normal:

1. `php artisan key:generate`
   - muncul pesan mirip:
   ```text
   Application key set successfully.
   ```

2. `php artisan migrate --force`
   - muncul daftar migration yang `DONE`, atau pesan:
   ```text
   Nothing to migrate.
   ```

3. `php artisan storage:link`
   - muncul pesan mirip:
   ```text
   The [public/storage] link has been connected to [storage/app/public].
   ```
   atau jika sudah pernah dibuat:
   ```text
   The [public/storage] link already exists.
   ```

## 6. Langkah 3: Tambah Queue Worker di Supervisor aaPanel

Masuk aaPanel:

1. buka `Supervisor`
2. klik `Add Program`

### Isi form Supervisor

| Field | Isi |
|---|---|
| Name | `siaps-laravel-worker` |
| Run User | `www` |
| Working Directory | `/www/wwwroot/load.sman1sumbercirebon.sch.id` |
| Start Command | `/www/server/php/83/bin/php artisan queue:work redis --queue=face-verification,izin-notifications,default --sleep=3 --tries=3 --timeout=120 --max-time=3600` |
| Number of Processes | `1` |
| Auto Start | `ON` |
| Start Seconds | `5` |
| Stdout Log | `/var/log/supervisor/siaps-laravel-worker.log` |
| Stderr Log | `/var/log/supervisor/siaps-laravel-worker-error.log` |

Lalu:

1. klik `Save`
2. klik `Start`

### Jika sukses tampilnya seperti apa

Di halaman `Supervisor`:

1. program `siaps-laravel-worker` muncul di list
2. statusnya `running`
3. tidak restart terus-menerus

Di terminal, kalau dicek:

```bash
ps aux | grep "queue:work"
```

contoh suksesnya mirip:

```text
www  697774  ... /www/server/php/83/bin/php artisan queue:work redis --queue=face-verification,izin-notifications,default --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

Di terminal, kalau dicek:

```bash
tail -f /var/log/supervisor/siaps-laravel-worker.log
```

yang normal biasanya tidak penuh error. Kalau ada masalah, cek:

```bash
tail -f /var/log/supervisor/siaps-laravel-worker-error.log
```

### Queue yang sekarang ditangani worker utama

Worker `siaps-laravel-worker` sekarang menangani:

1. `default`
2. `face-verification`
3. `izin-notifications`

Catatan:

1. queue `izin-notifications` dipakai untuk notifikasi approval/persetujuan izin
2. kalau queue ini tidak ikut di worker, submit izin bisa berhasil tetapi notifikasi approver akan tertahan

### Rekomendasi worker final di production

Kalau semua fitur aktif, susunan worker yang direkomendasikan adalah:

1. `siaps-laravel-worker`
   - queue: `face-verification,izin-notifications,default`
2. `siaps-broadcast-worker`
   - queue: `broadcast,broadcast-email`
3. `siaps-attendance-whatsapp-worker`
   - queue: `attendance-whatsapp`
4. `siaps-whatsapp-worker`
   - queue: `izin-whatsapp,broadcast-whatsapp`

Template file yang bisa langsung dijadikan acuan:

1. `backend-api/deploy/supervisor/siaps-laravel-worker.conf`
2. `backend-api/deploy/supervisor/siaps-broadcast-worker.conf`
3. `backend-api/deploy/supervisor/siaps-attendance-whatsapp-worker.conf`
4. `backend-api/deploy/supervisor/siaps-whatsapp-worker.conf`

### Tambahan jika fitur broadcast aktif

Masuk aaPanel:

1. buka `Supervisor`
2. klik `Add Program`

Isi form:

| Field | Isi |
|---|---|
| Name | `siaps-broadcast-worker` |
| Run User | `www` |
| Working Directory | `/www/wwwroot/load.sman1sumbercirebon.sch.id` |
| Start Command | `/www/server/php/83/bin/php artisan queue:work redis --queue=broadcast,broadcast-email --sleep=1 --tries=1 --timeout=180 --max-time=3600` |
| Number of Processes | `2` |
| Auto Start | `ON` |
| Start Seconds | `5` |
| Stdout Log | `/var/log/supervisor/siaps-broadcast-worker.log` |
| Stderr Log | `/var/log/supervisor/siaps-broadcast-worker-error.log` |

### Tambahan jika notifikasi WhatsApp absensi aktif

Masuk aaPanel:

1. buka `Supervisor`
2. klik `Add Program`

Isi form:

| Field | Isi |
|---|---|
| Name | `siaps-attendance-whatsapp-worker` |
| Run User | `www` |
| Working Directory | `/www/wwwroot/load.sman1sumbercirebon.sch.id` |
| Start Command | `/www/server/php/83/bin/php artisan queue:work redis --queue=attendance-whatsapp --sleep=1 --tries=1 --timeout=180 --max-time=3600` |
| Number of Processes | `1` |
| Auto Start | `ON` |
| Start Seconds | `5` |
| Stdout Log | `/var/log/supervisor/siaps-attendance-whatsapp-worker.log` |
| Stderr Log | `/var/log/supervisor/siaps-attendance-whatsapp-worker-error.log` |

### Tambahan jika notifikasi WhatsApp izin/broadcast aktif

Masuk aaPanel:

1. buka `Supervisor`
2. klik `Add Program`

Isi form:

| Field | Isi |
|---|---|
| Name | `siaps-whatsapp-worker` |
| Run User | `www` |
| Working Directory | `/www/wwwroot/load.sman1sumbercirebon.sch.id` |
| Start Command | `/www/server/php/83/bin/php artisan queue:work redis --queue=izin-whatsapp,broadcast-whatsapp --sleep=1 --tries=1 --timeout=180 --max-time=3600` |
| Number of Processes | `1` |
| Auto Start | `ON` |
| Start Seconds | `5` |
| Stdout Log | `/var/log/supervisor/siaps-whatsapp-worker.log` |
| Stderr Log | `/var/log/supervisor/siaps-whatsapp-worker-error.log` |

## 7. Langkah 4: Tambah Scheduler di Cron aaPanel

Masuk aaPanel:

1. buka `Cron`
2. klik `Add Task`
3. pilih `Shell Script`

### Isi form Cron

| Field | Isi |
|---|---|
| Task Name | `siaps scheduler` |
| Execute Cycle | `N Minutes` |
| Interval | `1` |
| Execute User | `www` |
| Script Content | `cd /www/wwwroot/load.sman1sumbercirebon.sch.id && /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1` |

Lalu:

1. klik `Save`
2. klik `Run` sekali untuk test

### Jika sukses tampilnya seperti apa

Di halaman `Cron`:

1. task `siaps scheduler` muncul di list
2. status task aktif
3. saat klik `Run`, tidak keluar error
4. log task tidak menunjukkan kegagalan command

Di aplikasi web, setelah cron sempat berjalan:

1. buka `Pengaturan Absensi`
2. lihat `Health-Check Operasional`
3. status `Auto alpha` dan `Alert threshold` tidak lagi `belum pernah tercatat berjalan`

Kalau masih warning, tunggu cron jalan atau klik `Run` lagi.

## 8. Langkah 5: Siapkan Face Service

Masuk `Terminal` aaPanel lalu jalankan:

```bash
cd /www/wwwroot/face-siaps
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
python scripts/download_models.py
```

### Jika sukses tampilnya seperti apa

1. folder `.venv` terbentuk
2. install `pip` selesai tanpa error
3. file model ONNX terdownload ke folder `models`

Minimal isi `.env` face-service:

```env
FACE_SERVICE_TOKEN=
FACE_SERVICE_YUNET_MODEL_PATH=models/face_detection_yunet_2023mar.onnx
FACE_SERVICE_SFACE_MODEL_PATH=models/face_recognition_sface_2021dec.onnx
FACE_SERVICE_DETECTOR_SCORE_THRESHOLD=0.9
FACE_SERVICE_MAX_IMAGE_SIDE=1280
FACE_SERVICE_MAX_UPLOAD_BYTES=5242880
FACE_SERVICE_TEMPLATE_VERSION=opencv-yunet-sface-v1
```

## 9. Langkah 6: Tambah Face Service di Supervisor aaPanel

Masuk aaPanel:

1. buka `Supervisor`
2. klik `Add Program`

### Isi form Supervisor

| Field | Isi |
|---|---|
| Name | `siaps-face-service` |
| Run User | `www` |
| Working Directory | `/www/wwwroot/face-siaps` |
| Start Command | `/www/wwwroot/face-siaps/.venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 9001` |
| Number of Processes | `1` |
| Auto Start | `ON` |
| Start Seconds | `5` |
| Stdout Log | `/var/log/supervisor/siaps-face-service.log` |
| Stderr Log | `/var/log/supervisor/siaps-face-service-error.log` |

Lalu:

1. klik `Save`
2. klik `Start`

### Jika sukses tampilnya seperti apa

Di halaman `Supervisor`:

1. program `siaps-face-service` muncul
2. statusnya `running`

Di terminal:

```bash
curl http://127.0.0.1:9001/health
```

Jika sukses, output-nya seperti ini:

```json
{
  "status": "ok",
  "engine": "opencv-yunet-sface",
  "yunet_model_loaded": true,
  "sface_model_loaded": true,
  "template_version": "opencv-yunet-sface-v1"
}
```

Catatan penting:
- Perintah `curl http://127.0.0.1:9001/health` dijalankan di `Terminal` server atau `Terminal` aaPanel, bukan di browser frontend.
- `127.0.0.1` pada browser berarti perangkat yang membuka browser, bukan server Ubuntu Anda.
- Frontend web tidak memanggil face-service langsung. Face-service diakses oleh backend Laravel dari sisi server.
- Jika Anda melihat `404` saat membuka halaman login frontend, cek URL request yang gagal di tab `Network` browser. Itu bukan validasi face-service dari langkah ini.

## 10. Langkah 7: Pastikan Backend Mengarah ke Face Service

Di file `backend-api/.env`, pastikan:

```env
ATTENDANCE_FACE_ENABLED=true
ATTENDANCE_FACE_MODE_DEFAULT=async_pending
ATTENDANCE_FACE_QUEUE=face-verification
ATTENDANCE_FACE_THRESHOLD=0.363
ATTENDANCE_FACE_ENGINE_VERSION=opencv-yunet-sface-v1
ATTENDANCE_FACE_SERVICE_URL=http://127.0.0.1:9001
ATTENDANCE_FACE_SERVICE_TOKEN=
ATTENDANCE_FACE_SERVICE_CONNECT_TIMEOUT=1.5
ATTENDANCE_FACE_SERVICE_REQUEST_TIMEOUT=5.0
QUEUE_CONNECTION=redis
```

Setelah ubah `.env`, jalankan:

```bash
cd /www/wwwroot/load.sman1sumbercirebon.sch.id
php artisan optimize:clear
php artisan config:cache
```

### Jika sukses tampilnya seperti apa

Di web:

1. buka `Pengaturan Absensi`
2. lihat `Health-Check Operasional`
3. bagian `Face Service` akan tampil:
   - status: `healthy`
   - pesan: `Face service terhubung dan merespons normal.`

## 11. Langkah 8: Cek Hasil Akhir di Aplikasi

### 10.1 Cek worker

Di aaPanel `Supervisor`:

1. `siaps-laravel-worker` = `running`
2. `siaps-broadcast-worker` = `running` jika fitur broadcast dipakai
3. `siaps-attendance-whatsapp-worker` = `running` jika WA absensi dipakai
4. `siaps-whatsapp-worker` = `running` jika WA izin/broadcast dipakai
5. `siaps-face-service` = `running`

### 10.2 Cek scheduler

Di aaPanel `Cron`:

1. task `siaps scheduler` ada
2. task aktif
3. run test tidak error

### 10.3 Cek aplikasi web

Buka web admin:

1. `Pengaturan Absensi`
2. `Health-Check Operasional`

Yang dianggap benar:

1. `Face Service` = sehat
2. `Queue Face` tidak warning berat
3. `Auto alpha` dan `Alert threshold` tidak stale setelah cron berjalan

### 10.4 Cek fitur wajah

Di web admin:

1. buka `Manajemen Pengguna`
2. pilih tab `Siswa`
3. buka aksi `Template Wajah`
4. upload foto template

Jika sukses:

1. upload tidak error
2. preview template tampil
3. di tabel siswa, badge berubah menjadi:
   - `Face aktif`
4. di modal template wajah, status template aktif terbaca

## 12. Kalau Ada Error, Cek di Sini

### Laravel

```bash
tail -f /www/wwwroot/load.sman1sumbercirebon.sch.id/storage/logs/laravel.log
```

### Worker queue

```bash
tail -f /var/log/supervisor/siaps-laravel-worker.log
tail -f /var/log/supervisor/siaps-laravel-worker-error.log
tail -f /var/log/supervisor/siaps-broadcast-worker.log
tail -f /var/log/supervisor/siaps-broadcast-worker-error.log
tail -f /var/log/supervisor/siaps-attendance-whatsapp-worker.log
tail -f /var/log/supervisor/siaps-attendance-whatsapp-worker-error.log
tail -f /var/log/supervisor/siaps-whatsapp-worker.log
tail -f /var/log/supervisor/siaps-whatsapp-worker-error.log
```

### Face service

```bash
tail -f /var/log/supervisor/siaps-face-service.log
tail -f /var/log/supervisor/siaps-face-service-error.log
```

### Frontend asset 404 / Workbox precache error

Kalau error yang muncul seperti ini:

```text
GET /assets/*.js 404
bad-precaching-response
```

artinya:

1. file hasil build frontend belum ter-upload lengkap
2. atau service worker lama masih tersimpan di browser

Yang dicek:

1. di root site frontend, pastikan file yang diminta browser benar-benar ada
2. upload ulang seluruh isi `frontend/build/`
3. `Unregister` service worker browser
4. `Clear site data`
5. hard refresh

## 13. Ringkasan Singkat

Minimal yang wajib ada di aaPanel:

1. `Supervisor` program untuk `siaps-laravel-worker`
2. `Cron` task untuk `schedule:run`
3. `Supervisor` program untuk `face-service`

Kalau fitur broadcast dan WhatsApp dipakai, tambahkan juga:

1. `Supervisor` program untuk `siaps-broadcast-worker`
2. `Supervisor` program untuk `siaps-attendance-whatsapp-worker`
3. `Supervisor` program untuk `siaps-whatsapp-worker`

Kalau worker yang relevan sudah `running/aktif`, lalu `Health-Check Operasional` di web sudah sehat, maka deployment inti sudah benar.

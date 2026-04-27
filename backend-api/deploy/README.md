# Deploy Backend Laravel

Gunakan dokumen utama ini:

- `docs/panduan-deploy-semua-fitur.md`

Dokumen itu sudah disederhanakan khusus `aaPanel` dan sudah memuat:

1. cara isi `Supervisor` untuk worker Laravel
2. cara isi `Cron` untuk `schedule:run`
3. indikator sukses jika konfigurasi benar
4. PHP CLI final server Anda: `/www/server/php/83/bin/php`
5. queue async utama: `izin-notifications`, `attendance-whatsapp`, dan `izin-whatsapp`

Template supervisor yang tersedia:

1. `deploy/supervisor/siaps-laravel-worker.conf`
   untuk queue umum `default`, `face-verification`, dan `izin-notifications`
2. `deploy/supervisor/siaps-broadcast-worker.conf`
   untuk queue broadcast aplikasi dan email: `broadcast,broadcast-email`
3. `deploy/supervisor/siaps-attendance-whatsapp-worker.conf`
   untuk queue WhatsApp absensi: `attendance-whatsapp`
4. `deploy/supervisor/siaps-whatsapp-worker.conf`
   untuk queue WhatsApp lain: `izin-whatsapp,broadcast-whatsapp`

Path backend:

- `/www/wwwroot/load.sman1sumbercirebon.sch.id`

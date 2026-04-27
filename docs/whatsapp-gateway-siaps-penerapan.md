# WhatsApp Gateway SIAPS

## Tujuan

Panduan ini menjelaskan cara menerapkan integrasi WhatsApp Gateway pada SIAPS setelah penyesuaian:

- `send-message` memakai `full=1` agar `message_id` gateway bisa direkam
- delivery callback memakai webhook + secret
- event webhook dicatat dan dipakai untuk menaikkan status `delivered`
- skip automation dicatat terpisah dari notifikasi gagal
- retry otomatis hanya menangani notifikasi yang benar-benar gagal

## Komponen Sistem

### 1. Pengiriman keluar

SIAPS mengirim WhatsApp melalui:

- absensi masuk/pulang
- izin submitted / approved / rejected
- discipline reminder
- broadcast campaign

Gateway utama yang dipakai tetap:

- `POST /send-message`

Referensi implementasi:

- `backend-api/app/Services/WhatsappGatewayClient.php`
- `backend-api/app/Services/WhatsappNotificationService.php`
- `backend-api/app/Services/BroadcastCampaignService.php`
- `backend-api/app/Jobs/DispatchAttendanceWhatsappNotification.php`

### 2. Delivery webhook

Gateway harus mengirim callback ke:

`/api/whatsapp/webhook`

Webhook ini dipakai untuk:

- audit event callback
- match ke `gateway_message_id`
- menaikkan status notifikasi dari `sent` menjadi `delivered`

Referensi implementasi:

- `backend-api/app/Http/Controllers/Api/WhatsappController.php`
- `backend-api/app/Services/WhatsappWebhookService.php`

### 3. Skip observability

Kasus berikut tidak lagi dicatat sebagai `failed`:

- nomor parent/pegawai tidak ada
- switch global WhatsApp nonaktif
- konfigurasi gateway belum lengkap

Kasus tersebut sekarang dicatat di:

- tabel `whatsapp_notification_skips`

## Langkah Penerapan

### 1. Migration

Jalankan migration:

```bash
cd backend-api
php artisan migrate
```

Migration penting:

- `2026_04_06_000001_add_delivery_fields_to_whatsapp_notifications_table.php`
- `2026_04_06_000002_create_whatsapp_webhook_events_table.php`
- `2026_04_06_000003_create_whatsapp_notification_skips_table.php`

### 2. Queue worker

Untuk produksi, pisahkan worker WhatsApp absensi dari worker WhatsApp lain.

Worker yang direkomendasikan:

```bash
php artisan queue:work --queue=attendance-whatsapp --sleep=1 --tries=1 --timeout=180 --max-time=3600
php artisan queue:work --queue=izin-whatsapp,broadcast-whatsapp --sleep=1 --tries=1 --timeout=180 --max-time=3600
php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

Alasannya:

- volume check-in/check-out biasanya paling besar
- antrean WA absensi tidak boleh menahan izin atau broadcast
- jika gateway lambat pada jam masuk/pulang, queue lain tetap jalan

### 3. Scheduler

Pastikan scheduler aktif tiap menit:

```bash
php artisan schedule:run
```

Scheduler yang relevan:

- `whatsapp:retry-failed`
- `whatsapp:cleanup-runtime-logs`

### 4. Isi konfigurasi gateway

Di halaman `WhatsApp Gateway`, isi:

- `API URL`
- `API Key`
- `Device ID / Sender`
- `Webhook Secret`

`Webhook Secret` sekarang wajib. Tanpa secret:

- callback webhook akan ditolak
- delivery tracking tidak aktif

### 5. Set webhook di gateway

Set callback gateway ke:

```text
https://DOMAIN-ANDA/api/whatsapp/webhook
```

Kirim secret yang sama seperti yang disimpan di SIAPS.

## Verifikasi Setelah Deploy

### 1. Verifikasi koneksi device

Di halaman `WhatsApp Gateway`:

- cek `Status Koneksi`
- cek `Device Info Terakhir`
- jika perlu lakukan `Generate QR`

### 2. Verifikasi pengiriman manual

Gunakan tab `Pesan Test & Utilitas`:

- kirim test message
- pastikan response sukses
- pastikan `gateway_message_id` tercatat

### 3. Verifikasi callback delivery

Setelah test message terkirim:

- buka panel `Recent Webhook Delivery`
- pastikan callback muncul
- pastikan `Matched Notification` terisi
- pastikan status row `whatsapp_notifications` berubah menjadi `delivered`

### 4. Verifikasi skip log

Jika nomor parent kosong atau WA global dimatikan:

- buka panel `Skipped Automation & Routing`
- pastikan event skip muncul
- pastikan kasus itu tidak menambah `failed` palsu

## Interpretasi Status

### `pending`

Record dibuat, belum final.

### `sent`

Gateway menerima request kirim.

### `delivered`

Gateway callback berhasil match ke `gateway_message_id`.

### `failed`

Gateway benar-benar error, timeout, atau reject.

### `skip log`

SIAPS sengaja tidak mencoba kirim karena syarat dasar tidak terpenuhi.

## Catatan Operasional

### Absensi

Notifikasi WA absensi sekarang hanya lewat observer `Absensi` dan diproses async di queue:

- `attendance-whatsapp`

Jalur ganda di `SimpleAttendanceController` sudah dibuang untuk menghindari pesan dobel.

Kebijakan yang berlaku sekarang:

- check-in/check-out normal tetap kirim WA ke orang tua
- absensi hasil koreksi/manual tidak kirim WA

Ini mencegah orang tua menerima pesan backfill atau koreksi operator yang bisa membingungkan.

Rekomendasi deployment:

- jalankan `attendance-whatsapp` di worker sendiri
- jangan gabungkan lagi dengan `izin-whatsapp`
- untuk sekolah besar, worker ini yang paling layak diberi prioritas proses

### Broadcast

Endpoint legacy:

`POST /api/whatsapp/broadcast`

sudah deprecated. Gunakan:

`POST /api/broadcast-campaigns`

Jika switch global WhatsApp dimatikan atau gateway belum lengkap:

- channel WhatsApp pada campaign akan ditandai `skipped`
- bukan `failed`

### Retention log

Runtime log dibersihkan otomatis oleh command:

```bash
php artisan whatsapp:cleanup-runtime-logs
```

Default retention:

- webhook events: `30 hari`
- skip logs: `30 hari`

## Checklist Gangguan

Jika WA tidak muncul:

1. cek `Status Koneksi`
2. cek `Switch Global WA`
3. cek `Webhook Secret`
4. cek `Recent Webhook Delivery`
5. cek `Skipped Automation & Routing`
6. cek `whatsapp_notifications`
7. cek worker queue `attendance-whatsapp`
8. cek worker queue `izin-whatsapp` / `broadcast-whatsapp`
9. cek scheduler `whatsapp:retry-failed`

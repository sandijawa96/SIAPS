# Rencana Implementasi Broadcast Message Multi-Channel

## Status Implementasi Saat Ini

- Frontend halaman `Broadcast Message` sudah tersedia dan bisa diakses dari menu.
- Permission broadcast sudah tampil di manajemen role dan sudah bisa di-assign ke role.
- Permission broadcast sudah aktif untuk `Super Admin` dan `Admin`.
- Popup announcement dari payload notifikasi sudah tampil di web dashboard.
- Popup announcement dari payload notifikasi sudah tampil di mobile app.
- Fondasi backend `broadcast_campaigns` sudah tersedia untuk simpan riwayat kampanye dan proses kirim terpadu.
- Channel yang sudah hidup pada fondasi ini: `in-app/popup`, `WhatsApp`, dan `email`.
- Popup flyer dan popup informasi sudah bisa ditampilkan di web dan mobile app.

## Draft Ulang: Pembagian Channel Per Bagian

Bagian ini adalah draft konseptual untuk dikoreksi terlebih dulu.
Belum menjadi keputusan implementasi final.

### 1. Kelompok Channel

#### A. Channel Pengiriman Eksternal

Channel ini mengirim pesan keluar dari aplikasi.

- `WhatsApp`
- `Email`

#### B. Channel Tampilan Internal

Channel ini tidak mengirim keluar sistem, tetapi menampilkan pesan di dalam sistem.

- `Mobile App`
- `Frontend Web`

### 2. Apa Yang Didapat Masing-Masing Channel

#### A. WhatsApp

Tujuan:

- pesan cepat,
- pesan langsung,
- tindak lanjut atau reminder,
- pemberitahuan yang memang harus sampai ke nomor tujuan.

Konten yang didapat:

- judul ringkas atau pembuka pesan,
- isi pesan utama,
- footer opsional,
- link/CTA opsional.

Yang tidak didapat:

- popup modal,
- inbox internal,
- flyer visual penuh seperti di web/app.

Catatan:

- tetap bisa membawa link ke flyer atau halaman detail,
- bukan media utama untuk pengalaman visual.

#### B. Email

Tujuan:

- pesan formal,
- surat/pemberitahuan resmi,
- informasi yang lebih panjang dan rapi.

Konten yang didapat:

- subject,
- body pesan,
- link/CTA opsional,
- gambar opsional bila nanti diaktifkan penuh.

Yang tidak didapat:

- popup,
- inbox internal aplikasi,
- interaksi gaya modal.

Catatan:

- email fokus pada format resmi, bukan notifikasi cepat.

#### C. Mobile App

Tujuan:

- menampilkan informasi di aplikasi mobile,
- memberi notifikasi ke user yang aktif memakai app.

Bentuk tampilan yang bisa didapat:

- `Inbox / notifikasi internal`,
- `Popup informasi`,
- `Popup flyer / modal visual`.

Konten yang didapat:

- judul,
- isi pesan,
- metadata tipe pesan,
- gambar/flyer untuk popup flyer,
- tombol tutup,
- CTA opsional pada popup flyer.

Catatan:

- mobile app adalah tempat tampil,
- bukan channel pengiriman eksternal.

#### D. Frontend Web

Tujuan:

- menampilkan informasi di dashboard/web aplikasi,
- memberi pengumuman saat user aktif di web.

Bentuk tampilan yang bisa didapat:

- `Inbox / notifikasi internal`,
- `Popup informasi`,
- `Popup flyer / modal visual`.

Konten yang didapat:

- judul,
- isi pesan,
- gambar/flyer untuk popup flyer,
- tombol tutup,
- CTA opsional.

Catatan:

- web juga merupakan tempat tampil,
- bukan channel pengiriman keluar sistem.

### 3. Kesimpulan Struktur Yang Lebih Benar

Jangan sejajarkan semua ini dalam satu level:

- `WhatsApp`
- `Email`
- `Mobile App`
- `Frontend Web`
- `Popup`

Karena itu mencampur dua level konsep yang berbeda.

Struktur yang lebih benar:

#### A. Pengiriman Eksternal

- `WhatsApp`
- `Email`

#### B. Tampilan Internal

- `Mobile App`
- `Frontend Web`

#### C. Jenis Tampilan Internal

- `Inbox / notifikasi biasa`
- `Popup informasi`
- `Popup flyer / modal`

### 4. Jenis Notifikasi Yang Akan Ditampilkan

Bagian ini membahas **isi/tujuan notifikasi**, bukan channel.

#### A. Pengumuman Umum

Tujuan:

- menyampaikan informasi umum,
- memberi tahu agenda, perubahan jadwal, atau info sekolah.

Karakter:

- tidak terlalu mendesak,
- bisa dibaca sewaktu user membuka sistem.

Bentuk tampil yang cocok:

- `Inbox internal`
- `Popup informasi`

Channel yang cocok:

- `Mobile App`
- `Frontend Web`
- opsional `WhatsApp` bila memang perlu diperluas

#### B. Reminder

Tujuan:

- mengingatkan user terhadap kegiatan atau kewajiban tertentu.

Contoh:

- reminder absensi,
- reminder rapat,
- reminder pengumpulan tugas/data.

Karakter:

- singkat,
- jelas,
- lebih efektif jika cepat sampai.

Bentuk tampil yang cocok:

- `Inbox internal`
- `WhatsApp`

Channel yang cocok:

- `WhatsApp`
- `Mobile App`
- `Frontend Web`

#### C. Informasi Penting / Mendesak

Tujuan:

- menyampaikan informasi yang harus segera diketahui user.

Contoh:

- perubahan jadwal mendadak,
- gangguan sistem,
- instruksi darurat,
- batas waktu yang sangat dekat.

Karakter:

- prioritas tinggi,
- harus terlihat jelas,
- tidak boleh tenggelam di daftar biasa.

Bentuk tampil yang cocok:

- `Popup informasi`
- `Inbox internal`
- `WhatsApp`

Channel yang cocok:

- `WhatsApp`
- `Mobile App`
- `Frontend Web`
- `Email` bila butuh penjelasan resmi

#### D. Flyer / Poster Pengumuman

Tujuan:

- menampilkan pengumuman visual,
- menyebarkan poster acara atau informasi bergambar.

Contoh:

- kegiatan sekolah,
- webinar,
- lomba,
- agenda tertentu yang butuh flyer.

Karakter:

- visual lebih dominan daripada teks,
- cocok untuk promosi atau pengumuman acara.

Bentuk tampil yang cocok:

- `Popup flyer / modal`

Channel yang cocok:

- `Mobile App`
- `Frontend Web`
- opsional `WhatsApp` dan `Email` hanya sebagai link pendukung, bukan tampilan utama

#### E. Tindak Lanjut / Aksi Diperlukan

Tujuan:

- meminta user melakukan tindakan tertentu.

Contoh:

- lengkapi data,
- cek izin,
- verifikasi sesuatu,
- buka tautan,
- respons terhadap pelanggaran atau monitoring.

Karakter:

- harus jelas apa tindakan lanjutannya,
- sering butuh tombol CTA atau link.

Bentuk tampil yang cocok:

- `Inbox internal`
- `Popup informasi`
- `WhatsApp`
- `Email`

Channel yang cocok:

- semua channel bisa dipakai, tergantung target dan urgensi

#### F. Notifikasi Formal / Resmi

Tujuan:

- menyampaikan pesan yang perlu format lebih formal dan rapi.

Contoh:

- surat pemberitahuan,
- agenda resmi,
- edaran internal,
- pesan ke pegawai/guru yang sifatnya administratif.

Karakter:

- lebih panjang,
- butuh subjek dan struktur yang rapi,
- tidak cocok jika hanya ditampilkan sebagai popup singkat.

Bentuk tampil yang cocok:

- `Email`
- `Inbox internal`

Channel yang cocok:

- `Email`
- `Frontend Web`
- `Mobile App`

### 5. Matrix Sederhana

| Bagian | Peran | Dapat Inbox | Dapat Popup Info | Dapat Popup Flyer | Dapat CTA Link | Dapat Footer | Dapat Gambar |
| --- | --- | --- | --- | --- | --- | --- | --- |
| WhatsApp | Pengiriman eksternal | Tidak | Tidak | Tidak | Ya | Ya | Tidak |
| Email | Pengiriman eksternal | Tidak | Tidak | Tidak | Ya | Tidak | Opsional |
| Mobile App | Tampilan internal | Ya | Ya | Ya | Ya | Tidak | Ya |
| Frontend Web | Tampilan internal | Ya | Ya | Ya | Ya | Tidak | Ya |

### 6. Matrix Ringkas Jenis Notifikasi vs Media

| Jenis Notifikasi | WhatsApp | Email | Mobile App | Frontend Web | Bentuk Internal Utama |
| --- | --- | --- | --- | --- | --- |
| Pengumuman Umum | Opsional | Opsional | Ya | Ya | Inbox / Popup info |
| Reminder | Ya | Opsional | Ya | Ya | Inbox |
| Informasi Penting | Ya | Opsional | Ya | Ya | Popup info |
| Flyer / Poster | Link saja | Opsional | Ya | Ya | Popup flyer |
| Tindak Lanjut / Aksi | Ya | Ya | Ya | Ya | Inbox / Popup info |
| Notifikasi Formal | Tidak utama | Ya | Ya | Ya | Inbox |

### 7. Usulan Aturan UI Nanti

Saat nanti didesain ulang, urutannya sebaiknya:

1. pilih dulu `pesan ini keluar sistem, tampil di sistem, atau keduanya`
2. bila keluar sistem:
   - pilih `WhatsApp`
   - pilih `Email`
3. bila tampil di sistem:
   - pilih `Mobile App`
   - pilih `Frontend Web`
4. lalu pilih bentuk tampil internal:
   - `Inbox`
   - `Popup informasi`
   - `Popup flyer`

### 8. Poin Yang Perlu Anda Koreksi

Silakan koreksi terutama pada hal berikut:

- apakah `Frontend Web` cukup `inbox + popup`, atau nanti perlu `banner/pengumuman dashboard`,
- apakah `Mobile App` dan `Frontend Web` harus selalu punya varian tampilan yang sama,
- apakah `WhatsApp` perlu dukungan gambar langsung atau cukup teks + link,
- apakah `Email` akan dipakai juga untuk broadcast umum, atau hanya untuk kasus tertentu,
- apakah jenis notifikasi di atas sudah cukup, atau perlu ditambah kategori lain seperti `warning pelanggaran` yang berdiri sendiri.

## 1. Tujuan

Menyediakan halaman **Broadcast Message** terpusat untuk:

- atur target penerima,
- tulis pesan,
- preview hasil pesan per channel,
- kirim notifikasi lewat `in-app/push`, `WhatsApp`, `Email`, dan `Popup Pengumuman`,
- pantau hasil kirim dan retry gagal.

Target tampilan popup:

- Web guru/pegawai (dashboard web),
- Mobile app guru/pegawai (dan role lain sesuai target).

## 2. Kondisi Sistem Saat Ini (Audit Singkat)

Komponen yang **sudah ada**:

- In-app notification: `backend-api/app/Http/Controllers/Api/NotificationController.php`
- Endpoint notifikasi: `/api/notifications/*`
- Push FCM: `backend-api/app/Services/PushNotificationService.php`
- Device token registry: `backend-api/app/Http/Controllers/Api/DeviceTokenController.php`
- WhatsApp gateway + broadcast: `backend-api/app/Http/Controllers/Api/WhatsappController.php`
- Log WA delivery: tabel `whatsapp_notifications`
- Notifikasi center web: `frontend/src/components/layout/Header.jsx`
- Notifikasi center mobile: `mobileapp/lib/screens/notification_center_screen.dart`

Gap utama:

- Riwayat detail per-recipient per-channel belum dibuka di UI admin.
- Retry gagal per-channel/per-recipient belum tersedia.
- Penjadwalan kirim masih belum diaktifkan.
- CTA popup di mobile app belum dibuka ke URL eksternal.

## 3. Scope Implementasi V1

Fitur wajib V1:

- Halaman `Broadcast Message` di web admin.
- Composer satu form untuk banyak channel.
- Preview sebelum kirim.
- Channel: `in_app`, `whatsapp`, `email`, `popup`.
- Targeting: role, kelas, daftar user, atau kombinasi.
- Penjadwalan: kirim sekarang atau terjadwal.
- Histori campaign + detail hasil kirim.
- Popup pengumuman tampil di web dan mobile.

Di luar scope V1:

- Editor template drag-drop kompleks.
- A/B testing konten.
- Integrasi provider email pihak ketiga di luar SMTP aktif.

## 4. Desain Arsitektur

## 4.1 Konsep Campaign

Satu broadcast disimpan sebagai **campaign**. Campaign punya:

- metadata kampanye,
- filter target,
- konfigurasi channel,
- isi pesan sumber,
- snapshot preview,
- status eksekusi.

## 4.2 Alur Eksekusi

1. Admin buat campaign di halaman Broadcast.
2. Sistem hitung kandidat penerima dan validasi kontak.
3. Admin lihat preview per channel.
4. Admin kirim sekarang atau jadwalkan.
5. Worker queue mengeksekusi per channel.
6. Sistem menyimpan log hasil sukses/gagal per penerima.
7. Dashboard campaign menampilkan progres + retry.

## 4.3 Strategi Popup Pengumuman

Strategi paling aman untuk V1:

- Popup disimpan sebagai notifikasi in-app dengan metadata khusus di kolom `data`.
- Frontend web dan mobile membaca notifikasi unread dengan marker popup.
- Saat user menutup popup, notifikasi ditandai read.

Manfaat:

- tidak menambah banyak tabel baru di fase awal,
- reuse endpoint dan model notifikasi yang sudah stabil,
- konsisten antara inbox dan popup.

## 4.4 Bentuk Popup Yang Ditargetkan

Popup yang dimaksud adalah **modal overlay pengumuman**, seperti contoh yang Anda tunjukkan:

- muncul di atas halaman aktif dengan backdrop gelap,
- memiliki judul kategori, misalnya `Berita` atau `Pengumuman`,
- memiliki judul konten utama,
- bisa menampilkan gambar/flyer/poster,
- punya tombol `Tutup` dan ikon close,
- opsional punya tombol aksi, misalnya `Buka Detail` atau `Lihat Lampiran`.

Format konten popup V1 yang disarankan:

- `popup_title`
- `popup_subtitle` nullable
- `popup_body` nullable
- `popup_image_url` nullable
- `popup_cta_label` nullable
- `popup_cta_url` nullable
- `popup_variant` (`info|announcement|warning`)
- `popup_requires_ack` boolean
- `popup_show_once` boolean

Artinya, channel `popup` memang diarahkan untuk mendukung pengumuman visual, bukan hanya teks pendek.

## 5. Model Data

Disarankan menambah tabel:

## 5.1 `notification_campaigns`

Kolom inti:

- `id`
- `title`
- `message`
- `type` (`info|warning|success|error`)
- `channels` (json: in_app, whatsapp, email, popup)
- `target_filters` (json: role_ids, kelas_ids, user_ids, scope flags)
- `preview_snapshot` (json)
- `scheduled_at` nullable
- `status` (`draft|scheduled|processing|completed|failed|cancelled`)
- `created_by`
- `started_at` nullable
- `finished_at` nullable
- timestamps

## 5.2 `notification_campaign_deliveries`

Kolom inti:

- `id`
- `campaign_id`
- `user_id` nullable
- `channel` (`in_app|whatsapp|email|popup`)
- `recipient_address` nullable (email/phone/token ringkas)
- `status` (`pending|sent|failed|skipped|read`)
- `error_message` nullable
- `attempt_count`
- `provider_response` json nullable
- `sent_at` nullable
- `read_at` nullable
- timestamps

Catatan:

- Channel WA tetap menulis juga ke `whatsapp_notifications` (kompatibilitas existing monitoring).
- Delivery table jadi source agregasi campaign.

## 6. Kontrak API (Rencana)

Prefix baru: `/api/broadcast-campaigns`

Endpoint inti:

- `GET /api/broadcast-campaigns`
- `POST /api/broadcast-campaigns`
- `GET /api/broadcast-campaigns/{id}`
- `POST /api/broadcast-campaigns/{id}/preview`
- `POST /api/broadcast-campaigns/{id}/send`
- `POST /api/broadcast-campaigns/{id}/retry-failed`
- `POST /api/broadcast-campaigns/{id}/cancel`

Endpoint helper:

- `POST /api/broadcast-campaigns/resolve-targets` untuk estimasi target sebelum simpan.
- `GET /api/broadcast-campaigns/{id}/deliveries` untuk tabel detail hasil.

Endpoint popup consume user:

- `GET /api/notifications/popup/active`
- `POST /api/notifications/{id}/popup-ack`

Catatan kompatibilitas:

- Endpoint lama `/api/notifications/broadcast` dan `/api/whatsapp/broadcast` tetap hidup.
- Halaman Broadcast baru memakai service campaign agar orkestrasi satu pintu.

## 7. Preview Engine

## 7.1 Placeholder V1

Placeholder standar:

- `{{nama}}`
- `{{role}}`
- `{{kelas}}`
- `{{tahun_ajaran}}`
- `{{periode}}`
- `{{tanggal}}`

## 7.2 Output Preview

Preview wajib menampilkan:

- contoh output in-app/popup,
- contoh output email subject+body,
- contoh output WhatsApp body,
- jumlah target valid per channel,
- jumlah target invalid per channel beserta alasan.

## 8. Halaman Web Broadcast (Admin)

Lokasi:

- Tambah menu baru di kategori `Pengaturan Sistem`: `Broadcast Message`.

Komponen halaman:

- Filter target penerima.
- Channel selector (checkbox per channel).
- Composer konten.
- Preview panel.
- Jadwal kirim.
- Tombol `Simpan Draft`, `Preview`, `Kirim`.
- Tabel histori campaign (status, progres, aksi detail/retry).

Validasi UI:

- Minimal 1 channel aktif.
- Minimal 1 target valid.
- Untuk email channel: penerima tanpa email ditandai `skipped`.
- Untuk WA channel: nomor otomatis normalisasi `628...`.

## 9. Integrasi Popup Web dan Mobile

## 9.1 Web

Lokasi integrasi:

- `frontend/src/components/Layout.jsx` atau layer global setara.

Perilaku:

- Saat login dan interval ringan, fetch popup active.
- Tampilkan modal popup sekali per notifikasi unread.
- Aksi `Tutup` memanggil endpoint ack/read.
- Popup prioritas tinggi bisa diberi flag `requires_ack`.
- Jika ada `popup_image_url`, tampilkan gambar/flyer di area konten utama modal.
- Layout modal harus stabil: perubahan panjang teks tidak boleh merusak area gambar dan footer aksi.

## 9.2 Mobile

Lokasi integrasi:

- `mobileapp/lib/screens/main_dashboard.dart` (post-login entry point).

Perilaku:

- Ambil popup active saat dashboard dibuka/resume.
- Tampilkan dialog modal konsisten dengan style app.
- Ack ke backend saat user menutup popup.
- Jika ada flyer, tampilkan dalam rasio yang proporsional dan bisa dibuka penuh bila dibutuhkan.

## 10. Integrasi Email

Backend:

- Tambah service `EmailNotificationService` untuk kirim massal via queue.
- Gunakan konfigurasi SMTP yang sudah dipakai reset password.

Aturan V1:

- Email hanya dikirim ke user dengan email valid.
- Subject default: judul campaign.
- Body plain text terlebih dulu (HTML menyusul).

Observasi:

- Jika SMTP gagal, status delivery `failed`, tidak menggagalkan channel lain.

## 11. Integrasi WhatsApp

Reuse:

- `WhatsappGatewayClient`
- `WhatsappController` logic
- normalisasi nomor `App\Support\PhoneNumber`

Penyesuaian:

- Campaign dispatcher memanggil service WA, bukan langsung dari controller.
- Simpan hasil kirim per recipient ke delivery table.
- Tetap isi `whatsapp_notifications` untuk kompatibilitas dashboard existing.

## 12. Permission dan Akses

Permission baru yang disarankan:

- `view_broadcast_campaigns`
- `manage_broadcast_campaigns`
- `send_broadcast_campaigns`
- `retry_broadcast_campaigns`

Mapping awal:

- `Admin`: full
- `Wakasek_Humas`: view + send + retry
- `Wakasek_Kesiswaan`: view (opsional send internal)

Catatan:

- Tetap hormati data scope (`RoleDataScope`) saat resolve target berbasis kelas.

## 13. Queue, Retry, dan Operasional

Eksekusi channel disarankan via queue job:

- `ProcessBroadcastCampaignJob`
- `SendCampaignChannelChunkJob`

Prinsip:

- Chunking penerima untuk mencegah timeout request.
- Retry otomatis untuk kegagalan sementara.
- Idempotency key per delivery agar tidak dobel kirim.

Monitoring:

- ringkasan campaign pada UI,
- log aplikasi + activity log.

## 14. Rencana Eksekusi Bertahap

## Fase 0 - Persiapan

- Finalisasi kebutuhan UI/UX broadcast.
- Finalisasi permission matrix.
- Finalisasi placeholder standar.

## Fase 1 - Fondasi Backend

- Migration tabel campaign + deliveries.
- Model, repository, service dispatcher.
- Endpoint CRUD + preview + send.

## Fase 2 - Channel Adapter

- Adapter in-app/popup.
- Adapter WhatsApp.
- Adapter email.
- Sinkron status delivery.

## Fase 3 - UI Web Broadcast

- Halaman composer + preview.
- Histori campaign.
- Detail delivery + retry.
- Integrasi menu dan route.

## Fase 4 - Popup Web dan Mobile

- Endpoint popup active/ack.
- Modal popup web global.
- Modal popup mobile global.

## Fase 5 - Hardening

- Permission enforcement.
- Validasi target besar.
- Rate limit dan guard payload.
- UX error handling.

## Fase 6 - UAT dan Go-Live

- Uji role matrix.
- Uji end-to-end per channel.
- Uji performa batch.
- Release bertahap.

## 15. Test Plan

Backend test:

- campaign create/preview/send
- delivery status transition
- role permission access
- popup ack flow
- failure and retry behavior per channel

Frontend web test:

- form validasi composer
- preview render
- histori dan detail delivery
- popup muncul dan ack
- popup visual dengan gambar tetap proporsional di desktop dan mobile browser

Mobile test:

- popup tampil saat dashboard/resume
- ack sukses
- fallback jika offline
- tampilan flyer tidak overflow dan tetap bisa ditutup dengan aman

Integrasi:

- kirim campaign kombinasi channel
- verifikasi hasil sinkron di inbox, WA log, email log, popup.

## 16. Risiko dan Mitigasi

Risiko:

- Mis-target broadcast ke penerima besar.
- WA gateway/down SMTP menghambat sebagian channel.
- Popup spam jika tidak ada deduplikasi ack.

Mitigasi:

- Preview target count wajib sebelum kirim.
- Isolasi per channel, gagal satu channel tidak blokir lainnya.
- Popup tampil berbasis unread/ack dengan cooldown.

## 17. Definisi Selesai (Definition of Done)

Fitur dinyatakan selesai jika:

- Admin/Humas bisa membuat campaign dari satu halaman.
- Preview lintas channel tersedia sebelum send.
- Campaign bisa kirim ke in-app, WA, email, popup.
- Popup pengumuman tampil di web dan mobile.
- Histori campaign dan status per channel terlihat jelas.
- Retry gagal per campaign berjalan.
- Test utama backend/web/mobile lulus.

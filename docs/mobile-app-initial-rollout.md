# Mobile App Initial Rollout

Dokumen ini dipakai untuk rollout awal `SIAPS Mobile` melalui website SIAPS.

## Tujuan

- File aplikasi hanya bisa diunduh setelah login.
- Release pertama SIAPS muncul di `Pusat Aplikasi`.
- Auto update SIAPS memakai release aktif yang sama.

## Prasyarat Deploy

1. Deploy backend terbaru.
2. Jalankan migration.
3. Deploy frontend terbaru.
4. Siapkan APK/IPA hasil build final dari project Flutter SIAPS.

## Command Server

```bash
cd /www/wwwroot/load.sman1sumbercirebon.sch.id
php artisan migrate
php artisan optimize:clear
```

## Jalur UI

- Admin release: `/rilis-mobile`
- Pusat aplikasi setelah login: `/pusat-aplikasi`

## Isian Release Pertama SIAPS

- Nama Aplikasi: `SIAPS Mobile`
- App Key: `siaps`
- Audience: `all`
- Platform: `android`
- Channel: `stable`
- Sumber distribusi: upload file privat
- Update mode awal: `optional`
- Publish: `true`
- Active: `true`

## Aturan Versi

Nilai release harus sama dengan versi hasil build Flutter.

Contoh:

```yaml
version: 1.0.0+1
```

Maka isi:

- `public_version = 1.0.0`
- `build_number = 1`

## Urutan Rollout Aman

1. Build APK SIAPS final.
2. Login sebagai admin ke website.
3. Buka `/rilis-mobile`.
4. Buat release baru untuk `siaps`.
5. Upload APK privat.
6. Isi `public_version` dan `build_number` sesuai build Flutter.
7. Set `update_mode = optional`.
8. Simpan dengan status `published` dan `active`.
9. Login sebagai akun siswa/guru biasa.
10. Pastikan release muncul di `/pusat-aplikasi`.
11. Download dan uji instalasi di perangkat Android nyata.
12. Setelah valid, gunakan release berikutnya untuk policy update yang lebih ketat bila diperlukan.

## Catatan Operasional

- Jangan gunakan URL publik eksternal jika release harus tetap private.
- Untuk rollout pertama, jangan aktifkan `required update` sampai instalasi nyata sudah diverifikasi.
- Update wajib saat ini memblok dashboard app SIAPS di sisi client, tetapi belum menjadi hard block di semua endpoint backend.

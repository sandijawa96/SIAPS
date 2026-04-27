# Runbook Pembersihan Device Binding Legacy Android

Dokumen ini untuk rollout perbaikan bug device binding akibat APK lama mengirim Android build ID seperti `UP1A.231005.007` sebagai `device_id`. Nilai itu bukan ID unik HP, sehingga beberapa HP berbeda bisa dianggap device yang sama.

## Tujuan

- Tetap mempertahankan aturan `1 akun siswa = 1 device`.
- Memaksa siswa keluar dari APK lama dan pindah ke APK baru lewat uninstall dan install ulang.
- Mencegah ID legacy `UP1A...` tersimpan lagi sebagai binding.
- Memigrasikan binding siswa dari ID legacy ke ID baru `siaps-...`.

## Kondisi Sebelum Perbaikan

- APK lama mengirim `device_id` dari `androidInfo.id`.
- Di Android, field itu adalah build OS, contoh `UP1A.231005.007`, bukan ID perangkat unik.
- Backend menganggap nilai itu unik dan memblokir login jika sudah terikat ke siswa lain.
- Auto update aplikasi berjalan setelah login, sehingga APK lama tidak cocok dipakai untuk migrasi masalah ini.

## Kondisi Setelah Perbaikan

- APK baru mengirim `device_id` unik format `siaps-...` yang disimpan di secure storage.
- Backend menolak login dari APK lama yang masih mengirim ID legacy dan meminta reinstall.
- Staff/admin tidak diblokir oleh device binding siswa.
- Jika siswa lama terikat ke `UP1A...`, login dari APK baru akan mengganti binding ke `siaps-...`.
- Setelah binding sudah `siaps-...`, aturan binding kembali ketat.

## Sebelum Deploy

1. Pastikan backend patch device binding sudah siap dan test lulus:

   ```bash
   cd backend-api
   php artisan test --filter=DeviceBindingRegistryTest
   ```

2. Pastikan APK baru memakai build number lebih tinggi dari produksi saat ini.

   Saat runbook ini dibuat:

   ```text
   mobileapp/pubspec.yaml: 1.0.0+3
   mobileapp/android/local.properties: flutter.versionCode=3
   ```

3. Build APK release:

   ```bash
   cd mobileapp
   flutter build apk --release
   ```

4. Siapkan pengumuman resmi bahwa siswa wajib uninstall APK lama lalu install APK baru.

## Deploy

1. Deploy backend patch ke production.

2. Upload APK baru ke Mobile Releases production:

   ```text
   app_key: siaps
   platform: android
   build_number: 3
   update_mode: required
   minimum_supported_build_number: 3
   target_audience: all
   is_published: true
   is_active: true
   ```

3. Pastikan release lama yang aktif untuk app/platform/channel yang sama tidak lagi menjadi release aktif.

4. Jangan mengandalkan flow auto update dari APK lama untuk kasus ini. Distribusi APK baru harus disiapkan lewat kanal luar aplikasi.

## Uji Satu Akun Terdampak

1. Pakai APK lama yang masih mengirim `UP1A...`.
2. Login sebagai siswa terdampak.
3. Login harus gagal dengan pesan reinstall aplikasi.
4. Hapus APK lama dari perangkat.
5. Install APK baru.
6. Login ulang dari APK baru.
7. Cek database: `device_id` siswa harus berubah ke format `siaps-...`.

Query cek satu siswa:

```sql
select id, nama_lengkap, username, email, device_id, device_name, device_bound_at, device_locked
from users
where username = 'ISI_NIS_SISWA';
```

## Monitoring Setelah Deploy

Hitung binding legacy yang masih tersisa:

```sql
select count(*) as total_legacy_bound
from users
where device_locked = true
  and device_id ~ '^[A-Z0-9]{4}\.[0-9]{6}\.[0-9]{3}$';
```

Lihat detail siswa yang masih legacy:

```sql
select id, nama_lengkap, username, email, device_id, device_name, device_bound_at
from users
where device_locked = true
  and device_id ~ '^[A-Z0-9]{4}\.[0-9]{6}\.[0-9]{3}$'
order by device_bound_at desc nulls last;
```

Cek collision aktif:

```sql
select device_id, count(*) as total
from users
where device_id is not null
  and device_locked = true
group by device_id
having count(*) > 1
order by total desc;
```

## Pembersihan Setelah Rollout

Lakukan reset legacy hanya setelah APK baru sudah didistribusikan dan siswa punya jalur install ulang yang jelas.

Reset semua binding legacy:

```sql
update users
set device_id = null,
    device_name = null,
    device_bound_at = null,
    device_locked = false,
    device_info = null,
    last_device_activity = null
where device_locked = true
  and device_id ~ '^[A-Z0-9]{4}\.[0-9]{6}\.[0-9]{3}$';
```

Setelah reset, siswa yang login dari APK baru akan bind ulang ke `siaps-...`. Siswa yang masih memakai APK lama akan tetap ditolak sampai uninstall dan install ulang.

## Verifikasi Setelah Pembersihan

Pastikan tidak ada binding legacy:

```sql
select count(*) as remaining_legacy_bound
from users
where device_locked = true
  and device_id ~ '^[A-Z0-9]{4}\.[0-9]{6}\.[0-9]{3}$';
```

Pastikan binding baru mulai masuk:

```sql
select count(*) as total_fixed_bound
from users
where device_locked = true
  and device_id like 'siaps-%';
```

Pastikan tidak ada collision pada binding baru:

```sql
select device_id, count(*) as total
from users
where device_locked = true
  and device_id like 'siaps-%'
group by device_id
having count(*) > 1
order by total desc;
```

## Catatan Operasional

- Jangan menjadikan `UP1A...`, `TP1A...`, `AP1A...`, atau pola serupa sebagai device unik.
- Jangan reset seluruh device binding sebelum APK baru siap dibagikan, karena siswa akan langsung kehilangan jalur login di APK lama.
- Jika siswa uninstall app atau secure storage hilang, `siaps-...` bisa berubah dan siswa perlu reset device binding manual.
- Auto update tetap berjalan setelah login sesuai desain saat ini, tetapi bukan jalur migrasi untuk kasus legacy `UP1A...`.

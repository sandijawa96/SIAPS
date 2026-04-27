# Checklist Firebase Console

Gunakan checklist ini saat membuat konfigurasi FCM untuk project ini.

Identifier final app:

- Android package name: `id.sch.sman1sumbercirebon.siaps`
- iOS bundle identifier: `id.sch.sman1sumbercirebon.siaps`

## 1. Buat Firebase Project

1. buka Firebase Console
2. klik `Create a project`
3. isi nama project:
   - `SIAPS SMAN 1 Sumber Cirebon`
4. pilih aktif/nonaktif Analytics
5. tunggu project selesai dibuat

## 2. Aktifkan Cloud Messaging

1. buka project Firebase
2. masuk ke `Build > Messaging`
3. pastikan Firebase Cloud Messaging aktif

## 3. Daftarkan Web App

1. di `Project Overview`, klik ikon `</>`
2. isi app nickname:
   - `SIAPS Web`
3. selesaikan registrasi
4. salin nilai berikut dari config web app:

- `apiKey`
- `authDomain`
- `projectId`
- `storageBucket`
- `messagingSenderId`
- `appId`
- `measurementId`

## 4. Buat VAPID Key untuk Web Push

1. buka `Project settings`
2. buka tab `Cloud Messaging`
3. cari bagian `Web configuration`
4. generate `Web Push certificates` jika belum ada
5. salin public key

Nilai ini masuk ke:

- `FIREBASE_VAPID_KEY` BH8_GsJMY4nlsrA5MIV6iK-tUzYRfcSDfkfki_fJ4yOCDvhZNCCteisAzfCrHpb8ojYyj9vd39FOW9nsoE_Wob0

## 5. Siapkan Service Account untuk FCM HTTP v1

1. tetap di `Project settings > Cloud Messaging`
2. tidak perlu mencari legacy server key
3. buka `Project settings > Service accounts`
4. klik `Generate new private key`
5. download file JSON service account

File JSON ini harus disimpan di server/backend, misalnya:

```text
backend-api/storage/app/firebase/service-account.json
```

Nilai env yang dipakai backend:

- `FCM_SERVICE_ACCOUNT_JSON=storage/app/firebase/service-account.json`

Catatan:

- backend project ini sekarang memakai FCM HTTP v1
- `FCM_SERVER_KEY` tidak dipakai lagi

## 6. Daftarkan Android App

1. di `Project Overview`, klik ikon Android
2. isi Android package name:

```text
id.sch.sman1sumbercirebon.siaps
```

3. isi app nickname:
   - `SIAPS Android`
4. register app
5. download `google-services.json`
6. simpan ke:

```text
mobileapp/android/app/google-services.json
```

## 7. Daftarkan iOS App

1. di `Project Overview`, klik ikon iOS
2. isi Apple bundle ID:

```text
id.sch.sman1sumbercirebon.siaps
```

3. isi app nickname:
   - `SIAPS iOS`
4. register app
5. download `GoogleService-Info.plist`
6. simpan ke:

```text
mobileapp/ios/Runner/GoogleService-Info.plist
```

## 8. Isi Backend Env

Masukkan nilai dari Firebase Console ke `backend-api/.env`:

```env
PUSH_ENABLED=true
PUSH_PROVIDER=fcm

FCM_SERVICE_ACCOUNT_JSON=storage/app/firebase/service-account.json
FCM_ANDROID_CHANNEL_ID=siaps_notifications
FIREBASE_API_KEY=
FIREBASE_AUTH_DOMAIN=
FIREBASE_PROJECT_ID=
FIREBASE_STORAGE_BUCKET=
FIREBASE_MESSAGING_SENDER_ID=
FIREBASE_APP_ID=
FIREBASE_MEASUREMENT_ID=
FIREBASE_VAPID_KEY=
```

Lalu jalankan:

```bash
cd backend-api
php artisan optimize:clear
```

## 9. Verifikasi

1. login ke web
2. buka `/pengaturan`
3. cek status push
4. cek device token user muncul
5. klik `Kirim Notifikasi Uji`

## 10. Android Build

1. pastikan file ini ada:

```text
mobileapp/android/app/google-services.json
```

2. jalankan:

```bash
cd mobileapp
flutter pub get
```

3. build ulang Android app

## 11. iOS Build

1. pastikan file ini ada:

```text
mobileapp/ios/Runner/GoogleService-Info.plist
```

2. buka project iOS di Xcode
3. aktifkan:
   - Push Notifications
   - Background Modes
   - Remote notifications
4. setup APNs di Apple Developer dan Firebase

## 12. File yang Tidak Perlu Masuk Git

Jangan commit file sensitif/runtime ini:

- `mobileapp/android/app/google-services.json`
- `mobileapp/ios/Runner/GoogleService-Info.plist`
- env production yang berisi key Firebase

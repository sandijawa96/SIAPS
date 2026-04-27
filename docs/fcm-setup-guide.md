# Panduan Konfigurasi FCM

Dokumen ini menjelaskan langkah konfigurasi Firebase Cloud Messaging (FCM) untuk project ini, mencakup:

- backend Laravel
- frontend web
- mobile app Android
- mobile app iOS
- verifikasi end-to-end
- catatan deploy aaPanel

Dokumen ini mengacu ke implementasi yang sudah ada di codebase:

- backend config: `backend-api/config/push.php`
- backend env example: `backend-api/.env.example`
- backend service: `backend-api/app/Services/PushNotificationService.php`
- backend token API: `backend-api/routes/api.php`
- web push service: `frontend/src/services/pushNotificationService.js`
- web service worker: `frontend/public/firebase-messaging-sw.js`
- mobile push service: `mobileapp/lib/services/push_notification_service.dart`

## 1. Arsitektur Saat Ini

Sistem push saat ini bekerja seperti ini:

1. user login
2. web/mobile meminta permission notifikasi
3. Firebase menghasilkan `push_token`
4. token dikirim ke backend lewat endpoint device token
5. backend menyimpan token per device
6. saat notifikasi dibuat, backend mencoba kirim push ke token aktif
7. notifikasi tetap disimpan juga sebagai inbox in-app

Endpoint backend yang terlibat:

- `GET /api/push/config/web`
- `GET /api/device-tokens`
- `POST /api/device-tokens/register`
- `POST /api/device-tokens/deactivate`

## 2. Persiapan Firebase Project

Buat atau pakai project Firebase yang akan dipakai untuk aplikasi ini.

Langkah:

1. buka Firebase Console
2. buat project baru atau pilih project existing
3. aktifkan Firebase Cloud Messaging
4. tambahkan app yang dibutuhkan:
   - Web app
   - Android app
   - iOS app jika memang dipakai

Data yang nanti dibutuhkan:

- `FIREBASE_API_KEY`
- `FIREBASE_AUTH_DOMAIN`
- `FIREBASE_PROJECT_ID`
- `FIREBASE_STORAGE_BUCKET`
- `FIREBASE_MESSAGING_SENDER_ID`
- `FIREBASE_APP_ID`
- `FIREBASE_MEASUREMENT_ID`
- `FIREBASE_VAPID_KEY`
- `FCM_SERVICE_ACCOUNT_JSON`

Catatan:

- backend project ini sekarang memakai FCM HTTP v1 berbasis service account JSON
- untuk web push, VAPID key wajib

## 2A. Langkah Lengkap di Firebase Console

Bagian ini adalah langkah yang benar-benar Anda lakukan di Firebase Console untuk project ini.

### A. Buat project Firebase

1. buka Firebase Console
2. klik `Create a project`
3. isi nama project, misalnya:
   - `SIAPS Absensi`
4. lanjutkan proses pembuatan project
5. Analytics boleh:
   - diaktifkan jika memang ingin dipakai
   - atau dimatikan jika tidak dibutuhkan

Setelah project selesai dibuat, Anda akan berada di dashboard project Firebase.

### B. Aktifkan Cloud Messaging

1. di sidebar Firebase, buka:
   - `Build`
   - `Messaging`
2. pastikan Firebase Cloud Messaging aktif
3. nanti di sini juga Anda bisa kirim test message jika diperlukan

### C. Daftarkan Web App

Project frontend ini memakai web push, jadi Web App wajib dibuat.

Langkah:

1. di halaman project overview Firebase, klik ikon `</>` untuk menambah Web App
2. isi app nickname, misalnya:
   - `SIAPS Web`
3. hosting Firebase tidak wajib
4. selesai registrasi

Setelah itu Firebase akan menampilkan config seperti ini:

```js
const firebaseConfig = {
  apiKey: "...",
  authDomain: "...",
  projectId: "...",
  storageBucket: "...",
  messagingSenderId: "...",
  appId: "...",
  measurementId: "..."
};
```

Nilai yang harus Anda salin ke backend `.env`:

- `apiKey` -> `FIREBASE_API_KEY`
- `authDomain` -> `FIREBASE_AUTH_DOMAIN`
- `projectId` -> `FIREBASE_PROJECT_ID`
- `storageBucket` -> `FIREBASE_STORAGE_BUCKET`
- `messagingSenderId` -> `FIREBASE_MESSAGING_SENDER_ID`
- `appId` -> `FIREBASE_APP_ID`
- `measurementId` -> `FIREBASE_MEASUREMENT_ID`

### D. Buat Web Push Certificate Key Pair

Ini wajib untuk web push.

Langkah:

1. di Firebase Console buka:
   - `Project settings`
   - tab `Cloud Messaging`
2. cari bagian `Web configuration`
3. buat atau generate `Web Push certificates`
4. salin public key VAPID

Nilai ini masuk ke:

- `FIREBASE_VAPID_KEY`

### E. Siapkan Service Account untuk FCM HTTP v1

Langkah:

1. di `Project settings`
2. buka tab `Service accounts`
3. klik `Generate new private key`
4. konfirmasi download JSON service account
5. simpan file JSON itu di server/backend

Contoh lokasi simpan:

```text
backend-api/storage/app/firebase/service-account.json
```

Catatan penting:

- backend code project ini sudah memakai FCM HTTP v1
- Anda tidak perlu mencari legacy server key
- env yang dipakai adalah path file JSON service account

### F. Daftarkan Android App

Project mobile Flutter ini sekarang memakai identifier final:

- Android applicationId: `id.sch.sman1sumbercirebon.siaps`
- iOS bundle identifier: `id.sch.sman1sumbercirebon.siaps`
- macOS bundle identifier: `id.sch.sman1sumbercirebon.siaps`
- Linux application id: `id.sch.sman1sumbercirebon.siaps`

Langkah di Firebase Console:

1. klik ikon Android untuk menambah Android App
2. isi Android package name

Untuk kondisi sekarang:

```text
id.sch.sman1sumbercirebon.siaps
```

3. isi app nickname, misalnya:
   - `SIAPS Android`
4. SHA-1/SHA-256 boleh ditambahkan sekarang atau nanti
5. klik register app
6. download `google-services.json`
7. simpan file itu ke:

```text
mobileapp/android/app/google-services.json
```

### G. Daftarkan iOS App

Kalau iOS memang akan dipakai, lakukan juga:

1. klik ikon iOS untuk menambah Apple App
2. isi iOS bundle ID
3. isi nickname app
4. download `GoogleService-Info.plist`
5. file itu nanti dimasukkan ke project iOS Flutter

Catatan:

- bundle ID iOS harus sama dengan identifier yang nanti Anda pakai di Xcode
- untuk push iOS, Anda juga perlu APNs key/certificate di Apple Developer

### H. Ringkasan data yang harus dibawa keluar dari Firebase Console

Minimal yang Anda butuhkan dari Firebase Console untuk project ini:

- `FIREBASE_API_KEY`
- `FIREBASE_AUTH_DOMAIN`
- `FIREBASE_PROJECT_ID`
- `FIREBASE_STORAGE_BUCKET`
- `FIREBASE_MESSAGING_SENDER_ID`
- `FIREBASE_APP_ID`
- `FIREBASE_MEASUREMENT_ID`
- `FIREBASE_VAPID_KEY`
- file JSON service account Firebase
- `google-services.json` untuk Android
- `GoogleService-Info.plist` untuk iOS jika dipakai

## 3. Konfigurasi Backend Laravel

Isi env backend di `backend-api/.env`:

```env
PUSH_ENABLED=true
PUSH_PROVIDER=fcm

FCM_ENDPOINT=
FCM_ANDROID_CHANNEL_ID=siaps_notifications
FCM_SERVICE_ACCOUNT_JSON=storage/app/firebase/service-account.json

FIREBASE_API_KEY=ISI_FIREBASE_API_KEY
FIREBASE_AUTH_DOMAIN=ISI_FIREBASE_AUTH_DOMAIN
FIREBASE_PROJECT_ID=ISI_FIREBASE_PROJECT_ID
FIREBASE_STORAGE_BUCKET=ISI_FIREBASE_STORAGE_BUCKET
FIREBASE_MESSAGING_SENDER_ID=ISI_MESSAGING_SENDER_ID
FIREBASE_APP_ID=ISI_FIREBASE_APP_ID
FIREBASE_MEASUREMENT_ID=ISI_FIREBASE_MEASUREMENT_ID
FIREBASE_VAPID_KEY=ISI_WEB_PUSH_CERTIFICATE_KEY_PAIR
```

Sumber bacaan config:

- `backend-api/config/push.php`

Setelah update env, jalankan:

```bash
cd backend-api
php artisan optimize:clear
```

Verifikasi cepat backend:

1. login ke aplikasi web
2. buka `Pengaturan`
3. cek kartu `Notifikasi Aplikasi`
4. status config harus berubah dari belum terkonfigurasi menjadi aktif

## 4. Konfigurasi Web Push

Implementasi web push sudah ada di:

- `frontend/src/services/pushNotificationService.js`
- `frontend/public/firebase-messaging-sw.js`

Yang perlu dipastikan:

1. config Firebase dari backend sudah valid
2. aplikasi web dilayani dari origin yang benar
3. browser mengizinkan notification permission

Langkah setup:

1. pastikan env backend pada bagian Firebase sudah benar
2. build frontend terbaru
3. deploy frontend
4. buka aplikasi web
5. login
6. izinkan permission notifikasi saat diminta browser

Perilaku saat login:

- frontend memanggil `GET /api/push/config/web`
- service worker `firebase-messaging-sw.js` diregister
- token FCM web didaftarkan ke backend via `POST /api/device-tokens/register`

Checklist Firebase Console untuk web:

1. Web App sudah dibuat di Firebase Console
2. VAPID key sudah dibuat di `Project settings > Cloud Messaging`
3. data config Web App sudah masuk ke env backend
4. domain web yang dipakai browser adalah origin yang benar

Verifikasi:

1. buka `Pengaturan`
2. lihat ringkasan:
   - jumlah device token aktif
   - daftar token/device
3. klik `Kirim Notifikasi Uji`

Jika token berhasil terdaftar:

- token akan muncul di daftar device token user
- test push akan tercatat sebagai sukses atau gagal dengan alasan yang lebih jelas

## 5. Konfigurasi Android

Code Android sudah disiapkan di:

- `mobileapp/android/settings.gradle.kts`
- `mobileapp/android/app/build.gradle.kts`
- `mobileapp/android/app/src/main/AndroidManifest.xml`

Yang masih wajib Anda sediakan:

- `mobileapp/android/app/google-services.json`

Nilai Android package name saat ini:

```text
id.sch.sman1sumbercirebon.siaps
```

Sumber:

- `mobileapp/android/app/build.gradle.kts`

Langkah:

1. di Firebase Console, tambahkan Android app
2. gunakan package name yang sama dengan aplikasi Android Anda
3. download `google-services.json`
4. simpan file itu ke:

```text
mobileapp/android/app/google-services.json
```

5. jalankan:

```bash
cd mobileapp
flutter pub get
```

6. build ulang aplikasi Android

Catatan penting:

- `google-services.json` harus cocok persis dengan package name yang sedang dipakai saat build

Catatan:

- service Flutter yang dipakai ada di `mobileapp/lib/services/push_notification_service.dart`
- token didaftarkan ke backend setelah login/auth restore
- Android permission `POST_NOTIFICATIONS` sudah ditambahkan

Verifikasi Android:

1. login ke mobile app
2. cek backend apakah token device bertambah
3. kirim notifikasi uji dari web `Pengaturan`
4. pastikan notifikasi masuk ke device Android

## 6. Konfigurasi iOS

Code iOS native belum selesai sepenuhnya. Yang masih perlu Anda setup manual:

1. tambahkan iOS app di Firebase Console
2. download `GoogleService-Info.plist`
3. masukkan ke project iOS Flutter
4. aktifkan capability:
   - Push Notifications
   - Background Modes
   - Remote notifications
5. setup APNs key/certificate di Apple Developer dan Firebase

Karena environment kerja saat ini tidak memuat toolchain iOS, bagian ini belum divalidasi runtime dari workspace ini.

Checklist Firebase Console untuk iOS:

1. Apple App sudah dibuat di Firebase
2. bundle ID di Firebase sama dengan bundle ID di Xcode
3. `GoogleService-Info.plist` sudah diunduh
4. APNs key sudah terhubung di Firebase Console

## 7. Device Token API

Token disimpan per device. Endpoint yang aktif:

- `GET /api/device-tokens`
- `POST /api/device-tokens/register`
- `POST /api/device-tokens/deactivate`

Payload register yang diharapkan:

```json
{
  "device_id": "unik-per-device",
  "device_name": "nama device",
  "device_type": "web|android|ios",
  "push_token": "token dari firebase",
  "device_info": {
    "platform": "android|ios|web"
  }
}
```

Web dan mobile project ini sudah mengirim payload itu otomatis.

## 8. Test End-to-End yang Disarankan

Urutan test yang paling aman:

1. backend config
   - isi env
   - `php artisan optimize:clear`
2. web push
   - login web
   - izinkan permission
   - cek token muncul di `Pengaturan`
   - klik `Kirim Notifikasi Uji`
3. Android push
   - pasang `google-services.json`
   - login mobile
   - cek token mobile masuk ke backend
   - kirim notifikasi uji
4. inbox parity
   - pastikan notifikasi tetap muncul di inbox aplikasi walau push gagal

## 9. Troubleshooting

### Web tidak dapat token

Cek:

1. browser support Service Worker dan Notifications
2. permission browser tidak diblokir
3. `FIREBASE_VAPID_KEY` terisi
4. config dari `GET /api/push/config/web` valid

### Mobile Android tidak dapat token

Cek:

1. `google-services.json` ada di `mobileapp/android/app/google-services.json`
2. `flutter pub get` sudah dijalankan
3. package name Firebase cocok dengan app Android
4. device/emulator punya Google Play Services

### Push test terkirim sebagai inbox tapi tidak muncul push

Artinya:

- backend notifikasi jalan
- tetapi push gateway/device token/config Firebase masih belum benar

Cek:

1. status `configured` pada response push
2. daftar device token user
3. apakah token aktif
4. file `FCM_SERVICE_ACCOUNT_JSON` ada dan valid
5. VAPID key untuk web

### Pengaturan menampilkan push belum terkonfigurasi

Cek:

1. `.env` backend belum lengkap
2. config cache belum dibersihkan

Jalankan:

```bash
cd backend-api
php artisan optimize:clear
```

## 10. Catatan Deploy aaPanel

Untuk aaPanel / production Linux:

1. upload source code terbaru
2. isi env push di server production
3. jalankan:

```bash
cd /path/ke/backend-api
php artisan optimize:clear
composer dump-autoload
```

4. deploy frontend build terbaru
5. lakukan hard refresh browser atau clear cache PWA bila perlu

Catatan:

- patch backup Windows lokal tidak memengaruhi jalur Linux production
- untuk push web, domain production harus sama dengan origin yang dipakai browser
- service worker `firebase-messaging-sw.js` harus ikut terdeploy di root frontend build

## 11. Rekomendasi Teknis

1. jangan hapus `useRoleManagement` atau `useRoleManagementNew` sekarang
2. arah yang benar adalah shared core + dua wrapper tipis
3. lock dulu konfigurasi push end-to-end
4. setelah push stabil, baru lanjut refactor role hook lebih jauh

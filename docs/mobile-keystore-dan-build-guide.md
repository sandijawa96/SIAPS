# Mobile Keystore dan Build Guide

Dokumen ini menjelaskan:

1. Cara membuat keystore Android untuk signing APK.
2. Cara menghubungkan keystore ke project Flutter.
3. Cara build APK Android.
4. Kondisi build IPA iPhone jika tidak punya MacBook/Xcode lokal.

## 1. Catatan penting sebelum membuat keystore

Untuk update APK ke device yang sudah pernah install aplikasi:

- APK baru harus memakai `package name` yang sama.
- APK baru harus ditandatangani dengan keystore yang sama seperti APK lama.

Jika tanda tangan berbeda:

- Android akan menolak update.
- Solusi hanya uninstall aplikasi lama lalu install ulang.

Package name project ini saat ini:

- `id.sch.sman1sumbercirebon.siaps`

Lihat di:

- `mobileapp/android/app/build.gradle.kts`

## 2. Cara membuat keystore Android di Windows

Jika belum punya keystore produksi, buat dengan `keytool`.

Karena Anda sudah membuat file dengan nama `sman1sumber.jks` dan saat ini menyimpannya di `mobileapp/android/app/`, pakai nama dan lokasi itu secara konsisten.

Contoh command memakai Java bawaan Android Studio:

```powershell
& "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe" -genkeypair `
  -v `
  -keystore "C:\laragon\www\absen-jadi\mobileapp\android\app\sman1sumber.jks" `
  -alias upload `
  -keyalg RSA `
  -keysize 2048 `
  -validity 10000
```

Atau jika `keytool` sudah ada di `PATH`:

```powershell
keytool -genkeypair -v `
  -keystore "C:\laragon\www\absen-jadi\mobileapp\android\app\sman1sumber.jks" `
  -alias upload `
  -keyalg RSA `
  -keysize 2048 `
  -validity 10000
```

Saat diminta, isi:

- `keystore password`
- `key password`
- nama organisasi
- kota
- provinsi
- kode negara

Simpan informasi berikut dengan aman:

- lokasi file `.jks`
- `storePassword`
- `keyAlias`
- `keyPassword`

Jangan hilangkan file ini. Kalau hilang, jalur update APK existing bisa putus.

## 3. Konfigurasi key.properties

Setelah keystore dibuat, buat file:

- `mobileapp/android/key.properties`

Contoh isi:

```properties
storeFile=sman1sumber.jks
storePassword=GANTI_DENGAN_PASSWORD_STORE
keyAlias=sman1sumber
keyPassword=GANTI_DENGAN_PASSWORD_KEY
```

Template sudah tersedia di:

- `mobileapp/android/key.properties.example`

Catatan:

- pada project ini, nilai `storeFile` dibaca relatif dari folder `mobileapp/android/app/`
- karena file Anda ada di `mobileapp/android/app/sman1sumber.jks`, nilainya cukup `sman1sumber.jks`
- file `key.properties` dan file `.jks` sudah di-ignore dari git

## 4. Cara kerja signing di project ini

Project Android sudah disiapkan seperti ini:

- jika `mobileapp/android/key.properties` ada, build `release` akan memakai signing produksi
- jika file itu tidak ada, build `release` fallback ke debug signing agar build lokal tidak mati

Lihat di:

- `mobileapp/android/app/build.gradle.kts`

Untuk distribusi produksi, wajib sediakan:

- `mobileapp/android/key.properties`
- file keystore asli `.jks`

## 5. Build APK Android

### Build release standar

```powershell
cd C:\laragon\www\absen-jadi\mobileapp
D:\flutter\bin\flutter.bat build apk --release
```

### Build release dengan background live tracking eksplisit aktif

```powershell
cd C:\laragon\www\absen-jadi\mobileapp
D:\flutter\bin\flutter.bat build apk --release --dart-define=ENABLE_BACKGROUND_LIVE_TRACKING=true
```

### Build release dengan background live tracking dimatikan

```powershell
cd C:\laragon\www\absen-jadi\mobileapp
D:\flutter\bin\flutter.bat build apk --release --dart-define=ENABLE_BACKGROUND_LIVE_TRACKING=false
```

Output APK:

- `mobileapp/build/app/outputs/flutter-apk/app-release.apk`

## 6. Status background live tracking di project ini

Build `release` sekarang default-nya mengaktifkan background live tracking.

Namun tracking background tetap butuh:

- user role `siswa`
- izin lokasi background `Allow all the time`
- notifikasi foreground service diizinkan
- beberapa vendor Android perlu:
  - battery optimization = `No restrictions`
  - autostart = `On`
  - app dikunci di recent apps

Manifest Android sudah memuat permission yang dibutuhkan:

- `ACCESS_BACKGROUND_LOCATION`
- `FOREGROUND_SERVICE`
- `FOREGROUND_SERVICE_LOCATION`

## 7. Build IPA iPhone tanpa MacBook / tanpa Xcode lokal

Secara praktis:

- Anda tidak bisa build dan sign IPA final untuk iPhone langsung dari Windows ini.

Alasannya:

- build iOS final memerlukan `macOS`
- memerlukan `Xcode`
- memerlukan Apple signing (`certificate`, `provisioning profile`, dan biasanya Apple Developer account)

Jadi di mesin Windows ini:

- APK Android bisa dibuild
- IPA iPhone tidak bisa dibuild final secara lokal

## 8. Opsi jika tidak punya MacBook

Pilihan realistis:

1. Pakai CI/CD macOS cloud
2. Sewa remote Mac
3. Pinjam Mac hanya untuk signing/build iOS

Contoh opsi kerja:

1. `Codemagic`
2. `Bitrise`
3. `GitHub Actions` dengan macOS runner
4. `MacStadium` atau remote Mac lain

Workflow GitHub Actions untuk project ini sudah disiapkan di:

- `.github/workflows/ios-build.yml`

Panduan secret dan signing iOS tanpa Mac lokal ada di:

- `docs/ios-cloud-build-github-actions.md`

## 9. Kebutuhan minimum agar IPA bisa dibuat

Tetap harus ada:

- Apple Developer account
- Bundle Identifier iOS
- Distribution certificate
- Provisioning profile
- akses ke build environment macOS

Command build iOS di environment macOS biasanya:

```bash
flutter build ipa --release
```

Atau jika memakai export options:

```bash
flutter build ipa --release --export-options-plist=ios/ExportOptions.plist
```

## 10. Rekomendasi operasional

Jika target utama sekarang adalah distribusi cepat:

1. pastikan keystore Android produksi benar
2. build ulang APK release dengan signing produksi
3. distribusikan APK ke pengguna Android
4. untuk iPhone, putuskan vendor build cloud/macOS lebih dulu

## 11. Checklist singkat sebelum rilis Android

- `key.properties` sudah benar
- file `.jks` benar dan aman
- package name tidak berubah
- API base URL produksi benar
- background live tracking diuji di device siswa nyata
- update APK diuji di device yang sudah terpasang versi lama
- izin `Allow all the time` sudah lolos uji
- vendor-specific battery restriction sudah diuji

# iOS Cloud Build dengan GitHub Actions

Dokumen ini menyiapkan build IPA di cloud tanpa Mac lokal. Workflow yang dipakai ada di:

- `.github/workflows/ios-build.yml`

Model kerjanya:

1. GitHub Actions menjalankan build di `macos-latest`
2. Flutter dipasang sesuai versi project
3. CocoaPods di-resolve dari folder `mobileapp/ios`
4. sertifikat `.p12` dan provisioning profile `.mobileprovision` diambil dari GitHub Secrets
5. workflow build `IPA` lalu mengunggah artefaknya

## 1. Batasan penting

Tanpa Mac lokal, build iPhone tetap memungkinkan di cloud. Namun tetap wajib ada:

- Apple Developer account aktif
- bundle identifier iOS yang valid
- certificate signing iOS
- provisioning profile yang cocok dengan bundle id

Untuk project ini, bundle id iOS saat ini:

- `id.sch.sman1sumbercirebon.siaps`

## 2. Distribusi iPhone tidak sama dengan Android

Untuk Android, APK bisa dibagikan langsung dan di-update manual jika tanda tangan sama.

Untuk iPhone, distribusi di luar App Store punya batasan:

- `app-store`: untuk App Store / TestFlight
- `ad-hoc`: hanya device yang UDID-nya sudah didaftarkan
- `development`: untuk device development
- `enterprise`: hanya untuk akun enterprise internal

Jika target pengguna umum, jalur yang paling realistis adalah:

1. `TestFlight`
2. `App Store`

## 3. Secrets yang harus diisi di GitHub

Masuk ke:

1. repository GitHub
2. `Settings`
3. `Secrets and variables`
4. `Actions`

Buat secret berikut:

- `IOS_CERTIFICATE_P12_BASE64`
- `IOS_CERTIFICATE_PASSWORD`
- `IOS_PROVISIONING_PROFILE_BASE64`

Workflow akan membaca `team id`, `profile name`, dan `bundle id` otomatis saat runtime.

## 4. Cara menyiapkan file signing dari Windows

### Opsi A: Anda sudah punya `.p12` dan `.mobileprovision`

Kalau sudah punya:

- file sertifikat distribusi `.p12`
- password `.p12`
- provisioning profile `.mobileprovision`

langsung lanjut ke bagian base64.

### Opsi B: Buat dari Windows tanpa Mac

Anda tetap bisa menyiapkan material signing dari Windows, tetapi tetap perlu akses ke Apple Developer portal.

#### Generate private key dan CSR

Contoh dengan OpenSSL:

```powershell
openssl genrsa -out ios_distribution.key 2048
openssl req -new -key ios_distribution.key -out ios_distribution.csr
```

Upload `ios_distribution.csr` ke Apple Developer portal untuk membuat certificate distribusi iOS.

Setelah Apple memberi file `.cer`, ubah ke `.p12`:

```powershell
openssl x509 -in ios_distribution.cer -inform DER -out ios_distribution.pem -outform PEM
openssl pkcs12 -export -inkey ios_distribution.key -in ios_distribution.pem -out ios_distribution.p12 -name "Apple Distribution"
```

Provisioning profile dibuat dari Apple Developer portal sesuai metode distribusi:

- App Store
- Ad Hoc
- Development

Bundle ID harus cocok:

- `id.sch.sman1sumbercirebon.siaps`

## 5. Ubah file signing menjadi base64 di Windows

### Sertifikat `.p12`

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\to\ios_distribution.p12")) | Set-Clipboard
```

Paste hasil clipboard itu ke secret:

- `IOS_CERTIFICATE_P12_BASE64`

### Provisioning profile `.mobileprovision`

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\to\profile.mobileprovision")) | Set-Clipboard
```

Paste hasil clipboard itu ke secret:

- `IOS_PROVISIONING_PROFILE_BASE64`

### Password `.p12`

Isi apa adanya ke secret:

- `IOS_CERTIFICATE_PASSWORD`

## 6. Menjalankan workflow

Masuk ke:

1. tab `Actions`
2. pilih workflow `iOS Cloud Build`
3. klik `Run workflow`

Input yang tersedia:

- `export_method`
- `build_name`
- `build_number`

Contoh:

- `export_method`: `app-store`
- `build_name`: `1.0.1`
- `build_number`: `2`

## 7. Artefak hasil build

Jika sukses, artifact akan tersedia di hasil workflow:

- file `.ipa`
- file `.xcarchive`

## 8. Catatan teknis workflow ini

Workflow ini sengaja:

- memakai `Flutter 3.32.5` agar sesuai dengan project
- memakai `macos-latest` karena build iOS final memang wajib macOS
- mem-patch signing settings hanya di runner CI, bukan memaksa hardcode team id di repo
- mengimpor provisioning profile dan certificate dari secret agar tidak bocor ke git

## 9. Jika build gagal

Titik gagal yang paling umum:

1. bundle id tidak cocok dengan provisioning profile
2. certificate tidak cocok dengan private key
3. profile jenis `ad-hoc` dipakai untuk `app-store`
4. akun Apple Developer belum aktif atau profile/certificate kadaluarsa
5. capability iOS di Apple portal belum sesuai dengan app

## 10. Validasi yang masih perlu Anda lakukan

Dari Windows ini saya bisa menyiapkan workflow dan struktur project-nya, tetapi saya tidak bisa mengeksekusi build IPA final karena GitHub Actions macOS belum berjalan dari sini.

Setelah secrets diisi, lakukan satu kali run workflow untuk memastikan:

1. signing cocok
2. archive berhasil
3. IPA valid untuk jalur distribusi yang Anda pilih

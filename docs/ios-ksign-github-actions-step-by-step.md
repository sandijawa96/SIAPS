# Build iPhone dengan GitHub Actions untuk Ksign

Panduan ini untuk build aplikasi Flutter `mobileapp` menjadi unsigned `.ipa`, lalu `.ipa` itu ditandatangani lewat Ksign.

Workflow yang dipakai:

```text
.github/workflows/ios-unsigned-build.yml
```

Hasil workflow:

```text
siaps-ios-unsigned-<nomor-run>.ipa
```

File ini belum ditandatangani Apple. Upload file itu ke Ksign.

## 1. Pastikan file workflow sudah ada di GitHub

Di repository GitHub, pastikan file ini ikut terupload:

```text
.github/workflows/ios-unsigned-build.yml
```

Kalau file ini hanya ada di laptop lokal, commit dan push dulu ke GitHub.

Contoh dari root project:

```powershell
git add .github/workflows/ios-unsigned-build.yml
git commit -m "Add unsigned iOS build workflow for Ksign"
git push
```

Kalau folder root project belum terhubung ke GitHub, upload file tersebut lewat halaman GitHub:

1. buka repository GitHub
2. klik `Add file`
3. pilih `Upload files`
4. upload file ke path `.github/workflows/ios-unsigned-build.yml`
5. klik `Commit changes`

## 2. Tambahkan secret Firebase iOS jika dipakai

Project ini memakai Firebase. File berikut tidak masuk git:

```text
mobileapp/ios/Runner/GoogleService-Info.plist
```

Jika file itu diperlukan di build GitHub, buat GitHub Secret:

```text
IOS_GOOGLE_SERVICE_INFO_PLIST_BASE64
```

Cara membuat nilainya dari Windows:

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\GoogleService-Info.plist")) | Set-Clipboard
```

Lalu di GitHub:

1. buka repository
2. masuk `Settings`
3. pilih `Secrets and variables`
4. pilih `Actions`
5. klik `New repository secret`
6. Name: `IOS_GOOGLE_SERVICE_INFO_PLIST_BASE64`
7. Value: paste dari clipboard
8. klik `Add secret`

Jika secret ini belum diisi, workflow tetap mencoba build, tetapi akan memberi warning.

## 3. Jalankan workflow

Di GitHub:

1. buka tab `Actions`
2. pilih `iOS Unsigned Build for Ksign`
3. klik `Run workflow`
4. isi:
   - `build_name`: contoh `1.0.0`
   - `build_number`: contoh `5`
5. klik tombol hijau `Run workflow`

Tunggu sampai status workflow hijau/success.

## 4. Download artifact IPA

Setelah workflow selesai:

1. buka run workflow yang selesai
2. scroll ke bagian `Artifacts`
3. download artifact:

```text
ios-unsigned-ipa-ksign-<nomor-run>
```

Isi zip artifact itu adalah file:

```text
siaps-ios-unsigned-<nomor-run>.ipa
```

## 5. Upload ke Ksign

Di Ksign:

1. upload file `.ipa` dari artifact GitHub
2. pilih certificate/profile yang disediakan Ksign
3. pastikan bundle id tetap:

```text
id.sch.sman1sumbercirebon.siaps
```

4. proses signing
5. download hasil signed `.ipa`
6. install ke iPhone sesuai instruksi Ksign

## 6. Catatan penting

- Unsigned `.ipa` dari GitHub belum bisa langsung diinstall ke iPhone.
- File yang bisa diinstall adalah `.ipa` hasil signing dari Ksign.
- Jika Ksign certificate revoke, aplikasi iPhone bisa berhenti terbuka.
- Push notification iOS butuh entitlement APNs. Jika Ksign tidak menyediakan entitlement push notification, FCM iOS bisa tidak jalan walaupun aplikasi berhasil terinstall.
- Jalur Ksign cocok untuk testing/internal, bukan distribusi resmi TestFlight/App Store.

## 7. Kalau workflow gagal

Cek error di step yang merah.

Penyebab umum:

1. `flutter pub get` gagal karena dependency bermasalah.
2. `pod install` gagal karena CocoaPods dependency belum cocok.
3. `GoogleService-Info.plist` tidak tersedia jika Firebase iOS butuh file itu.
4. `Runner.app tidak ditemukan` berarti build iOS gagal sebelum packaging.

Jika gagal, buka run workflow, klik step merah, lalu baca log paling bawah.

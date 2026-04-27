# Rencana Deployment MobileApp ke SIAPS

Tanggal penyusunan: 7 April 2026

## Tujuan

Dokumen ini merangkum:

- kondisi aplikasi mobile saat ini di repo
- kelayakan distribusi mobileapp melalui website SIAPS tanpa Play Store atau App Store
- model update Android dan iPhone yang realistis
- perbedaan versi Android dan iPhone
- model update `wajib` dan `tidak wajib`
- risiko operasional, teknis, dan kebijakan distribusi

## Ringkasan Eksekutif

Kesimpulan utamanya:

1. **Android** bisa didistribusikan lewat website SIAPS dan tetap bisa punya alur update tanpa Play Store.
2. **Android** bisa punya mekanisme "auto-update" berbasis cek versi dari backend, tetapi pada perangkat Android normal itu tetap berujung ke **installer sistem**. Artinya ini bukan silent update penuh.
3. **iPhone** tidak punya jalur distribusi umum via website yang setara Android. Untuk distribusi resmi Apple, opsi realistisnya adalah:
   - `TestFlight`
   - `Ad Hoc` untuk perangkat terbatas
   - `Apple School Manager` / `MDM` untuk perangkat terkelola
   - `Apple Developer Enterprise Program` hanya untuk distribusi internal ke pegawai organisasi, bukan distribusi umum ke siswa/orang luar
4. Jalur `DNS Skibidy + Ksign anti-revoke gratis` adalah jalur **nonresmi**, **tidak stabil**, dan **tidak layak dijadikan fondasi utama** deployment SIAPS.
5. Jika targetnya adalah distribusi mobile lewat website SIAPS untuk publik pengguna sekolah, maka arsitektur yang paling masuk akal adalah:
   - **Android**: file APK/AAB hasil build release di-host dari SIAPS
   - **iPhone**: SIAPS menjadi **pusat informasi rilis**, tetapi distribusi utamanya tetap memakai jalur resmi Apple yang paling cocok
6. Mekanisme update `wajib` dan `tidak wajib` sebaiknya dikelola **per platform** dari backend SIAPS, bukan dari versi tunggal Flutter.

## Fakta Kondisi Repo Saat Ini

### Mobile app

- Aplikasi Flutter mobile ada di `mobileapp/`
- Versi Flutter saat ini:
  - `mobileapp/pubspec.yaml:6`
  - `version: 1.0.0+1`
- Android mengambil versi dari Flutter build variables:
  - `mobileapp/android/app/build.gradle.kts:35`
  - `mobileapp/android/app/build.gradle.kts:36`
- iOS juga mengambil versi dari Flutter build variables:
  - `mobileapp/ios/Runner/Info.plist:19`
  - `mobileapp/ios/Runner/Info.plist:23`

Implikasinya:

- saat ini Android dan iPhone **masih mengikuti satu sumber versi yang sama**
- belum ada sistem rilis terpisah per platform
- belum ada mekanisme backend untuk:
  - cek versi terbaru per platform
  - status update wajib/tidak wajib
  - halaman unduh rilis mobile dari SIAPS

### Infrastruktur backend yang bisa dipakai

Repo backend sudah punya fondasi yang berguna untuk implementasi rilis mobile:

- runtime setting store:
  - `backend-api/app/Services/RuntimeSettingStore.php`
- mobile client sudah mengirim header pembeda client:
  - `mobileapp/lib/services/api_service.dart:34`
  - `X-Client-App: mobileapp`

Artinya:

- SIAPS sudah punya tempat yang cukup untuk menyimpan konfigurasi release ringan
- tetapi untuk release mobile yang serius, **lebih baik pakai tabel release khusus**, bukan hanya runtime setting mentah

## Kebutuhan yang Ingin Dicapai

Kebutuhan yang sedang dituju:

1. aplikasi Android dan iPhone diunduh dari website SIAPS, bukan melalui Play Store atau App Store
2. Android bisa update tanpa Play Store
3. iPhone akan memakai jalur `DNS Skibidy + Ksign anti-revoke gratis`
4. Android dan iPhone punya versi rilis yang berbeda dan bisa dicek terpisah
5. ada 2 model update:
   - `wajib update`
   - `tidak wajib update`

## Analisis Kelayakan Per Platform

## Android

### Apakah bisa didistribusikan dari website SIAPS?

**Bisa.**

Ini adalah target yang paling realistis dari seluruh permintaan.

Modelnya:

- SIAPS menyediakan halaman release mobile
- user Android mengunduh APK dari website SIAPS
- aplikasi Android melakukan pengecekan versi ke backend SIAPS saat launch atau setelah login
- jika ada versi baru:
  - tampilkan update optional
  - atau blokir aplikasi jika update wajib

### Apakah bisa auto-update tanpa Play Store?

**Bisa secara operasional, tetapi tidak silent penuh.**

Yang realistis:

1. app cek metadata versi terbaru dari backend SIAPS
2. app unduh APK baru dari server SIAPS
3. app membuka installer Android
4. user menyetujui proses instalasi

Yang penting:

- pada Android biasa, ini bukan update diam-diam penuh di background
- user tetap akan berinteraksi dengan installer sistem
- jika signature berbeda, upgrade akan gagal

Jadi istilah yang tepat:

- **auto-detect update**: ya
- **auto-download update**: bisa
- **auto-install silent**: umumnya tidak, kecuali skenario khusus device owner/MDM

### Risiko Android

- user harus mengizinkan instalasi dari sumber non-Play Store
- beberapa browser/perangkat punya alur izin yang berbeda
- jika build release ditandatangani dengan keystore berbeda, APK tidak bisa upgrade di atas APK lama
- file APK besar akan membebani bandwidth server SIAPS
- jika metadata release salah, user bisa diarahkan ke file yang tidak sesuai

## iPhone

### Apakah bisa didistribusikan dari website SIAPS seperti Android?

**Tidak dengan tingkat keluwesan yang sama.**

Ini titik paling penting dari keseluruhan rencana.

Distribusi iPhone resmi Apple tidak memberi jalur publik umum via website yang setara Android untuk app sekolah yang dipakai banyak pengguna eksternal.

Opsi resmi Apple yang relevan:

1. `TestFlight`
2. `Ad Hoc distribution`
3. `Apple School Manager` / `MDM` untuk perangkat yang dikelola
4. `Apple Developer Enterprise Program` untuk internal pegawai organisasi melalui sistem internal yang aman

### Posisi `DNS Skibidy + Ksign anti-revoke gratis`

Secara praktis, ini adalah jalur **nonresmi**.

Dokumen ini **tidak merekomendasikan** jalur tersebut sebagai fondasi utama SIAPS, karena:

- tidak berasal dari model distribusi resmi Apple
- stabilitasnya tidak bisa dijamin
- sangat rentan berubah sewaktu-waktu
- ada risiko revoke
- ada risiko support burden tinggi
- ada risiko keamanan dan kepercayaan distribusi file tanda tangan/profil
- sulit dijadikan standar operasional sekolah

Jika tetap dipakai, posisinya seharusnya:

- **opsi komunitas/eksperimental**
- **bukan jalur distribusi utama**
- **bukan jalur yang didukung resmi penuh oleh SIAPS**

### Apakah iPhone bisa punya update otomatis?

Untuk jalur non-App Store/non-TestFlight, **tidak layak diasumsikan punya auto-update yang reliabel**.

Yang realistis untuk iPhone:

- app atau website SIAPS memberi tahu ada versi baru
- user diarahkan ke jalur instalasi yang sedang dipakai
- proses update tetap cenderung manual

Jadi untuk iPhone:

- **version check**: bisa
- **force update gate di aplikasi**: bisa
- **silent auto-update yang stabil**: tidak layak dijadikan asumsi utama

### Risiko iPhone

- ketergantungan ke jalur nonresmi
- revoke atau perubahan perilaku sewaktu-waktu
- support pengguna tinggi karena instalasi/update lebih rapuh
- potensi user menganggap aplikasi rusak padahal yang berubah adalah trust/signature chain
- sulit membuat SLA dukungan yang konsisten
- berbeda perilaku antar perangkat/iOS version

## Rekomendasi Strategi Distribusi

## Strategi yang direkomendasikan

### Android

Gunakan website SIAPS sebagai **release center resmi Android**.

Fitur yang direkomendasikan:

- halaman unduh APK Android
- endpoint cek versi Android
- changelog
- checksum file
- status `wajib update` / `opsional`
- minimum supported version

### iPhone

Jadikan website SIAPS sebagai **release center informasi**, bukan satu-satunya mekanisme distribusi binary.

Pilihan yang direkomendasikan, dari yang paling aman:

1. `TestFlight` untuk fase distribusi yang tidak melalui App Store publik
2. `Apple School Manager` / `MDM` bila perangkat iPhone memang dikelola institusi
3. `Ad Hoc` hanya jika perangkat sangat terbatas dan terdaftar

Posisi `Ksign/DNS`:

- kalau tetap ingin dipakai, jadikan **jalur nonutama**
- SIAPS hanya boleh memperlakukan itu sebagai jalur yang **tidak dijamin**
- jangan jadikan asumsi dasar desain update production

## Rekomendasi Arsitektur SIAPS

### 1. Release metadata per platform

SIAPS perlu menyimpan release terpisah per platform.

Minimal field:

- `platform`
  - `android`
  - `ios`
- `public_version`
- `build_number`
- `release_channel`
  - misalnya `stable`, `internal`, `beta`
- `download_url`
- `checksum_sha256`
- `release_notes`
- `published_at`
- `update_mode`
  - `optional`
  - `required`
- `minimum_supported_version`
- `is_active`

### 2. Halaman website SIAPS untuk mobile release

Satu halaman release center, misalnya:

- kartu `Android`
- kartu `iPhone`

Masing-masing menampilkan:

- versi terbaru
- tanggal rilis
- status update
- tombol unduh / instruksi instalasi
- changelog singkat

### 3. API pengecekan versi

Backend SIAPS perlu endpoint seperti:

- `GET /api/mobile-releases/latest?platform=android`
- `GET /api/mobile-releases/latest?platform=ios`

Atau satu endpoint umum:

- `GET /api/mobile-releases/check`

Payload request ideal:

- `platform`
- `app_version`
- `build_number`

Payload response ideal:

- `has_update`
- `update_mode`
- `latest_version`
- `latest_build_number`
- `minimum_supported_version`
- `download_url`
- `release_notes`

### 4. Status update per platform

Karena user ingin Android dan iPhone berbeda, SIAPS harus bisa membedakan:

- hanya Android yang update
- hanya iPhone yang update
- keduanya update

Ini berarti keputusan update **tidak boleh** bergantung pada satu versi Flutter global saja.

## Rekomendasi Versioning

## Kondisi sekarang

Saat ini versi masih satu sumber dari `pubspec.yaml`.

Artinya:

- Android dan iPhone belum benar-benar independen
- jika Android perlu rilis patch cepat tetapi iPhone belum, sistem versi akan terasa kaku

## Rekomendasi

Tetap boleh memakai `pubspec.yaml` sebagai base version, tetapi pipeline release harus bisa override per platform saat build.

Contoh:

- Android:
  - `1.3.0+103`
- iPhone:
  - `1.3.0+57`

Atau bahkan:

- Android terbaru lebih cepat dari iPhone
- iPhone tetap di build lama sampai siap distribusi

Keputusan update di backend tetap berbasis:

- `platform`
- `version`
- `build_number`

## Rekomendasi Model Update

SIAPS perlu dua mode update:

### 1. `optional`

Perilaku:

- aplikasi memberi notifikasi ada update
- user boleh menunda
- aplikasi tetap bisa dipakai

Cocok untuk:

- perbaikan minor
- peningkatan performa
- perubahan nonkritis

### 2. `required`

Perilaku:

- aplikasi memblokir akses ke area utama
- user harus memperbarui aplikasi

Cocok untuk:

- perubahan API breaking
- perbaikan bug kritis
- masalah keamanan
- perubahan format data yang tidak kompatibel

## Catatan penting per platform

### Android

Mode `required` sangat realistis:

- app bisa menolak lanjut jika versi di bawah `minimum_supported_version`
- user diarahkan ke unduh APK baru

### iPhone

Mode `required` masih bisa diterapkan di level aplikasi, tetapi:

- proses update install-nya belum tentu mulus
- jalur update nonresmi tidak bisa diasumsikan stabil

Artinya:

- **force update gate** bisa dibuat
- **proses update aktual di iPhone** tetap paling berisiko

## Rekomendasi Teknis Implementasi di SIAPS

## Tahap 1

Fokus pada fondasi release management:

1. tabel `mobile_releases`
2. halaman admin SIAPS untuk kelola release Android/iPhone
3. halaman publik/internal `Unduh Mobile SIAPS`
4. endpoint cek versi per platform
5. payload update:
   - optional
   - required

## Tahap 2

Fokus Android:

1. halaman unduh APK di SIAPS
2. app Android cek versi saat startup
3. dialog update optional/required
4. unduh APK dan buka installer sistem
5. checksum verification sebelum install prompt

## Tahap 3

Fokus iPhone:

1. tentukan jalur distribusi utama yang benar-benar akan dipakai
2. jika resmi:
   - integrasikan info TestFlight / jalur Apple yang dipilih
3. jika tetap ada jalur nonresmi:
   - dokumentasikan sebagai unsupported/high-risk path
   - jangan dijadikan satu-satunya jalur wajib produksi

## Rekomendasi Backend Data Model

Untuk kebutuhan ini saya **merekomendasikan tabel khusus**, bukan hanya runtime setting.

Alasan:

- perlu histori release
- perlu changelog
- perlu file URL
- perlu status per platform
- perlu mode update per release

Runtime setting masih berguna untuk:

- pointer release aktif
- toggle maintenance update
- cache config sederhana

## Rekomendasi UI Admin SIAPS

Halaman admin release mobile perlu memuat:

- daftar release Android
- daftar release iPhone
- upload/link file
- changelog
- status publish
- pilihan:
  - `optional`
  - `required`
- penanda:
  - `latest stable`
  - `minimum supported`

## Rekomendasi UI Mobile

### Android

App saat startup:

1. baca versi app saat ini
2. kirim ke endpoint SIAPS
3. jika `optional`, tampilkan dialog update
4. jika `required`, blokir ke layar update

### iPhone

App saat startup:

1. baca versi app saat ini
2. kirim ke endpoint SIAPS
3. jika `optional`, tampilkan notifikasi update
4. jika `required`, blokir
5. arahkan ke jalur distribusi iPhone yang sedang dipakai

## Risiko Utama

## Risiko teknis

1. **Android signature mismatch**
   - APK baru tidak bisa upgrade jika signing key berubah

2. **server bandwidth**
   - hosting APK/IPA langsung dari SIAPS akan menambah beban storage dan bandwidth

3. **release metadata salah**
   - force update bisa memblokir user ke file yang belum siap

4. **fragmentasi platform**
   - Android dan iPhone akan punya jadwal rilis berbeda
   - ini memang diinginkan, tetapi perlu disiplin release management

## Risiko kebijakan dan distribusi

1. **iPhone nonresmi**
   - jalur `Ksign/DNS` tidak punya jaminan keberlanjutan

2. **support burden**
   - tim sekolah akan lebih sering menghadapi isu instalasi iPhone dibanding Android

3. **kepatuhan distribusi**
   - model distribusi iPhone harus berhati-hati agar tidak mengandalkan jalur yang sewaktu-waktu runtuh

## Risiko operasional

1. force update yang salah konfigurasi bisa mengunci user
2. user Android bisa menunda izin unknown sources
3. user iPhone bisa gagal update walaupun backend sudah menandai `required`

## Keputusan yang Saya Rekomendasikan

### Keputusan 1

**Lanjutkan Android via website SIAPS.**

Ini feasible, berguna, dan paling masuk akal.

### Keputusan 2

**Pisahkan release Android dan iPhone di backend.**

Jangan andalkan satu status versi global.

### Keputusan 3

**Gunakan dua mode update: `optional` dan `required` per platform.**

Ini penting dan realistis.

### Keputusan 4

**Untuk iPhone, jangan jadikan `DNS Skibidy + Ksign anti-revoke gratis` sebagai fondasi resmi utama SIAPS.**

Kalau tetap ingin dipakai:

- posisikan sebagai jalur nonutama
- dokumentasikan risikonya
- jangan bangun asumsi bisnis kritis di atasnya

### Keputusan 5

**Jadikan website SIAPS sebagai pusat release dan keputusan update, bukan sebagai satu-satunya mekanisme instalasi iPhone.**

## Rekomendasi Akhir

Arsitektur yang paling sehat:

1. **Android**
   - full support via SIAPS website
   - cek versi dari backend
   - update optional/required
   - unduh APK dari SIAPS

2. **iPhone**
   - cek versi dari backend SIAPS
   - status update tetap diputuskan oleh SIAPS
   - distribusi binary utama tetap memakai jalur Apple yang paling realistis
   - jika jalur nonresmi dipakai, perlakukan sebagai tambahan berisiko tinggi

3. **Backend SIAPS**
   - tabel release khusus
   - endpoint cek versi per platform
   - release center di website

## Panduan Arah Implementasi Berikutnya

Jika dilanjutkan ke tahap implementasi, urutan yang paling aman adalah:

1. buat desain backend `mobile_releases`
2. buat halaman admin release mobile di SIAPS
3. buat halaman website unduh mobile
4. implementasikan version check di app
5. aktifkan mode update `optional`
6. setelah stabil, aktifkan `required`
7. baru setelah itu tetapkan jalur iPhone final yang benar-benar akan dipakai

## Sumber Rujukan Eksternal

Rujukan resmi yang relevan per 7 April 2026:

- Apple Developer Support, `Switching to the Apple Developer Program`
  - https://developer.apple.com/support/switching-to-the-apple-developer-program/
- Apple Developer, `TestFlight`
  - https://developer.apple.com/testflight/
- Apple Developer Enterprise Program reference
  - https://developer.apple.com/programs/enterprise/
- Android Developers, App distribution guidance
  - https://developer.android.com/distribute

## Catatan Penutup

Untuk kebutuhan SIAPS:

- **Android via website SIAPS** adalah jalur yang kuat
- **iPhone via jalur nonresmi** adalah titik risiko paling besar

Karena itu, implementasi sebaiknya dimulai dari arsitektur release center SIAPS yang netral per platform, lalu keputusan distribusi iPhone diperlakukan sebagai keputusan terpisah, bukan disamakan dengan Android.

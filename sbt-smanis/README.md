# SBT SMANIS

Aplikasi Flutter untuk siswa yang membuka CBT SMAN 1 Sumber Cirebon melalui WebView.

## Fokus

- Dashboard siswa: Mulai Ujian, Tentang, Keluar.
- Precheck lokal perangkat.
- WebView ke `https://res.sman1sumbercirebon.sch.id`.
- Proteksi dasar ujian di Android melalui native guard.
- Sinkron konfigurasi, heartbeat sesi, log pelanggaran fokus, dan validasi kode pengawas ke SIAPS.

## Menjalankan

Pastikan Flutter 3.32.5 tersedia, lalu jalankan:

```powershell
flutter pub get
flutter run
```

Default API SIAPS mengarah ke `https://load.sman1sumbercirebon.sch.id/api`.
Untuk emulator lokal, jalankan dengan override:

```powershell
flutter run --dart-define=SIAPS_API_BASE_URL=http://10.0.2.2:8000/api
```

Build APK produksi:

```powershell
flutter build apk --release
```

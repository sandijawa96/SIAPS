# Rencana SBT SMANIS

SBT SMANIS adalah aplikasi siswa saja. Aplikasi ini tidak menambah backend atau frontend baru karena fitur administrasi ujian sudah berada di `https://res.sman1sumbercirebon.sch.id/adm`.

## Alur

1. Siswa membuka aplikasi.
2. Dashboard menampilkan tombol Mulai Ujian, Tentang, dan Keluar.
3. Mulai Ujian menjalankan precheck lokal.
4. Jika perangkat siap, aplikasi membuka WebView ke `https://res.sman1sumbercirebon.sch.id`.
5. Saat ujian, aplikasi mengaktifkan Exam Guard Mode.

## Proteksi Lokal

- Fullscreen saat ujian.
- Blok screenshot dan screen recording dengan `FLAG_SECURE`.
- Blok overlay pihak ketiga di Android 12+ dengan `HIDE_OVERLAY_WINDOWS`.
- Deteksi app keluar fokus, background, split-screen, dan picture-in-picture.
- Navigasi WebView dibatasi ke domain CBT.

## Batasan

Pada HP pribadi siswa, Android tidak mengizinkan aplikasi biasa mematikan semua notifikasi atau floating window secara paksa. Aplikasi ini memberi proteksi dan peringatan lokal. Penguncian sistem penuh membutuhkan device owner/kiosk mode pada perangkat terkelola.

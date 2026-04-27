# Aturan Utama Sistem Absensi

Dokumen ini menjadi acuan kebijakan inti yang wajib dijaga saat pengembangan.

## 1) Kanal Absensi
- Check-in/check-out absensi **hanya** melalui **mobile app**.
- Dashboard web **tidak** boleh menjadi kanal submit absensi.
- Peran dashboard web: monitoring, laporan, dan administrasi.

## 2) Scope Role Absensi
- Scope absensi aplikasi ini adalah **`siswa_only`**.
- Role non-siswa tidak melakukan absensi di aplikasi ini.

## 3) Integrasi Eksternal Pegawai
- Untuk non-siswa/pegawai, kanal absensi menggunakan **JSA** (aplikasi eksternal Pemprov Jawa Barat).
- Di dashboard web internal, status non-siswa menampilkan informasi JSA, bukan status check-in/out aplikasi ini.

## 4) Guardrail Implementasi
- Setiap perubahan terkait absensi wajib menjaga aturan ini.
- Perubahan yang melanggar aturan di atas harus ditolak dalam review.

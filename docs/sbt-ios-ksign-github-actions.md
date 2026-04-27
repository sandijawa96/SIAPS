# Build SBT iPhone dengan GitHub Actions untuk Ksign

Panduan ini untuk aplikasi SBT di folder:

```text
sbt-smanis
```

Workflow yang dipakai:

```text
.github/workflows/sbt-ios-unsigned-build.yml
```

Bundle identifier iOS SBT:

```text
id.sch.sman1sumbercirebon.sbt
```

Bundle id ini disamakan dengan Android `applicationId`.

## Cara menjalankan

1. Buka repository GitHub.
2. Masuk tab `Actions`.
3. Pilih workflow `SBT iOS Unsigned Build for Ksign`.
4. Klik `Run workflow`.
5. Isi bila perlu:
   - `build_name`, contoh `1.0.0`
   - `build_number`, contoh `2`
6. Klik `Run workflow`.

Jika berhasil, artifact yang muncul:

```text
sbt-ios-unsigned-ipa-ksign-<nomor-run>
```

Di dalam artifact ada file:

```text
sbt-smanis-ios-unsigned-<nomor-run>.ipa
```

File ini belum signed. Upload file `.ipa` tersebut ke Ksign.

## Catatan

- Unsigned `.ipa` tidak bisa langsung diinstall ke iPhone.
- File yang dipasang ke iPhone adalah hasil signed dari Ksign.
- Jika Ksign meminta bundle id, gunakan `id.sch.sman1sumbercirebon.sbt`.
- Workflow ini memakai `flutter build ios --release --no-codesign`.
- SBT memakai `webview_flutter`, jadi folder iOS sekarang memiliki `Podfile` agar GitHub runner bisa menjalankan `pod install`.

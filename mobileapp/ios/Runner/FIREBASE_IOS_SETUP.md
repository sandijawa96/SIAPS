# Firebase iOS Setup

File Firebase iOS yang harus diletakkan di folder ini:

```text
mobileapp/ios/Runner/GoogleService-Info.plist
```

Bundle identifier yang harus dipakai di Firebase Console:

```text
id.sch.sman1sumbercirebon.siaps
```

Langkah singkat:

1. buat Apple App di Firebase Console
2. bundle ID harus sama persis:
   - `id.sch.sman1sumbercirebon.siaps`
3. download `GoogleService-Info.plist`
4. letakkan file itu di folder `Runner`
5. buka Xcode
6. pastikan file masuk ke target `Runner`
7. aktifkan capability:
   - Push Notifications
   - Background Modes
   - Remote notifications

Catatan:

- file `GoogleService-Info.plist` jangan dimasukkan ke repository publik
- file ini adalah runtime secret/config artifact, bukan source code biasa

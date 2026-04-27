# Setup Nginx di AA Panel untuk React SPA

## Masalah: Error 404 setelah reload

Ketika mengakses `https://siap.sman1sumbercirebon.sch.id/login` langsung atau setelah reload, server mengembalikan 404 karena tidak ada file fisik `/login`.

## Solusi: Konfigurasi Nginx di AA Panel

### Langkah 1: Masuk ke AA Panel

1. Login ke AA Panel
2. Pilih website `siap.sman1sumbercirebon.sch.id`
3. Klik "Pengaturan" atau "Settings"

### Langkah 2: Edit Konfigurasi Nginx

1. Cari menu "Nginx Config" atau "Konfigurasi Nginx"
2. Tambahkan konfigurasi berikut di dalam blok `server`:

```nginx
location / {
    try_files $uri $uri/ /index.html;
}

# Handle API requests (jika backend di port 8000)
location /api {
    proxy_pass http://localhost:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

### Langkah 3: Restart Nginx

1. Simpan konfigurasi
2. Restart Nginx service
3. Test dengan mengakses `https://siap.sman1sumbercirebon.sch.id/login`

### Alternatif: Jika tidak bisa edit Nginx config

Jika AA Panel tidak mengizinkan edit konfigurasi Nginx, coba:

1. **Gunakan subdirectory**: Deploy di `https://siap.sman1sumbercirebon.sch.id/app/`
2. **Ubah base path** di `vite.config.js`:
   ```js
   export default defineConfig({
     base: "/app/",
     // ... config lainnya
   });
   ```

### Langkah 4: Verifikasi

Test URL berikut harus berfungsi tanpa 404:

- `https://siap.sman1sumbercirebon.sch.id/`
- `https://siap.sman1sumbercirebon.sch.id/login`
- `https://siap.sman1sumbercirebon.sch.id/dashboard`

## Troubleshooting

### Jika masih 404:

1. Periksa apakah file `index.html` ada di root directory
2. Pastikan konfigurasi Nginx sudah disimpan dan direstart
3. Cek log error Nginx di AA Panel

### Jika AA Panel tidak support custom Nginx config:

Gunakan HashRouter sebagai fallback:

```js
// Di router.jsx, ganti createBrowserRouter dengan createHashRouter
import { createHashRouter } from "react-router-dom";

const router = createHashRouter([
  // ... routes yang sama
]);
```

URL akan menjadi: `https://siap.sman1sumbercirebon.sch.id/#/login`

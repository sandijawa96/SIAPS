# Panduan Deploy Frontend ke AA Panel

## Persiapan Sebelum Deploy

### 1. Konfigurasi API

✅ **Sudah dikonfigurasi**: API endpoint sudah diatur ke `https://load.sman1sumbercirebon.sch.id/api`

### 2. Build Production

```bash
cd frontend
npm run build
```

## Langkah-langkah Deploy ke AA Panel

### 1. Upload File ke AA Panel

1. **Login ke AA Panel**

   - Buka panel AA Panel Anda
   - Login dengan kredensial yang diberikan hosting

2. **Akses File Manager**

   - Pilih website/domain yang akan digunakan
   - Buka File Manager atau FTP

3. **Upload Build Files**
   - Masuk ke folder `public_html` atau folder root website
   - Upload semua file dari folder `frontend/build/` ke root website
   - Pastikan file `index.html` ada di root folder

### 2. Konfigurasi Server

#### Jika menggunakan Apache (.htaccess)

File `.htaccess` sudah disediakan di `frontend/public/.htaccess`

- Copy file ini ke root website (sama level dengan index.html)

#### Jika menggunakan Nginx

Tambahkan konfigurasi berikut ke Nginx config di AA Panel:

```nginx
location / {
    try_files $uri $uri/ /index.html;
}

# Handle API requests - proxy ke load.sman1sumbercirebon.sch.id
location /api {
    proxy_pass https://load.sman1sumbercirebon.sch.id/api;
    proxy_set_header Host load.sman1sumbercirebon.sch.id;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;

    # CORS headers jika diperlukan
    proxy_set_header Access-Control-Allow-Origin *;
    proxy_set_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
    proxy_set_header Access-Control-Allow-Headers "Content-Type, Authorization";
}

# Cache static assets
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

### 3. Konfigurasi Domain dan SSL

1. **Setup Domain**

   - Tambahkan domain/subdomain di AA Panel
   - Arahkan ke folder yang berisi file frontend

2. **Aktifkan SSL**
   - Aktifkan SSL gratis (Let's Encrypt) di AA Panel
   - Pastikan website dapat diakses via HTTPS

### 4. Testing

Setelah deploy, test URL berikut:

1. **Homepage**: `https://yourdomain.com/`
2. **Login**: `https://yourdomain.com/#/login`
3. **Dashboard**: `https://yourdomain.com/#/dashboard`

### 5. Troubleshooting

#### Jika halaman menampilkan 404 saat refresh:

- Pastikan konfigurasi server (Apache/Nginx) sudah benar
- Periksa file `.htaccess` sudah ada di root folder

#### Jika API tidak berfungsi:

- Pastikan `load.sman1sumbercirebon.sch.id` dapat diakses
- Periksa CORS configuration di backend
- Cek Network tab di browser untuk error API

#### Jika menggunakan HashRouter:

URL akan menjadi: `https://yourdomain.com/#/login`
Ini normal dan sudah dikonfigurasi untuk kompatibilitas AA Panel.

## File yang Perlu Diupload

Dari folder `frontend/build/`:

- `index.html`
- `assets/` (folder berisi CSS, JS, dan asset lainnya)
- File static lainnya

Dari folder `frontend/public/`:

- `.htaccess` (jika menggunakan Apache)
- File konfigurasi lain sesuai kebutuhan

## Catatan Penting

1. **API Backend**: Pastikan backend API sudah running di `load.sman1sumbercirebon.sch.id`
2. **CORS**: Backend harus mengizinkan request dari domain frontend
3. **HTTPS**: Gunakan HTTPS untuk keamanan
4. **Cache**: Clear browser cache jika ada perubahan tidak terlihat

## Kontak Support

Jika ada masalah deployment, hubungi:

- Support AA Panel
- Administrator sistem

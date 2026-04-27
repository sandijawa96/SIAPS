# 🚨 SOLUSI CORS ERROR

## Error yang Terjadi:

```
Access to XMLHttpRequest at 'https://load.sman1sumbercirebon.sch.id/api/web/login'
from origin 'https://siap.sman1sumbercirebon.sch.id' has been blocked by CORS policy:
Response to preflight request doesn't pass access control check:
No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

## Penjelasan Error:

### 1. **Apa itu CORS?**

CORS (Cross-Origin Resource Sharing) adalah mekanisme keamanan browser yang membatasi request dari satu domain ke domain lain.

### 2. **Mengapa Error Ini Terjadi?**

- **Frontend**: `https://siap.sman1sumbercirebon.sch.id` (domain A)
- **Backend API**: `https://load.sman1sumbercirebon.sch.id` (domain B)
- Browser memblokir request karena backend tidak mengizinkan request dari domain frontend

### 3. **Kapan CORS Error Muncul?**

- Ketika frontend dan backend berada di domain/subdomain yang berbeda
- Backend tidak memiliki konfigurasi CORS yang tepat
- Header `Access-Control-Allow-Origin` tidak ada di response backend

## 🔧 SOLUSI

### Solusi 1: Konfigurasi CORS di Backend (RECOMMENDED)

#### A. Laravel CORS Configuration

File: `backend-api/config/cors.php`

```php
<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://siap.sman1sumbercirebon.sch.id',
        'http://localhost:3000', // untuk development
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

#### B. Atau tambahkan di .env backend:

```env
CORS_ALLOWED_ORIGINS=https://siap.sman1sumbercirebon.sch.id,http://localhost:3000
```

#### C. Atau tambahkan middleware di backend:

File: `backend-api/app/Http/Middleware/Cors.php`

```php
<?php
namespace App\Http\Middleware;

use Closure;

class Cors
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', 'https://siap.sman1sumbercirebon.sch.id');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
```

### Solusi 2: Proxy di Frontend (ALTERNATIF)

#### Update konfigurasi Nginx di AA Panel:

```nginx
# Handle API requests - proxy ke load.sman1sumbercirebon.sch.id
location /api {
    proxy_pass https://load.sman1sumbercirebon.sch.id/api;
    proxy_set_header Host load.sman1sumbercirebon.sch.id;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;

    # Handle CORS
    proxy_hide_header Access-Control-Allow-Origin;
    add_header Access-Control-Allow-Origin "https://siap.sman1sumbercirebon.sch.id" always;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With" always;
    add_header Access-Control-Allow-Credentials "true" always;

    # Handle preflight requests
    if ($request_method = 'OPTIONS') {
        add_header Access-Control-Allow-Origin "https://siap.sman1sumbercirebon.sch.id";
        add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With";
        add_header Access-Control-Max-Age 86400;
        return 204;
    }
}
```

#### Kemudian update API config frontend:

```javascript
// frontend/src/config/api.js
production: {
    baseURL: '/api', // Gunakan relative URL untuk proxy
    timeout: 15000
},
```

### Solusi 3: Menggunakan Same Domain (TERBAIK)

#### Deploy backend dan frontend di domain yang sama:

- **Frontend**: `https://siap.sman1sumbercirebon.sch.id/`
- **Backend**: `https://siap.sman1sumbercirebon.sch.id/api/`

Dengan cara ini tidak ada CORS issue karena same-origin.

## 🎯 REKOMENDASI

### Untuk Production (Pilih salah satu):

1. **Solusi Terbaik**: Deploy backend di subdirectory yang sama

   - Frontend: `https://siap.sman1sumbercirebon.sch.id/`
   - Backend: `https://siap.sman1sumbercirebon.sch.id/api/`

2. **Solusi Kedua**: Konfigurasi CORS di backend Laravel

   - Lebih mudah dan standar
   - Perlu akses ke backend code

3. **Solusi Ketiga**: Proxy di Nginx
   - Jika tidak bisa mengubah backend
   - Perlu akses ke server configuration

## 🔍 Testing CORS

### Test dari browser console:

```javascript
// Test CORS
fetch("https://load.sman1sumbercirebon.sch.id/api/health", {
  method: "GET",
  headers: {
    "Content-Type": "application/json",
  },
})
  .then((response) => response.json())
  .then((data) => console.log("Success:", data))
  .catch((error) => console.error("CORS Error:", error));
```

### Test dengan curl:

```bash
curl -H "Origin: https://siap.sman1sumbercirebon.sch.id" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: Content-Type" \
     -X OPTIONS \
     https://load.sman1sumbercirebon.sch.id/api/web/login
```

## 📞 Next Steps

1. **Hubungi Administrator Backend** untuk mengimplementasi solusi CORS
2. **Atau** gunakan proxy solution di frontend
3. **Atau** deploy backend dan frontend di domain yang sama

Pilih solusi yang paling sesuai dengan setup infrastruktur Anda.

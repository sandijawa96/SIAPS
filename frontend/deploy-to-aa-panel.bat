@echo off
echo ========================================
echo    DEPLOY FRONTEND KE AA PANEL
echo ========================================
echo.

echo [1/3] Building aplikasi untuk production...
call npm run build
if %errorlevel% neq 0 (
    echo ERROR: Build gagal!
    pause
    exit /b 1
)

echo.
echo [2/3] Build berhasil! File siap untuk upload.
echo.
echo LANGKAH SELANJUTNYA:
echo 1. Login ke AA Panel
echo 2. Buka File Manager website Anda
echo 3. Upload semua file dari folder 'build' ke root website (public_html)
echo 4. Pastikan file index.html ada di root folder
echo.
echo FILE YANG PERLU DIUPLOAD:
echo - index.html
echo - assets/ (folder lengkap)
echo - .htaccess (untuk Apache)
echo - File lainnya dari folder build/
echo.

echo [3/3] Konfigurasi Server:
echo.
echo UNTUK APACHE:
echo - File .htaccess sudah disertakan dalam build
echo.
echo UNTUK NGINX:
echo - Gunakan konfigurasi dari file nginx.conf
echo - Tambahkan ke konfigurasi site di AA Panel
echo.

echo ========================================
echo API ENDPOINT: https://load.sman1sumbercirebon.sch.id/api
echo ========================================
echo.
echo Deployment siap! Silakan upload file ke AA Panel.
pause

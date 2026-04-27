@echo off
echo 🚀 Starting setup for Sistem Absensi...

REM Check if composer is installed
where composer >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Composer is not installed. Please install composer first.
    exit /b 1
)

REM Check if php is installed
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ PHP is not installed. Please install PHP first.
    exit /b 1
)

echo 📦 Installing dependencies...
call composer install

echo 📝 Setting up environment file...
if not exist .env (
    copy .env.example .env
    php artisan key:generate
)

echo 🗄️ Setting up database...
php artisan migrate:fresh --seed

echo 🔗 Creating storage link...
php artisan storage:link

echo 📚 Installing additional packages...
call composer require intervention/image
call composer require simplesoftwareio/simple-qrcode
call composer require spatie/laravel-backup
call composer require spatie/laravel-permission

echo 🔄 Running migrations again for new packages...
php artisan migrate

echo ⚙️ Publishing package assets...
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"

echo 🧹 Clearing caches...
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo ✅ Setup completed successfully!
echo.
echo Next steps:
echo 1. Configure your database settings in .env file
echo 2. Configure your mail settings in .env file
echo 3. Configure your storage settings in .env file
echo 4. Run 'php artisan serve' to start the development server
echo 5. Run the test script with 'php test-endpoints.php'
echo.
echo For more information, check the documentation in docs/dokumentasi-final-implementasi.md

pause

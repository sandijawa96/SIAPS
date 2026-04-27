#!/bin/bash

echo "🚀 Starting setup for Sistem Absensi..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install composer first."
    exit 1
fi

# Check if php is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP first."
    exit 1
fi

echo "📦 Installing dependencies..."
composer install

echo "📝 Setting up environment file..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    php artisan key:generate
fi

echo "🗄️ Setting up database..."
php artisan migrate:fresh --seed

echo "🔗 Creating storage link..."
php artisan storage:link

echo "📚 Installing additional packages..."
composer require intervention/image
composer require simplesoftwareio/simple-qrcode
composer require spatie/laravel-backup
composer require spatie/laravel-permission

echo "🔄 Running migrations again for new packages..."
php artisan migrate

echo "⚙️ Publishing package assets..."
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"

echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "🔒 Setting proper permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo "✅ Setup completed successfully!"
echo ""
echo "Next steps:"
echo "1. Configure your database settings in .env file"
echo "2. Configure your mail settings in .env file"
echo "3. Configure your storage settings in .env file"
echo "4. Run 'php artisan serve' to start the development server"
echo "5. Run the test script with 'php test-endpoints.php'"
echo ""
echo "For more information, check the documentation in docs/dokumentasi-final-implementasi.md"

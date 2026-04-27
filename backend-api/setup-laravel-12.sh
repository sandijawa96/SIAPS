#!/bin/bash

echo "🔧 Setting up Laravel 12 Project..."

# Check if composer.json exists
if [ ! -f "composer.json" ]; then
    echo "❌ composer.json not found!"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
if (( $(echo "$PHP_VERSION < 8.2" | bc -l) )); then
    echo "❌ PHP version must be >= 8.2 (current: $PHP_VERSION)"
    exit 1
fi

# Check if .env exists, if not copy from .env.example
if [ ! -f ".env" ]; then
    echo "📄 Creating .env file..."
    cp .env.example .env
    if [ $? -eq 0 ]; then
        echo "✅ Created .env file"
    else
        echo "❌ Failed to create .env file"
        exit 1
    fi
fi

# Generate application key if not set
if ! grep -q "^APP_KEY=" .env || grep -q "^APP_KEY=$" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate
    if [ $? -eq 0 ]; then
        echo "✅ Generated application key"
    else
        echo "❌ Failed to generate application key"
        exit 1
    fi
fi

# Clear all caches
echo "🧹 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo "✅ Cleared all caches"

# Run migrations and seeders
echo "🗄️ Running migrations..."
php artisan migrate:fresh --seed
if [ $? -eq 0 ]; then
    echo "✅ Database migrated and seeded"
else
    echo "❌ Migration failed"
    exit 1
fi

# Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link
if [ $? -eq 0 ]; then
    echo "✅ Storage link created"
else
    echo "❌ Failed to create storage link"
    exit 1
fi

# Optimize
echo "⚡ Optimizing application..."
php artisan optimize
if [ $? -eq 0 ]; then
    echo "✅ Application optimized"
else
    echo "❌ Optimization failed"
    exit 1
fi

echo "
🎉 Laravel 12 setup completed!

Next steps:
1. Configure your database in .env
2. Configure mail settings in .env
3. Configure other services in .env
4. Run 'php artisan serve' to start the development server

For testing:
- Run unit tests: ./vendor/bin/phpunit
- Test API endpoints: php test-endpoints.php

Documentation:
- API endpoints: docs/dokumentasi-api-endpoints.md
- Database schema: docs/skema-database.md
- Features per role: docs/dokumentasi-fitur-aplikasi-per-role.md
"

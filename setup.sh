#!/bin/bash
# Complete setup script for Laravel Sail
# Run this script from the project root directory

set -e  # Exit on any error

echo "🚀 Starting Laravel Customer Management System setup..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop and try again."
    exit 1
fi

# Check if .env exists, if not create it BEFORE any Docker operations
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "🔧 Creating .env file from .env.example..."
        cp .env.example .env
        echo "✅ .env file created from .env.example"
    else
        echo "❌ .env.example file not found. Please ensure .env.example exists in the repository."
        exit 1
    fi
else
    echo "✅ .env file already exists"
fi

# Check if vendor directory exists, if not create it using a temporary PHP 8.4 container
if [ ! -d "vendor" ]; then
    echo "📦 Vendor directory not found. Creating it using a temporary PHP 8.4 container..."

    # Create a temporary container to run composer install
    echo "🔧 Running composer install in temporary PHP 8.4 container..."
    docker run --rm \
        -v "$(pwd):/app" \
        -w /app \
        php:8.4-cli \
        bash -c "apt-get update && apt-get install -y git unzip && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && composer install --no-interaction"

    echo "✅ Vendor directory created successfully!"
else
    echo "✅ Vendor directory already exists"
fi

# Start containers using Laravel Sail
echo "📦 Starting Docker containers with Laravel Sail..."
./vendor/bin/sail up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 15

# Check if containers are running
if ! ./vendor/bin/sail ps | grep -q "laravel.test.*Up"; then
    echo "❌ Laravel container is not running. Check logs with './vendor/bin/sail logs'"
    exit 1
fi

# Install PHP dependencies (in case any are missing)
echo "📚 Installing PHP dependencies..."
./vendor/bin/sail composer install --no-interaction

# Install NPM dependencies
echo "📦 Installing NPM dependencies..."
./vendor/bin/sail npm install --silent

# Generate application key
echo "🔑 Generating application key..."
./vendor/bin/sail artisan key:generate --no-interaction

# Run database migrations
echo "🗄️ Running database migrations..."
./vendor/bin/sail artisan migrate --no-interaction

# Build frontend assets
echo "🏗️ Building frontend assets..."
./vendor/bin/sail npm run build --silent

# Setup storage
echo "💾 Setting up MinIO storage bucket..."
echo "   Access MinIO console at http://localhost:8901"
echo "   Username: sail, Password: password"
echo "   Create a bucket named 'dev-bucket' for file storage"

echo ""
echo "✅ Setup complete! 🎉"
echo ""
echo "🌐 Application available at: http://localhost"
echo "📧 Mailpit available at: http://localhost:8025"
echo "🗄️ MinIO Console available at: http://localhost:8901"
echo "📊 MySQL available at: localhost:3306"
echo "🔴 Redis available at: localhost:6379"
echo "📨 ElasticMQ available at: localhost:9324"
echo ""
echo "🚀 POST-SETUP STEPS (REQUIRED):"
echo "   1. **Create MinIO Bucket**: Visit http://localhost:8901 and create a bucket named 'dev-bucket'"
echo "   2. **Start Queue Workers**: ./vendor/bin/sail artisan queue:work sqs --queue=default,import-export,audit"
echo "   3. **Register User**: Go to http://localhost/register to create your first account"
echo "   4. **Verify Email**: Check Mailpit at http://localhost:8025 for verification emails"
echo "   5. **Start Using**: Once verified, you can log in and start managing customers"
echo ""
echo "💡 Development commands:"
echo "   ./vendor/bin/sail up -d          # Start containers"
echo "   ./vendor/bin/sail down           # Stop containers"
echo "   ./vendor/bin/sail artisan test   # Run tests"
echo "   ./vendor/bin/sail npm run dev    # Start Vite dev server"

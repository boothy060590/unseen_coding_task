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

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "❌ Vendor directory not found. Please run 'composer install' first."
    exit 1
fi

# Start containers
echo "📦 Starting Docker containers..."
./vendor/bin/sail up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 15

# Check if containers are running
if ! ./vendor/bin/sail ps | grep -q "laravel.test.*Up"; then
    echo "❌ Laravel container is not running. Check logs with './vendor/bin/sail logs'"
    exit 1
fi

# Install PHP dependencies
echo "📚 Installing PHP dependencies..."
./vendor/bin/sail composer install --no-interaction

# Install NPM dependencies
echo "📦 Installing NPM dependencies..."
./vendor/bin/sail npm install --silent

# Check if .env exists, if not create it
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
echo "   Access MinIO console at http://localhost:8900"
echo "   Username: sail, Password: password"
echo "   Create a bucket named 'unseen-code-task' for file storage"

echo ""
echo "✅ Setup complete! 🎉"
echo ""
echo "🌐 Application available at: http://localhost"
echo "📧 Mailpit available at: http://localhost:8025"
echo "🗄️ MinIO Console available at: http://localhost:8900"
echo "📊 MySQL available at: localhost:3306"
echo "🔴 Redis available at: localhost:6379"
echo "📨 ElasticMQ available at: localhost:9324"
echo ""
echo "🚀 Next steps:"
echo "   1. Visit http://localhost:8900 to create MinIO bucket 'unseen-code-task'"
echo "   2. Register a new user at http://localhost/register"
echo "   3. Check your email at http://localhost:8025"
echo "   4. Verify your email and start using the system!"
echo ""
echo "💡 Development commands:"
echo "   ./vendor/bin/sail up -d          # Start containers"
echo "   ./vendor/bin/sail down           # Stop containers"
echo "   ./vendor/bin/sail artisan test   # Run tests"
echo "   ./vendor/bin/sail npm run dev    # Start Vite dev server"

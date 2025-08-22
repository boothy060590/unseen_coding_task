# Laravel Customer Management System (CRM)

A production-ready Laravel-based Customer Relationship Management system with authentication, email verification, customer management, audit trails, and data import/export capabilities. Built with Laravel Sail for containerized development.

## Features

- **Authentication System**
  - User registration with email verification
  - Secure login/logout functionality
  - Email verification with custom templates
  
- **Customer Management**
  - CRUD operations for customer records
  - Slug-based URLs for customer profiles
  - Advanced search and filtering capabilities
  - Customer dashboard with statistics
  
- **Data Management**
  - CSV import functionality with background processing
  - Export customers in multiple formats (CSV, Excel, JSON)
  - Progress tracking for import/export operations
  - S3-compatible storage for file management
  
- **Audit Trail**
  - Complete activity logging for all customer changes
  - Activity search and filtering
  - User-specific audit trails
  - Audit data archival to S3 storage
  
- **Security & Multi-tenancy**
  - Users only see their own customer records
  - Form request validation
  - CSRF protection
  - Email verification requirement

## Quick Start

For experienced developers who want to get up and running quickly:

```bash
# Clone and setup
git clone git@github.com:boothy060590/unseen_coding_task.git
cd unseen_code_task

# Run automated setup (recommended)
./setup.sh

# Or manual setup
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail npm install
cp .env.example .env
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run build

# Visit http://localhost:8900 to create MinIO bucket 'unseen-code-task'
# Then register at http://localhost/register
```

## Docker Architecture

This application uses **Laravel Sail** for containerized development with the following services:

### Services Overview

- **laravel.test** - Main PHP 8.4 application container
- **mysql** - MySQL 8.0 database server
- **redis** - Redis cache and session storage
- **minio** - S3-compatible object storage for file uploads
- **mailpit** - Local SMTP server for email testing
- **elasticmq** - Local SQS-compatible queue service

### Port Mapping

- **Application**: http://localhost (port 80)
- **MySQL**: localhost:3306
- **Redis**: localhost:6379
- **MinIO Console**: http://localhost:8900
- **MinIO API**: http://localhost:9000
- **Mailpit UI**: http://localhost:8025
- **ElasticMQ**: http://localhost:9324
- **Vite Dev Server**: http://localhost:5173

## Requirements

- Docker Desktop
- Docker Compose
- Git
- `.env.example` file (included in repository)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd unseen_code_task
   ```

2. **Start Docker containers**
   ```bash
   ./vendor/bin/sail up -d
   ```

3. **Environment Setup**
   ```bash
   # Create .env file from .env.example
   cp .env.example .env
   
   # Generate application key
   ./vendor/bin/sail artisan key:generate
   ```

4. **Install PHP dependencies**
   ```bash
   ./vendor/bin/sail composer install
   ```

5. **Install NPM dependencies**
   ```bash
   ./vendor/bin/sail npm install
   ```

6. **Database Setup**
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

7. **Build frontend assets**
   ```bash
   ./vendor/bin/sail npm run build
   ```

8. **Setup MinIO Storage Buckets**
   ```bash
   # Access MinIO console at http://localhost:8900
   # Username: sail, Password: password
   # Create a bucket named 'unseen-code-task' for file storage
   ```

The application will be available at `http://localhost`

### First-Time Setup Process

When setting up the project for the first time, follow these steps in order:

1. **Prerequisites**: Ensure Docker Desktop is running
2. **Start containers**: `./vendor/bin/sail up -d`
3. **Wait for services**: Give containers 10-15 seconds to fully start
4. **Install dependencies**: Run `./vendor/bin/sail composer install` and `./vendor/bin/sail npm install`
5. **Environment**: Create `.env` file from `.env.example` template
6. **Generate key**: Run `./vendor/bin/sail artisan key:generate`
7. **Database**: Execute `./vendor/bin/sail artisan migrate`
8. **Build assets**: Run `./vendor/bin/sail npm run build`
9. **Storage**: Set up MinIO bucket manually via web console

**Note**: The first run of `composer install` and `npm install` may take several minutes as it downloads all dependencies.

### Setup Troubleshooting

**Common setup issues and solutions:**

- **"Permission denied" errors**: Ensure Docker has proper permissions to access the project directory
- **"Container not found"**: Run `./vendor/bin/sail down` then `./vendor/bin/sail up -d` again
- **"Database connection refused"**: Wait longer for MySQL container to fully start (check with `./vendor/bin/sail logs mysql`)
- **"Composer install fails"**: Ensure the laravel.test container is running: `./vendor/bin/sail ps`
- **"NPM install fails"**: Check if the container has enough memory allocated to Docker
- **"Migration fails"**: Ensure MySQL is fully ready: `./vendor/bin/sail exec mysql mysqladmin ping -h localhost -u sail -ppassword`

**Container health check:**
```bash
./vendor/bin/sail ps
./vendor/bin/sail logs
```

### Complete Setup Script

For convenience, you can run this complete setup script:

```bash
#!/bin/bash
# Complete setup script for Laravel Sail

echo "🚀 Starting Laravel Sail setup..."

# Start containers
echo "📦 Starting Docker containers..."
./vendor/bin/sail up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 10

# Install PHP dependencies
echo "📚 Installing PHP dependencies..."
./vendor/bin/sail composer install

# Install NPM dependencies
echo "📦 Installing NPM dependencies..."
./vendor/bin/sail npm install

# Create .env file
echo "🔧 Creating .env file from .env.example..."
cp .env.example .env

# Generate application key
echo "🔑 Generating application key..."
./vendor/bin/sail artisan key:generate

# Run database migrations
echo "🗄️ Running database migrations..."
./vendor/bin/sail artisan migrate

# Build frontend assets
echo "🏗️ Building frontend assets..."
./vendor/bin/sail npm run build

# Setup storage
echo "💾 Setting up MinIO storage bucket..."
echo "   Access MinIO console at http://localhost:8900"
echo "   Username: sail, Password: password"
echo "   Create a bucket named 'unseen-code-task' for file storage"

echo "✅ Setup complete! Application available at http://localhost"
echo "📧 Mailpit available at http://localhost:8025"
echo "🗄️ MinIO Console available at http://localhost:8900"
```

**💡 Pro Tip**: We've also created an automated `setup.sh` script that handles the entire setup process. Simply run:

```bash
./setup.sh
```

This script will:
- Check Docker is running
- Start all containers
- Install dependencies
- Create .env file if needed
- Run migrations
- Build assets
- Provide next steps

## Development Commands

All commands should be run using Laravel Sail:

```bash
# Start containers
./vendor/bin/sail up -d

# Stop containers
./vendor/bin/sail down

# View logs
./vendor/bin/sail logs

# Access application container shell
./vendor/bin/sail shell

# Run Artisan commands
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan tinker

# Install Composer packages
./vendor/bin/sail composer require package-name

# Install NPM packages
./vendor/bin/sail npm install package-name

# Run development server with hot reload
./vendor/bin/sail npm run dev

# Build for production
./vendor/bin/sail npm run build
```

## Queue Processing

The application uses SQS (ElasticMQ) for background job processing:

```bash
# Start queue worker
./vendor/bin/sail artisan queue:work sqs --queue=default,import-export,audit 


# Process failed jobs
./vendor/bin/sail artisan queue:failed
./vendor/bin/sail artisan queue:retry all
```

## Running Tests

This project includes comprehensive unit and integration tests with 100% coverage on business logic:

### Test Suite Overview

- **Unit Tests** (`--testsuite=Unit`): Test individual components in isolation
  - Services: Business logic validation and processing
  - Repositories: Data access layer and query methods
  - Models: Eloquent model relationships and attributes
  
- **Integration Tests** (`--testsuite=Integration`): Test database interactions
  - Repository implementations with real database
  - Service integrations with actual dependencies
  - Database transaction handling and rollbacks

### Running the Test Suite

```bash
# Run all tests
./vendor/bin/sail artisan test

# Run specific test suites
./vendor/bin/sail artisan test --testsuite=Unit
./vendor/bin/sail artisan test --testsuite=Integration

# Run tests with coverage (requires Xdebug)
./vendor/bin/sail artisan test --coverage

# Run specific test file
./vendor/bin/sail artisan test tests/Unit/Services/CustomerServiceTest.php

# Run tests with verbose output
./vendor/bin/sail artisan test --verbose

# Run tests and stop on first failure
./vendor/bin/sail artisan test --stop-on-failure
```

### Test Database

The test suite uses a separate test database to ensure data isolation:

```bash
# Check test database configuration
./vendor/bin/sail artisan config:show database.connections.mysql_testing

# Refresh test database
./vendor/bin/sail artisan migrate:fresh --env=testing

# Seed test database
./vendor/bin/sail artisan db:seed --env=testing
```

### Writing Tests

When adding new features, ensure you write corresponding tests:

```bash
# Create a new test
./vendor/bin/sail artisan make:test Services/NewServiceTest

# Run only your new test
./vendor/bin/sail artisan test tests/Unit/Services/NewServiceTest.php
```

### Test Structure

```
tests/
├── Feature/           # End-to-end user workflow tests
├── Integration/       # Database and service integration tests
│   ├── Repository/   # Repository implementation tests
│   └── Services/     # Service integration tests
└── Unit/             # Isolated component tests
    └── Services/     # Service unit tests
```

## Email Testing

This application uses **Mailpit** for email testing in development:

1. Visit http://localhost:8025 to access the Mailpit interface
2. Register a new user or trigger email verification
3. Check Mailpit for sent emails
4. All emails sent by the application will appear here

## File Storage

The application uses **MinIO** (S3-compatible) for file storage:

### MinIO Console Access
- URL: http://localhost:8901
- Username: `sail`
- Password: `password`

### Storage Configuration
- Import files are stored in user-specific directories
- Export files are generated and stored temporarily
- Audit logs can be archived to S3 storage
- Files are automatically cleaned up based on retention policies

## Architecture & Design Decisions

### Repository Pattern
- Implemented repository pattern for data access abstraction
- Repositories handle all database queries with filtering capabilities
- Cached repository decorators for performance optimization

### Service Layer
- Business logic is encapsulated in service classes
- Services handle validation, event dispatching, and complex operations
- Clear separation between controllers, services, and repositories

### Event-Driven Architecture
- Customer actions trigger events for audit logging
- Events can be extended for notifications, analytics, etc.
- Uses Laravel's built-in event system

### Caching Strategy
- Redis used for application cache and sessions
- Repository-level caching with automatic invalidation
- Configurable TTL for different cache types

### Queue System
- Background processing using SQS (ElasticMQ)
- Import/export operations run asynchronously
- Progress tracking for long-running operations
- Graceful handling of failed jobs

### Security Measures
- All routes require authentication and email verification
- Form Request classes handle validation and authorization
- CSRF protection on all forms
- User data isolation (multi-tenancy at application level)

### Testing Strategy
- 90+% test coverage on business logic (Services and Repositories)
- Unit tests mock dependencies for isolation
- Integration tests use test database for realistic scenarios
- Feature tests cover complete user workflows

### Business Logic Assumptions
- **Customer Email Uniqueness**: Customer emails are unique within a user's scope (not globally)
- **Import/Export Limits**: Large files are processed asynchronously to prevent timeouts
- **File Retention**: Import/export files are automatically cleaned up after 7 days
- **Audit Retention**: Activity logs are retained indefinitely for compliance purposes
- **User Registration**: All users must provide valid email addresses for verification
- **Data Ownership**: Users can only access and modify their own customer records
- **Queue Processing**: Background jobs use SQS-compatible queue for reliability
- **Storage Strategy**: Files are stored in S3-compatible storage with user-specific paths

## Key Assumptions & Architectural Decisions

### Development Environment
- **Laravel Sail**: Chosen for consistent containerized development across team members
- **PHP 8.4**: Latest stable PHP version with modern features and performance improvements
- **MySQL 8.0**: Robust, production-ready database with JSON support and performance optimizations
- **Redis**: Fast in-memory caching and session storage
- **MinIO**: S3-compatible storage for development, easily swappable for AWS S3 in production

### Application Architecture
- **Repository Pattern**: Abstracted data access layer for testability and maintainability
- **Service Layer**: Business logic encapsulation with clear separation of concerns
- **Event-Driven**: Customer actions trigger events for audit logging and extensibility
- **Queue-Based Processing**: Background job processing for long-running operations
- **Multi-tenant by User**: Each user manages their own customer database (application-level isolation)

### Security & Data Management
- **Email Verification Required**: All users must verify email before accessing the system
- **User Data Isolation**: Users can only access their own customer records
- **Audit Trail**: Complete activity logging for compliance and debugging
- **Soft Deletes**: Customer records are soft-deleted for data recovery
- **Form Request Validation**: Centralized validation and authorization logic

### Performance & Scalability
- **Repository Caching**: Redis-based caching with automatic invalidation
- **Background Processing**: Import/export operations don't block user interface
- **Database Indexing**: Optimized queries with proper database indexing
- **Asset Compilation**: Vite-based frontend build system for development and production

### Testing Strategy
- **Comprehensive Coverage**: Unit tests for business logic, integration tests for data operations
- **Mocking Strategy**: External dependencies mocked in unit tests for isolation

## Database Schema

### Core Tables
- `users` - User accounts with email verification
- `customers` - Customer records with full contact information
- `imports` - Import job tracking and status
- `exports` - Export job tracking and file management
- `activity_log` - Audit trail using spatie/laravel-activitylog

### Key Features
- Soft deletes on customer records
- Timestamps on all tables
- Foreign key constraints for data integrity
- Indexes for performance optimization

## Configuration

### Environment Variables

The project includes a `.env.example` file with the proper configuration. Simply copy it to create your `.env` file:

```bash
cp .env.example .env
```

This will create a `.env` file with all the necessary configuration for Laravel Sail development.

## Troubleshooting

### Common Issues

**Containers won't start:**
```bash
./vendor/bin/sail down
docker system prune -f
./vendor/bin/sail up -d
```

**Permission issues:**
```bash
sudo chown -R $USER:$USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

**Database connection issues:**
```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail down && ./vendor/bin/sail up -d
```

**Queue jobs not processing:**
```bash
./vendor/bin/sail artisan queue:restart
./vendor/bin/sail artisan queue:work sqs --queue=default,import-export,audit
```

## Development Tools

### Available Aliases

You can create shell aliases for convenience:

```bash
# Add to ~/.bashrc or ~/.zshrc
alias sail='./vendor/bin/sail'
alias art='./vendor/bin/sail artisan'
alias test='./vendor/bin/sail artisan test'
```

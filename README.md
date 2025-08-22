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
- **MySQL**: localhost:3307
- **Redis**: localhost:6379
- **MinIO Console**: http://localhost:8901
- **MinIO API**: http://localhost:9001
- **Mailpit UI**: http://localhost:8025
- **ElasticMQ**: http://localhost:9324
- **Vite Dev Server**: http://localhost:5173

## Requirements

- Docker Desktop
- Docker Compose
- Git

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
   cp .env.example .env
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
   ./vendor/bin/sail artisan storage:setup
   ```

The application will be available at `http://localhost`

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
./vendor/bin/sail artisan queue:work

# Process failed jobs
./vendor/bin/sail artisan queue:failed
./vendor/bin/sail artisan queue:retry all
```

## Running Tests

This project includes comprehensive unit and integration tests:

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
```

### Test Structure

- **Unit Tests**: Test individual components (Services, Repositories, Models) in isolation
- **Integration Tests**: Test database interactions and service integrations

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
- 100% test coverage on business logic (Services and Repositories)
- Unit tests mock dependencies for isolation
- Integration tests use test database for realistic scenarios
- Feature tests cover complete user workflows

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

Key environment variables for Docker setup:

```env
# Database (MySQL container)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=unseen_code_task
DB_USERNAME=sail
DB_PASSWORD=password

# Cache & Sessions (Redis container)
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=redis

# Queue (ElasticMQ container)
QUEUE_CONNECTION=sqs
SQS_PREFIX=http://elasticmq:9324/000000000000

# Storage (MinIO container)
FILESYSTEM_DISK=s3
AWS_ENDPOINT=http://localhost:9001
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin

# Email (Mailpit container)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

## Production Deployment

### Docker Production Setup

1. **Build production images**
   ```bash
   docker build -t crm-app .
   ```

2. **Use production docker-compose**
   ```yaml
   # docker-compose.prod.yml
   services:
     app:
       image: crm-app
       environment:
         APP_ENV: production
         APP_DEBUG: false
   ```

3. **Environment Configuration**
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Configure production database
   - Set up external Redis/SQS services
   - Configure S3 storage

4. **Performance Optimization**
   ```bash
   ./vendor/bin/sail artisan config:cache
   ./vendor/bin/sail artisan route:cache
   ./vendor/bin/sail artisan view:cache
   ./vendor/bin/sail artisan event:cache
   ```

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
./vendor/bin/sail artisan queue:work
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

### Debugging

- Xdebug is available in the PHP container
- Set `SAIL_XDEBUG_MODE=develop,debug` in .env to enable
- Configure your IDE to connect to port 9003

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Write/update tests
5. Ensure all tests pass (`./vendor/bin/sail artisan test`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## Assumptions Made

1. **Single-tenant per user**: Each user manages their own customer database
2. **Email is unique identifier**: Customer emails are unique within a user's scope
3. **Background processing**: Large imports/exports are processed asynchronously
4. **File cleanup**: Import/export files are automatically cleaned up after 7 days
5. **Audit retention**: Activity logs are retained indefinitely (can be configured)
6. **Email verification required**: All users must verify their email before accessing the system
7. **Containerized deployment**: Application is designed to run in Docker containers
8. **S3-compatible storage**: Uses MinIO for development, can be swapped for AWS S3 in production

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

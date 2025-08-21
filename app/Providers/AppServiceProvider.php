<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Export;
use App\Models\Import;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\ImportRepositoryInterface;
use App\Contracts\Repositories\ExportRepositoryInterface;
use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Repositories\CustomerRepository;
use App\Repositories\ImportRepository;
use App\Repositories\ExportRepository;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Repositories\Decorators\CachedCustomerRepository;
use App\Repositories\Decorators\CachedImportRepository;
use App\Repositories\Decorators\CachedExportRepository;
use App\Repositories\Decorators\CachedAuditRepository;
use App\Services\CacheService;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register CacheService as singleton - safe because it's stateless
        $this->app->singleton(CacheService::class, function ($app) {
            return new CacheService($app->make('cache.store'));
        });

        // Register UserRepository
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        // Register AuditService with S3 storage and AuditRepository dependency injection
        $this->app->bind(AuditService::class, function ($app) {
            return new AuditService(
                $app->make('filesystem.disk.s3'),
                $app->make(AuditRepositoryInterface::class)
            );
        });

        // Register repository interfaces with their implementations
        $this->app->bind(CustomerRepositoryInterface::class, function ($app) {
            $baseRepository = new CustomerRepository();
            $cacheService = $app->make(CacheService::class);
            $config = $app->make('config');
            
            // Wrap with cache decorator for better performance
            return new CachedCustomerRepository($baseRepository, $cacheService, $config);
        });

        $this->app->bind(ImportRepositoryInterface::class, function ($app) {
            $baseRepository = new ImportRepository();
            $cacheService = $app->make(CacheService::class);
            $config = $app->make('config');
            
            // Wrap with cache decorator for better performance
            return new CachedImportRepository($baseRepository, $cacheService, $config);
        });

        $this->app->bind(ExportRepositoryInterface::class, function ($app) {
            $baseRepository = new ExportRepository();
            $cacheService = $app->make(CacheService::class);
            $config = $app->make('config');
            
            // Wrap with cache decorator for better performance
            return new CachedExportRepository($baseRepository, $cacheService, $config);
        });

        $this->app->bind(AuditRepositoryInterface::class, function ($app) {
            $baseRepository = new AuditRepository();
            $cacheService = $app->make(CacheService::class);
            $config = $app->make('config');
            
            // Wrap with cache decorator for better performance
            return new CachedAuditRepository($baseRepository, $cacheService, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Route model binding with user scoping
        Route::bind('customer', function (string $slug) {
            if (!auth()->check()) {
                throw new ModelNotFoundException();
            }

            return Customer::where('user_id', auth()->id())
                ->where('slug', $slug)
                ->firstOrFail();
        });

        Route::bind('import', function (string $id) {
            if (!auth()->check()) {
                throw new ModelNotFoundException();
            }

            return Import::where('user_id', auth()->id())
                ->where('id', $id)
                ->firstOrFail();
        });

        Route::bind('export', function (string $id) {
            if (!auth()->check()) {
                throw new ModelNotFoundException();
            }

            return Export::where('user_id', auth()->id())
                ->where('id', $id)
                ->firstOrFail();
        });
    }
}

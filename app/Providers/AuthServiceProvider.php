<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Export;
use App\Models\Import;
use App\Policies\CustomerPolicy;
use App\Policies\ExportPolicy;
use App\Policies\ImportPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Customer::class => CustomerPolicy::class,
        Import::class => ImportPolicy::class,
        Export::class => ExportPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define additional gates if needed
        Gate::define('manage-customers', function ($user) {
            return true; // All authenticated users can manage their customers
        });
    }
}

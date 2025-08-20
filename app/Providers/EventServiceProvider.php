<?php

namespace App\Providers;

use App\Events\Customer\CustomerCreated;
use App\Events\Customer\CustomerDeleted;
use App\Events\Customer\CustomerUpdated;
use App\Listeners\Customer\AuditCustomerActivity;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event Service Provider for registering events and listeners
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        CustomerCreated::class => [
            AuditCustomerActivity::class,
        ],

        CustomerUpdated::class => [
            AuditCustomerActivity::class,
        ],

        CustomerDeleted::class => [
            AuditCustomerActivity::class,
        ],
    ];

    /**
     * Register any events for your application
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
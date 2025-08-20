<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Export;
use App\Models\Import;
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
        //
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

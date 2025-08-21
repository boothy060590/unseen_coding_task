<?php

use App\Http\Controllers\Auth\ApiAuthController;
use App\Http\Controllers\Api\CustomerController as ApiCustomerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API routes
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);

// Protected API routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [ApiAuthController::class, 'user']);
    Route::post('/logout', [ApiAuthController::class, 'logout']);

    // Customer API routes
    Route::apiResource('customers', ApiCustomerController::class)->parameters(['customers' => 'customer:slug']);
    
    // Additional customer API endpoints
    Route::get('/customers-search', [ApiCustomerController::class, 'search'])->name('api.customers.search');
    Route::get('/customers-statistics', [ApiCustomerController::class, 'statistics'])->name('api.customers.statistics');
    Route::get('/customers-by-organization/{organization}', [ApiCustomerController::class, 'byOrganization'])->name('api.customers.by-organization');
    Route::post('/customers-bulk', [ApiCustomerController::class, 'bulk'])->name('api.customers.bulk');
});

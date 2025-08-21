<?php

use App\Http\Controllers\Auth\ApiAuthController;
use App\Http\Controllers\Api\CustomerController as ApiCustomerController;
use App\Http\Controllers\Api\ImportController as ApiImportController;
use App\Http\Controllers\Api\ExportController as ApiExportController;
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
    Route::delete('/customers-bulk', [ApiCustomerController::class, 'bulkDelete'])->name('api.customers.bulk-delete');

    // Import API routes
    Route::apiResource('imports', ApiImportController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('/imports/{import}/progress', [ApiImportController::class, 'progress'])->name('api.imports.progress');
    Route::post('/imports/{import}/cancel', [ApiImportController::class, 'cancel'])->name('api.imports.cancel');
    Route::get('/imports-statistics', [ApiImportController::class, 'statistics'])->name('api.imports.statistics');
    Route::get('/imports-recent', [ApiImportController::class, 'recent'])->name('api.imports.recent');

    // Export API routes
    Route::apiResource('exports', ApiExportController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('/exports/{export}/progress', [ApiExportController::class, 'progress'])->name('api.exports.progress');
    Route::get('/exports/{export}/download', [ApiExportController::class, 'download'])->name('api.exports.download');
    Route::get('/exports-statistics', [ApiExportController::class, 'statistics'])->name('api.exports.statistics');
    Route::get('/exports-recent', [ApiExportController::class, 'recent'])->name('api.exports.recent');
    Route::post('/exports-preview', [ApiExportController::class, 'preview'])->name('api.exports.preview');
});

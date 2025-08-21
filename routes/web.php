<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Email verification routes
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
});

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard routes
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/search', [DashboardController::class, 'search'])->name('dashboard.search');
    Route::get('/dashboard/overview', [DashboardController::class, 'overview'])->name('dashboard.overview');
    Route::get('/dashboard/suggestions', [DashboardController::class, 'suggestions'])->name('dashboard.suggestions');

    // Customer routes
    Route::resource('customers', CustomerController::class)->parameters(['customers' => 'customer:slug']);

    // Import/Export routes
    Route::prefix('import-export')->name('import-export.')->group(function () {
        Route::get('/', [ImportExportController::class, 'index'])->name('index');
        
        // Import routes
        Route::get('/import', [ImportExportController::class, 'showImport'])->name('show-import');
        Route::post('/import', [ImportExportController::class, 'import'])->name('import');
        Route::get('/import/{import}/status', [ImportExportController::class, 'showImportStatus'])->name('show-import-status');
        Route::get('/import/{import}/progress', [ImportExportController::class, 'importProgress'])->name('import-progress');
        Route::post('/import/{import}/cancel', [ImportExportController::class, 'cancelImport'])->name('cancel-import');
        Route::delete('/import/{import}', [ImportExportController::class, 'deleteImport'])->name('delete-import');
        
        // Export routes
        Route::get('/export', [ImportExportController::class, 'showExport'])->name('show-export');
        Route::post('/export', [ImportExportController::class, 'export'])->name('export');
        Route::get('/export/{export}/status', [ImportExportController::class, 'showExportStatus'])->name('show-export-status');
        Route::get('/export/{export}/progress', [ImportExportController::class, 'exportProgress'])->name('export-progress');
        Route::get('/export/{export}/download', [ImportExportController::class, 'downloadExport'])->name('download');
        Route::delete('/export/{export}', [ImportExportController::class, 'deleteExport'])->name('delete-export');
    });

    // Audit routes
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [AuditController::class, 'index'])->name('index');
        Route::get('/search', [AuditController::class, 'search'])->name('search');
        Route::get('/customer/{customer}', [AuditController::class, 'customer'])->name('customer');
        Route::get('/activity/{activityId}', [AuditController::class, 'activity'])->name('activity');
        Route::get('/statistics', [AuditController::class, 'statistics'])->name('statistics');
        Route::get('/recent', [AuditController::class, 'recent'])->name('recent');
        Route::post('/export', [AuditController::class, 'export'])->name('export');
        Route::post('/archive', [AuditController::class, 'archive'])->name('archive');
    });
});

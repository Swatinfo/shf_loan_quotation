<?php

use App\Http\Controllers\Api\NotesApiController;
use App\Http\Controllers\Api\SyncApiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/quotation-data', [DashboardController::class, 'quotationData'])->name('dashboard.quotation-data');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User Management
    Route::middleware('permission:view_users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
    });

    Route::middleware('permission:create_users')->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });

    Route::middleware('permission:edit_users')->group(function () {
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });

    Route::middleware('permission:delete_users')->group(function () {
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Permissions Management
    Route::middleware('permission:manage_permissions')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::put('/permissions', [PermissionController::class, 'update'])->name('permissions.update');
    });

    // Settings
    Route::middleware('permission:view_settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    });
    Route::middleware('permission:edit_company_info')->post('/settings/company', [SettingsController::class, 'updateCompany'])->name('settings.company');
    Route::middleware('permission:edit_banks')->post('/settings/banks', [SettingsController::class, 'updateBanks'])->name('settings.banks');
    Route::middleware('permission:edit_tenures')->post('/settings/tenures', [SettingsController::class, 'updateTenures'])->name('settings.tenures');
    Route::middleware('permission:edit_documents')->post('/settings/documents', [SettingsController::class, 'updateDocuments'])->name('settings.documents');
    Route::middleware('permission:edit_charges')->group(function () {
        Route::post('/settings/charges', [SettingsController::class, 'updateCharges'])->name('settings.charges');
        Route::post('/settings/bank-charges', [SettingsController::class, 'updateBankCharges'])->name('settings.bank-charges');
    });
    Route::middleware('permission:edit_services')->post('/settings/services', [SettingsController::class, 'updateServices'])->name('settings.services');
    Route::middleware('permission:edit_gst')->post('/settings/gst', [SettingsController::class, 'updateGst'])->name('settings.gst');
    Route::middleware('permission:view_settings')->post('/settings/reset', [SettingsController::class, 'reset'])->name('settings.reset');

    // Quotations
    Route::middleware('permission:create_quotation')->group(function () {
        Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    });
    Route::middleware('permission:generate_pdf')->group(function () {
        Route::post('/quotations/generate', [QuotationController::class, 'generate'])->name('quotations.generate');
    });
    Route::middleware('permission:download_pdf')->group(function () {
        Route::get('/quotations/{quotation}/download', [QuotationController::class, 'download'])->name('quotations.download');
        Route::get('/download-pdf', [QuotationController::class, 'downloadByFilename'])->name('quotations.download-file');
    });
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::middleware('permission:delete_quotations')->group(function () {
        Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
    });

    // Activity Log
    Route::middleware('permission:view_activity_log')->group(function () {
        Route::get('/activity-log', [DashboardController::class, 'activityLog'])->name('activity-log');
    });

    // API endpoints that require auth (session-based, used by PWA)
    Route::post('/api/sync', [SyncApiController::class, 'sync'])->name('api.sync');
    Route::post('/api/notes', [NotesApiController::class, 'save'])->name('api.notes.save');
});

require __DIR__.'/auth.php';

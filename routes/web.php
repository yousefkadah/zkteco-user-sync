<?php

declare(strict_types=1);

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Native\Desktop\Facades\AutoUpdater;

Route::get('/', fn () => Inertia::render('Home/Index'))->name('home');
Route::get('/import', [ImportController::class, 'index'])->name('import.index');
Route::post('/import', [ImportController::class, 'store'])->name('import.store');
Route::get('/import/{batch}', [ImportController::class, 'show'])->name('import.show');
Route::get('/import/{batch}/transfer', [ImportController::class, 'transfer'])->name('import.transfer');
Route::delete('/import/{batch}', [ImportController::class, 'destroy'])->name('import.destroy');
Route::post('/import/{batch}/sync', [SyncController::class, 'store'])->name('import.sync');
Route::post('/import/{batch}/users', [ImportController::class, 'storeUser'])->name('import.users.store');
Route::put('/import/{batch}/users/{user}', [ImportController::class, 'updateUser'])->name('import.users.update');
Route::delete('/import/{batch}/users/{user}', [ImportController::class, 'destroyUser'])->name('import.users.destroy');

Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
Route::get('/devices/scan', [DeviceController::class, 'scan'])->name('devices.scan');
Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
Route::post('/devices/{device}/test', [DeviceController::class, 'test'])->name('devices.test');
Route::get('/devices/{device}/users', [DeviceController::class, 'users'])->name('devices.users');
Route::post('/devices/{device}/users', [DeviceController::class, 'storeDeviceUser'])->name('devices.users.store');
Route::put('/devices/{device}/users/{uid}', [DeviceController::class, 'updateDeviceUser'])->name('devices.users.update');
Route::delete('/devices/{device}/users/{uid}', [DeviceController::class, 'destroyDeviceUser'])->name('devices.users.destroy');
Route::delete('/devices/{device}/users', [DeviceController::class, 'clearDeviceUsers'])->name('devices.users.clear');

Route::get('/template', [TemplateController::class, 'download'])->name('template.download');

// Manual "Check for updates" — the app already auto-checks GitHub on launch; this
// lets the About dialog trigger a check on demand. Wrapped so it no-ops outside the
// native runtime (e.g. plain `artisan serve`).
Route::post('/updates/check', function () {
    try {
        AutoUpdater::checkForUpdates();
    } catch (Throwable) {
        // AutoUpdater is only bound inside the packaged NativePHP app.
    }

    return back();
})->name('updates.check');

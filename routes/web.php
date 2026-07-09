<?php

declare(strict_types=1);

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ImportController::class, 'index'])->name('import.index');
Route::post('/import', [ImportController::class, 'store'])->name('import.store');
Route::get('/import/{batch}', [ImportController::class, 'show'])->name('import.show');
Route::delete('/import/{batch}', [ImportController::class, 'destroy'])->name('import.destroy');
Route::post('/import/{batch}/sync', [SyncController::class, 'store'])->name('import.sync');

Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
Route::post('/devices/{device}/test', [DeviceController::class, 'test'])->name('devices.test');

Route::get('/template', [TemplateController::class, 'download'])->name('template.download');

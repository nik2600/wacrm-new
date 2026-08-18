<?php

use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Support\Facades\Route;

// Devices — read the workspace's connected WhatsApp numbers.
Route::get('/devices',      [DeviceController::class, 'index'])->name('devices.index');
Route::get('/devices/{id}', [DeviceController::class, 'show'])->whereNumber('id')->name('devices.show');

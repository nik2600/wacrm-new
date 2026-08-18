<?php

use App\Http\Controllers\Api\V1\ScheduledController;
use Illuminate\Support\Facades\Route;

// Scheduled messages — schedule a WhatsApp send for later + read / cancel.
Route::post('/scheduled',      [ScheduledController::class, 'store'])->name('scheduled.store');
Route::get('/scheduled',       [ScheduledController::class, 'index'])->name('scheduled.index');
Route::get('/scheduled/{id}',  [ScheduledController::class, 'show'])->whereNumber('id')->name('scheduled.show');
Route::delete('/scheduled/{id}', [ScheduledController::class, 'destroy'])->whereNumber('id')->name('scheduled.destroy');

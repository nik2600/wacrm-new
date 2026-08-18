<?php

use App\Http\Controllers\Api\V1\BroadcastController;
use Illuminate\Support\Facades\Route;

// Broadcasts — send a template/message to a list of contacts or a group,
// then read status + per-recipient delivery.
Route::post('/broadcasts',                  [BroadcastController::class, 'store'])->name('broadcasts.store');
Route::get('/broadcasts',                   [BroadcastController::class, 'index'])->name('broadcasts.index');
Route::get('/broadcasts/{id}',              [BroadcastController::class, 'show'])->whereNumber('id')->name('broadcasts.show');
Route::get('/broadcasts/{id}/recipients',   [BroadcastController::class, 'recipients'])->whereNumber('id')->name('broadcasts.recipients');
Route::post('/broadcasts/{id}/stop',        [BroadcastController::class, 'stop'])->whereNumber('id')->name('broadcasts.stop');

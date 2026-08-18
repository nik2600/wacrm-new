<?php

use App\Http\Controllers\Api\V1\AutoReplyController;
use Illuminate\Support\Facades\Route;

// Auto-replies — keyword-triggered automatic responses for the current workspace.
Route::get('/auto-replies',         [AutoReplyController::class, 'index'])->name('auto-replies.index');
Route::post('/auto-replies',        [AutoReplyController::class, 'store'])->name('auto-replies.store');
Route::get('/auto-replies/{id}',    [AutoReplyController::class, 'show'])->whereNumber('id')->name('auto-replies.show');
Route::put('/auto-replies/{id}',    [AutoReplyController::class, 'update'])->whereNumber('id')->name('auto-replies.update');
Route::delete('/auto-replies/{id}', [AutoReplyController::class, 'destroy'])->whereNumber('id')->name('auto-replies.destroy');

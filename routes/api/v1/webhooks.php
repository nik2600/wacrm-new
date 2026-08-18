<?php

use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhooks — register, list and delete outbound endpoints for the workspace.
Route::get('/webhooks',         [WebhookController::class, 'index'])->name('webhooks.index');
Route::post('/webhooks',        [WebhookController::class, 'store'])->name('webhooks.store');
Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy'])->whereNumber('id')->name('webhooks.destroy');

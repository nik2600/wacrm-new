<?php

use App\Http\Controllers\Api\V1\AccountController;
use Illuminate\Support\Facades\Route;

// Account — read the current workspace, its plan, limits and usage.
Route::get('/me',    [AccountController::class, 'me'])->name('account.me');
Route::get('/usage', [AccountController::class, 'usage'])->name('account.usage');

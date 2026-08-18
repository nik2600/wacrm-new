<?php

use App\Http\Controllers\Api\V1\DealController;
use Illuminate\Support\Facades\Route;

// Deals — Sales Pipeline opportunities (plan-gated: access_sales_pipeline).
Route::get('/deals',         [DealController::class, 'index'])->name('deals.index');
Route::post('/deals',        [DealController::class, 'store'])->name('deals.store');
Route::get('/deals/{id}',    [DealController::class, 'show'])->whereNumber('id')->name('deals.show');
Route::put('/deals/{id}',    [DealController::class, 'update'])->whereNumber('id')->name('deals.update');
Route::delete('/deals/{id}', [DealController::class, 'destroy'])->whereNumber('id')->name('deals.destroy');

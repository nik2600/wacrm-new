<?php

use App\Http\Controllers\Api\V1\FlowController;
use Illuminate\Support\Facades\Route;

// Flows — read chatbot flows, enroll a contact, and list subscribers.
Route::get('/flows',                    [FlowController::class, 'index'])->name('flows.index');
Route::get('/flows/{id}',               [FlowController::class, 'show'])->whereNumber('id')->name('flows.show');
Route::post('/flows/{id}/enroll',       [FlowController::class, 'enroll'])->whereNumber('id')->name('flows.enroll');
Route::get('/flows/{id}/subscribers',   [FlowController::class, 'subscribers'])->whereNumber('id')->name('flows.subscribers');

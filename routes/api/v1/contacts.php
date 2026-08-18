<?php

use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContactGroupController;
use Illuminate\Support\Facades\Route;

// Contacts — manage the current workspace's contact book.
Route::get('/contacts',         [ContactController::class, 'index'])->name('contacts.index');
Route::post('/contacts',        [ContactController::class, 'store'])->name('contacts.store');
Route::get('/contacts/{id}',    [ContactController::class, 'show'])->whereNumber('id')->name('contacts.show');
Route::put('/contacts/{id}',    [ContactController::class, 'update'])->whereNumber('id')->name('contacts.update');
Route::delete('/contacts/{id}', [ContactController::class, 'destroy'])->whereNumber('id')->name('contacts.destroy');

// Contact groups — named lists/segments contacts can belong to.
Route::get('/contact-groups',         [ContactGroupController::class, 'index'])->name('contact-groups.index');
Route::post('/contact-groups',        [ContactGroupController::class, 'store'])->name('contact-groups.store');
Route::delete('/contact-groups/{id}', [ContactGroupController::class, 'destroy'])->whereNumber('id')->name('contact-groups.destroy');

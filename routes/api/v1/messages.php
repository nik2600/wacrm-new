<?php

use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MessageController;
use Illuminate\Support\Facades\Route;

// Messages — send a single WhatsApp message (text / media / location) + read
// status/history. Media can be attached directly (multipart `media`) or by URL.
Route::post('/messages',       [MessageController::class, 'store'])->name('messages.store');
Route::get('/messages',        [MessageController::class, 'index'])->name('messages.index');
Route::get('/messages/{id}',   [MessageController::class, 'show'])->whereNumber('id')->name('messages.show');

// Media — upload a file once and reuse its hosted URL across sends (optional;
// you can also attach the file directly on POST /messages).
Route::post('/media',          [MediaController::class, 'store'])->name('media.store');

// Conversations — read the workspace inbox (chat threads + their messages).
Route::get('/conversations',                 [ConversationController::class, 'index'])->name('conversations.index');
Route::get('/conversations/{id}/messages',   [ConversationController::class, 'messages'])->whereNumber('id')->name('conversations.messages');

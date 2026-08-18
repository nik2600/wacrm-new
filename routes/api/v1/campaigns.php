<?php

use App\Http\Controllers\Api\V1\CampaignController;
use Illuminate\Support\Facades\Route;

// Campaigns — create + manage multi-recipient WhatsApp campaigns (template /
// custom / flow, optional A/B testing) for the current workspace.
Route::get('/campaigns/statistics', [CampaignController::class, 'statistics'])->name('campaigns.statistics');
Route::get('/campaigns',            [CampaignController::class, 'index'])->name('campaigns.index');
Route::post('/campaigns',           [CampaignController::class, 'store'])->name('campaigns.store');
Route::get('/campaigns/{id}',       [CampaignController::class, 'show'])->whereNumber('id')->name('campaigns.show');
Route::post('/campaigns/{id}/stop', [CampaignController::class, 'stop'])->whereNumber('id')->name('campaigns.stop');
Route::delete('/campaigns/{id}',    [CampaignController::class, 'destroy'])->whereNumber('id')->name('campaigns.destroy');

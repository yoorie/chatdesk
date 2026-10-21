<?php

use App\Modules\Broadcasts\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

// Broadcasts/Campaigns routes - Protected by module entitlement
Route::middleware(['module.entitled:broadcasts'])->group(function () {
    Route::get('/broadcasts', [CampaignController::class, 'index'])->name('broadcasts.index');
    Route::post('/broadcasts', [CampaignController::class, 'store'])->name('broadcasts.store');
    Route::post('/broadcasts/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('broadcasts.duplicate');
    Route::post('/broadcasts/{campaign}/retry-failed', [CampaignController::class, 'retryFailed'])->name('broadcasts.retry-failed');
    Route::post('/broadcasts/{campaign}/send-test', [CampaignController::class, 'sendTest'])->name('broadcasts.send-test');
    Route::post('/broadcasts/{campaign}/start', [CampaignController::class, 'start'])->name('broadcasts.start');
    Route::post('/broadcasts/{campaign}/pause', [CampaignController::class, 'pause'])->name('broadcasts.pause');
    Route::post('/broadcasts/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('broadcasts.cancel');
    Route::delete('/broadcasts/{campaign}', [CampaignController::class, 'destroy'])->name('broadcasts.destroy');
});

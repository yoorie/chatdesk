<?php

use App\Modules\Contacts\Http\Controllers\ContactController;
use App\Modules\Contacts\Http\Controllers\SegmentController;
use App\Modules\Contacts\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Contacts routes - Protected by module entitlement
// Define static paths before /contacts/{contact} so they are not matched as {contact}
Route::middleware(['module.entitled:contacts'])->group(function () {
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::get('/contacts/imports/{batch}', [ContactController::class, 'importStatus'])->name('contacts.imports.status');
    Route::get('/contacts/imports/{batch}/errors', [ContactController::class, 'importErrors'])->name('contacts.imports.errors');
    Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::delete('/contacts/bulk', [ContactController::class, 'bulkDestroy'])->name('contacts.bulk-destroy');
    Route::post('/contacts/bulk/tags', [ContactController::class, 'bulkUpdateTags'])->name('contacts.bulk-tags');

    // Tags (CRM) - must be before /contacts/{contact}
    Route::get('/contacts/tags', [TagController::class, 'index'])->name('contacts.tags.index');
    Route::post('/contacts/tags', [TagController::class, 'store'])->name('contacts.tags.store');
    Route::put('/contacts/tags/{tag}', [TagController::class, 'update'])->name('contacts.tags.update');
    Route::delete('/contacts/tags/{tag}', [TagController::class, 'destroy'])->name('contacts.tags.destroy');

    // Segments (CRM) - must be before /contacts/{contact}
    Route::get('/contacts/segments', [SegmentController::class, 'index'])->name('contacts.segments.index');
    Route::post('/contacts/segments', [SegmentController::class, 'store'])->name('contacts.segments.store');
    Route::put('/contacts/segments/{segment}', [SegmentController::class, 'update'])->name('contacts.segments.update');
    Route::delete('/contacts/segments/{segment}', [SegmentController::class, 'destroy'])->name('contacts.segments.destroy');
    Route::post('/contacts/segments/{segment}/recalculate', [SegmentController::class, 'recalculate'])->name('contacts.segments.recalculate');

    // Dynamic contact routes last
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    Route::post('/contacts/{contact}/note', [ContactController::class, 'addNote'])->name('contacts.add-note');
    Route::post('/contacts/{contact}/merge', [ContactController::class, 'merge'])->name('contacts.merge');
});

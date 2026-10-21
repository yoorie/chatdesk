<?php

use App\Modules\WhatsApp\Http\Controllers\ConnectionController;
use App\Modules\WhatsApp\Http\Controllers\ConversationComposerController;
use App\Modules\WhatsApp\Http\Controllers\ConversationController;
use App\Modules\WhatsApp\Http\Controllers\FlowController;
use App\Modules\WhatsApp\Http\Controllers\TemplateController;
use App\Modules\WhatsApp\Http\Controllers\TemplateSendController;
use App\Modules\WhatsApp\Http\Controllers\TemplateSyncController;
use Illuminate\Support\Facades\Route;

// Note: These routes are loaded inside the app routes group, so they don't need their own prefix/middleware
// Routes are prefixed with 'app.whatsapp.' and are already under '/app'

// Note: Connection model binding is handled in the controllers via resolveConnection() method
// This avoids issues with route binding running before middleware

// WhatsApp Cloud API routes - Protected by module entitlement
Route::middleware(['module.entitled:whatsapp.cloud'])->group(function () {
    // Connections
    Route::get('/connections', [ConnectionController::class, 'index'])->name('whatsapp.connections.index');
    Route::post('/connections', [ConnectionController::class, 'storeManual'])->name('whatsapp.connections.store');
    Route::put('/connections/{connection}', [ConnectionController::class, 'update'])->name('whatsapp.connections.update');
    Route::delete('/connections/{connection}', [ConnectionController::class, 'destroy'])->name('whatsapp.connections.destroy');
    Route::post('/connections/{connection}/sync-meta', [ConnectionController::class, 'syncMeta'])->name('whatsapp.connections.sync-meta');
    Route::post('/connections/{connection}/subscribe-webhook', [ConnectionController::class, 'subscribeWebhook'])->name('whatsapp.connections.subscribe-webhook');
    Route::delete('/connections/{connection}/subscribe-webhook', [ConnectionController::class, 'unsubscribeWebhook'])->name('whatsapp.connections.unsubscribe-webhook');
    Route::post('/connections/{connection}/rotate-verify-token', [ConnectionController::class, 'rotateVerifyToken'])->name('whatsapp.connections.rotate-verify-token');

    // Meta WhatsApp Flows lifecycle
    Route::get('/flows', [FlowController::class, 'index'])->name('whatsapp.flows.index');
    Route::post('/flows', [FlowController::class, 'store'])->name('whatsapp.flows.store');
    Route::post('/flows/sync', [FlowController::class, 'sync'])->name('whatsapp.flows.sync');
    Route::put('/flows/{flow}', [FlowController::class, 'update'])->name('whatsapp.flows.update');
    Route::post('/flows/{flow}/publish', [FlowController::class, 'publish'])->name('whatsapp.flows.publish');
    Route::post('/flows/{flow}/deprecate', [FlowController::class, 'deprecate'])->name('whatsapp.flows.deprecate');

    // Conversations (static path before {conversation} so "by-contact" is not matched as conversation id)
    Route::get('/conversations', [ConversationController::class, 'index'])->name('whatsapp.conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('whatsapp.conversations.store');
    Route::get('/conversations/by-contact/{contact}', [ConversationController::class, 'showByContact'])->name('whatsapp.conversations.by-contact');
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'loadMoreMessages'])->name('whatsapp.conversations.messages');
    Route::get('/conversations/{conversation}/gallery', [ConversationController::class, 'gallery'])->name('whatsapp.conversations.gallery');
    Route::post('/conversations/{conversation}/send', [ConversationComposerController::class, 'sendMessage'])->name('whatsapp.conversations.send');
    Route::post('/conversations/{conversation}/messages/{message}/retry', [ConversationComposerController::class, 'retryMessage'])->name('whatsapp.conversations.retry-message');
    Route::post('/conversations/{conversation}/send-template', [ConversationComposerController::class, 'sendTemplateMessage'])->name('whatsapp.conversations.send-template');
    Route::post('/conversations/{conversation}/send-media', [ConversationComposerController::class, 'sendMediaMessage'])->name('whatsapp.conversations.send-media');
    Route::post('/conversations/{conversation}/send-reaction', [ConversationComposerController::class, 'sendReaction'])->name('whatsapp.conversations.send-reaction');
    Route::post('/conversations/{conversation}/send-location', [ConversationComposerController::class, 'sendLocationMessage'])->name('whatsapp.conversations.send-location');
    Route::post('/conversations/{conversation}/send-list', [ConversationComposerController::class, 'sendList'])->name('whatsapp.conversations.send-list');
    Route::post('/conversations/{conversation}/send-buttons', [ConversationComposerController::class, 'sendInteractiveButtons'])->name('whatsapp.conversations.send-buttons');
    Route::post('/conversations/{conversation}/send-flow', [ConversationComposerController::class, 'sendFlow'])->name('whatsapp.conversations.send-flow');
    Route::post('/conversations/{conversation}/send-cta-url', [ConversationComposerController::class, 'sendCtaUrl'])->name('whatsapp.conversations.send-cta-url');
    Route::post('/conversations/{conversation}/send-payment-link', [ConversationComposerController::class, 'sendPaymentLink'])->name('whatsapp.conversations.send-payment-link');
    Route::post('/conversations/{conversation}/send-contact-card', [ConversationComposerController::class, 'sendContactCard'])->name('whatsapp.conversations.send-contact-card');
    Route::post('/conversations/{conversation}/send-product', [ConversationComposerController::class, 'sendProduct'])->name('whatsapp.conversations.send-product');
    Route::post('/conversations/{conversation}/notes', [ConversationController::class, 'addInternalNote'])->name('whatsapp.conversations.notes.store');
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead'])->name('whatsapp.conversations.read');
    Route::post('/conversations/{conversation}/update', [ConversationController::class, 'updateMeta'])->name('whatsapp.conversations.update');
    Route::post('/conversations/{conversation}/automation/stop', [ConversationController::class, 'stopAutomation'])->name('whatsapp.conversations.automation.stop');
    Route::post('/conversations/{conversation}/bot/toggle', [ConversationController::class, 'toggleBot'])->name('whatsapp.conversations.bot.toggle');
    Route::post('/conversations/{conversation}/ai-suggest', [ConversationController::class, 'aiSuggest'])->name('whatsapp.conversations.ai-suggest');
    Route::post('/conversations/{conversation}/ai-feedback', [ConversationController::class, 'aiFeedback'])->name('whatsapp.conversations.ai-feedback');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('whatsapp.conversations.destroy');

    // Lists (requires whatsapp.cloud entitlement)
    Route::get('/lists', [\App\Modules\WhatsApp\Http\Controllers\ListController::class, 'index'])->name('whatsapp.lists.index');
    Route::post('/lists', [\App\Modules\WhatsApp\Http\Controllers\ListController::class, 'store'])->name('whatsapp.lists.store');
    Route::put('/lists/{list}', [\App\Modules\WhatsApp\Http\Controllers\ListController::class, 'update'])->name('whatsapp.lists.update');
    Route::delete('/lists/{list}', [\App\Modules\WhatsApp\Http\Controllers\ListController::class, 'destroy'])->name('whatsapp.lists.destroy');
    Route::post('/lists/{list}/toggle', [\App\Modules\WhatsApp\Http\Controllers\ListController::class, 'toggle'])->name('whatsapp.lists.toggle');

    // Inbox stream endpoints (fallback polling)
    Route::get('/inbox/stream', [\App\Modules\WhatsApp\Http\Controllers\InboxStreamController::class, 'stream'])->name('whatsapp.inbox.stream');
    Route::get('/inbox/{conversation}/stream', [\App\Modules\WhatsApp\Http\Controllers\InboxStreamController::class, 'conversationStream'])->name('whatsapp.inbox.conversation.stream');
});

// Templates (requires templates module entitlement)
Route::middleware(['module.entitled:templates'])->group(function () {
    Route::get('/templates', [TemplateController::class, 'index'])->name('whatsapp.templates.index');
    Route::get('/templates/library', [TemplateController::class, 'library'])->name('whatsapp.templates.library');
    Route::post('/templates', [TemplateController::class, 'store'])->name('whatsapp.templates.store');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('whatsapp.templates.update');
    Route::post('/templates/{template}/check-status', [TemplateController::class, 'checkStatus'])->name('whatsapp.templates.check-status');
    Route::post('/templates/sync', [TemplateSyncController::class, 'store'])->name('whatsapp.templates.sync');
    Route::post('/templates/{template}/archive', [TemplateController::class, 'archive'])->name('whatsapp.templates.archive');
    Route::post('/templates/{template}/restore', [TemplateController::class, 'restore'])->name('whatsapp.templates.restore');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('whatsapp.templates.destroy');
    Route::post('/templates/{template}/send', [TemplateSendController::class, 'store'])->name('whatsapp.templates.send.store');
    Route::post('/templates/upload-media', [TemplateController::class, 'uploadMedia'])->name('whatsapp.templates.upload-media');
});

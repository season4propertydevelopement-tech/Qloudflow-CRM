<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\BotTestController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\MetaCampaignController;
use App\Http\Controllers\MetaTemplateController;

// Public Shareable Video Watch & Download Routes
Route::get('/watch/{slug}', [VideoController::class, 'watch'])->name('video.watch');
Route::get('/watch/{slug}/download', [VideoController::class, 'download'])->name('video.download');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Root entry: If logged in, go to dashboard. If guest or cron ping, show login & run 10-min cron check
Route::get('/', function (\Illuminate\Http\Request $request) {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('dashboard');
    }
    return app(AuthController::class)->showLoginForm($request);
});

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Contacts Import / Export
    Route::get('contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::get('contacts/template', [ContactController::class, 'downloadTemplate'])->name('contacts.template');
    Route::post('contacts/import', [ContactController::class, 'import'])->name('contacts.import');

    Route::resource('contacts', ContactController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::post('contacts/{contact}/toggle-bot', [ContactController::class, 'toggleBot'])->name('contacts.toggle-bot');
    Route::post('contacts/{contact}/set-lead-status', [ContactController::class, 'setLeadStatus'])->name('contacts.set-lead-status');

    Route::resource('conversations', ConversationController::class)->only(['index', 'show', 'destroy']);
    Route::post('conversations/{conversation}/reply', [ConversationController::class, 'reply'])->name('conversations.reply');
    Route::post('conversations/{conversation}/toggle-bot', [ConversationController::class, 'toggleBot'])->name('conversations.toggle-bot');

    Route::prefix('whatsapp')->group(function () {
        Route::get('/connection', [WhatsAppController::class, 'index'])->name('whatsapp.connection');
        Route::get('/settings', [WhatsAppController::class, 'settings'])->name('whatsapp.settings');
        Route::post('/settings', [WhatsAppController::class, 'updateSettings'])->name('whatsapp.settings.update');
        Route::get('/api/status', [WhatsAppController::class, 'status']);
        Route::get('/api/sync', [WhatsAppController::class, 'sync']);
        Route::get('/api/qr', [WhatsAppController::class, 'qr']);
        Route::post('/api/connect', [WhatsAppController::class, 'connect']);
        Route::post('/api/logout', [WhatsAppController::class, 'logout']);

        // Connected WhatsApp Numbers for Team Lead Broadcasts
        Route::get('/numbers', [WhatsAppController::class, 'getNumbers'])->name('whatsapp.numbers');
        Route::post('/numbers', [WhatsAppController::class, 'storeNumber'])->name('whatsapp.numbers.store');
        Route::post('/numbers/{number}/toggle', [WhatsAppController::class, 'toggleNumber'])->name('whatsapp.numbers.toggle');
        Route::delete('/numbers/{number}', [WhatsAppController::class, 'destroyNumber'])->name('whatsapp.numbers.destroy');
        Route::post('/numbers/{number}/test-message', [WhatsAppController::class, 'testNumberMessage'])->name('whatsapp.numbers.test');
    });

    // Chatbot Simulator & Live Sandbox Testing Routes
    Route::post('/bot/test', [BotTestController::class, 'simulateMessage'])->name('bot.test');
    Route::post('/bot/test/reset', [BotTestController::class, 'resetSession'])->name('bot.test.reset');

    // Meta Leads & WhatsApp Automation Routes
    Route::prefix('meta-leads')->name('meta-leads.')->group(function () {
        Route::get('/automation', [MetaCampaignController::class, 'index'])->name('automation');
        Route::post('/campaigns', [MetaCampaignController::class, 'store'])->name('campaigns.store');
        Route::post('/campaigns/preview-sheet', [MetaCampaignController::class, 'previewSheet'])->name('campaigns.preview-sheet');
        Route::get('/campaigns/{campaign}', [MetaCampaignController::class, 'show'])->name('campaigns.show');
        Route::put('/campaigns/{campaign}', [MetaCampaignController::class, 'update'])->name('campaigns.update');
        Route::delete('/campaigns/{campaign}', [MetaCampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::post('/campaigns/{campaign}/sync', [MetaCampaignController::class, 'syncSheet'])->name('campaigns.sync');

        // Bulk & Single Dispatch
        Route::post('/campaigns/{campaign}/send-email', [MetaCampaignController::class, 'sendBulkEmail'])->name('campaigns.send-email');
        Route::post('/campaigns/{campaign}/send-whatsapp', [MetaCampaignController::class, 'sendBulkWhatsApp'])->name('campaigns.send-whatsapp');
        Route::post('/campaigns/{campaign}/leads/{lead}/send-single-email', [MetaCampaignController::class, 'sendSingleEmail'])->name('campaigns.leads.send-email');
        Route::post('/campaigns/{campaign}/leads/{lead}/send-single-whatsapp', [MetaCampaignController::class, 'sendSingleWhatsApp'])->name('campaigns.leads.send-whatsapp');

        // Auto-Welcome & Lead Creation
        Route::put('/campaigns/{campaign}/automation-settings', [MetaCampaignController::class, 'updateAutomationSettings'])->name('campaigns.automation-settings');
        Route::post('/campaigns/{campaign}/leads', [MetaCampaignController::class, 'storeSingleLead'])->name('campaigns.leads.store');
        Route::post('/campaigns/{campaign}/dispatch-pending-welcome', [MetaCampaignController::class, 'dispatchWelcomeToPending'])->name('campaigns.dispatch-pending-welcome');

        // Templates Management
        Route::get('/templates', [MetaTemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [MetaTemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/upload-image', [MetaTemplateController::class, 'uploadImage'])->name('templates.upload-image');
        Route::post('/templates/test-whatsapp', [MetaTemplateController::class, 'testWhatsApp'])->name('templates.test-whatsapp');
        Route::post('/templates/test-email', [MetaTemplateController::class, 'testEmail'])->name('templates.test-email');
        Route::put('/templates/{template}', [MetaTemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [MetaTemplateController::class, 'destroy'])->name('templates.destroy');
        Route::get('/api/templates', [MetaTemplateController::class, 'apiList'])->name('api.templates');
    });
});

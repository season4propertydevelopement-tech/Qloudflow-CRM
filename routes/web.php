<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\BotTestController;
use App\Http\Controllers\VideoController;

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

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

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
    });

    // Chatbot Simulator & Live Sandbox Testing Routes
    Route::post('/bot/test', [BotTestController::class, 'simulateMessage'])->name('bot.test');
    Route::post('/bot/test/reset', [BotTestController::class, 'resetSession'])->name('bot.test.reset');
});

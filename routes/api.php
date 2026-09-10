<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\MetaCampaignController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::match(['GET', 'POST'], '/webhooks/whatsapp/incoming', [WebhookController::class, 'handleIncoming']);
Route::match(['GET', 'POST'], '/meta-leads/campaigns/{campaign}/webhook', [MetaCampaignController::class, 'handleWebhookLead'])->name('api.meta-leads.campaigns.webhook');
Route::match(['GET', 'POST'], '/meta-leads/campaigns/{campaign}/sync-cron', [MetaCampaignController::class, 'syncCron'])->name('api.meta-leads.campaigns.sync-cron');
Route::match(['GET', 'POST'], '/meta-leads/cron/sync-all', [MetaCampaignController::class, 'syncAllCron'])->name('api.meta-leads.cron.sync-all');
Route::match(['GET', 'POST'], '/meta-leads/sync-all-cron', [MetaCampaignController::class, 'syncAllCron'])->name('api.meta-leads.sync-all-cron');




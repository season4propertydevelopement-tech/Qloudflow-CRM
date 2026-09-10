<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MetaCampaign;
use App\Models\MetaCampaignLead;
use App\Services\GoogleSheetService;
use App\Services\MetaAutomationService;

class SyncMetaLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meta-leads:sync {campaign_id? : Optional campaign ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically check Google Sheets for new leads and dispatch welcome messages on WhatsApp and Email.';

    /**
     * Execute the console command.
     */
    public function handle(GoogleSheetService $sheetService, MetaAutomationService $automationService)
    {
        $campaignId = $this->argument('campaign_id');

        if ($campaignId) {
            $campaigns = MetaCampaign::where('id', $campaignId)->get();
        } else {
            $campaigns = MetaCampaign::where('status', 'active')
                ->whereNotNull('sheet_url')
                ->get();
        }

        if ($campaigns->isEmpty()) {
            $this->info("No active campaigns with Google Sheets found to sync.");
            return Command::SUCCESS;
        }

        $this->info("Found {$campaigns->count()} campaign(s) to synchronize.");

        foreach ($campaigns as $campaign) {
            $this->line("Checking Campaign #{$campaign->id}: '{$campaign->name}'...");

            if (empty($campaign->sheet_url)) {
                $this->warn(" - Skipped: No Google Sheet URL linked.");
                continue;
            }

            $sheetResult = $sheetService->parseSheet($campaign->sheet_url);
            if (!$sheetResult['success']) {
                $this->error(" - Failed to parse Google Sheet: " . ($sheetResult['error'] ?? 'Unknown error'));
                continue;
            }

            $newCount = 0;
            $welcomeCount = 0;

            foreach ($sheetResult['rows'] as $rawRow) {
                $normalized = $sheetService->normalizeRow($rawRow);

                if (empty($normalized['phone']) && empty($normalized['email'])) {
                    continue;
                }

                $exists = MetaCampaignLead::where('campaign_id', $campaign->id)
                    ->where(function ($q) use ($normalized) {
                        if (!empty($normalized['meta_lead_id'])) {
                            $q->orWhere('meta_lead_id', $normalized['meta_lead_id']);
                        }
                        if (!empty($normalized['phone'])) {
                            $q->orWhere('phone', $normalized['phone']);
                        }
                        if (!empty($normalized['email'])) {
                            $q->orWhere('email', $normalized['email']);
                        }
                    })
                    ->exists();

                if (!$exists) {
                    $newLead = MetaCampaignLead::create([
                        'campaign_id' => $campaign->id,
                        'meta_lead_id' => $normalized['meta_lead_id'],
                        'name' => $normalized['name'],
                        'phone' => $normalized['phone'],
                        'raw_phone' => $normalized['raw_phone'],
                        'email' => $normalized['email'],
                        'city' => $normalized['city'],
                        'platform' => $normalized['platform'],
                        'lead_status' => $normalized['lead_status'],
                        'custom_fields' => $normalized['custom_fields'],
                        'raw_data' => $normalized['raw_data'],
                    ]);
                    $newCount++;

                    if ($campaign->auto_welcome_enabled) {
                        $automationService->dispatchAutoWelcome($newLead);
                        $welcomeCount++;
                    }

                    // Broadcast synced lead to all connected WhatsApp numbers
                    try {
                        app(\App\Services\WhatsAppAlertService::class)->broadcastNewLead([
                            'name' => $newLead->name ?: 'New Lead',
                            'phone' => $newLead->phone ?: $newLead->raw_phone,
                            'email' => $newLead->email,
                            'project' => $campaign->name ?: 'Growth City Naigaon',
                            'location' => $newLead->city,
                            'source' => "Meta Command Sync ({$campaign->name})",
                        ]);
                    } catch (\Throwable $e) {
                        // Continue sync
                    }
                }
            }

            $campaign->recalculateStats();

            $this->info(" - Imported {$newCount} new lead(s). Welcomes dispatched: {$welcomeCount}. Total leads: {$campaign->total_leads}.");
        }

        $this->info("Synchronization finished at " . now()->toDateTimeString());
        return Command::SUCCESS;
    }
}

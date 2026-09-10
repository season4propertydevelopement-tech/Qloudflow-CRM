<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MetaCampaign;
use App\Models\MetaCampaignLead;
use App\Models\MetaTemplate;
use App\Services\GoogleSheetService;
use App\Services\MetaAutomationService;

class MetaCampaignController extends Controller
{
    /**
     * Display the Campaign Management Dashboard.
     */
    public function index(Request $request)
    {
        $query = MetaCampaign::query()->withCount('leads');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && in_array($request->status, ['active', 'paused', 'completed', 'archived'])) {
            $query->where('status', $request->status);
        }

        $campaigns = $query->latest('id')->paginate(10)->withQueryString();

        $stats = [
            'total_campaigns' => MetaCampaign::count(),
            'total_leads' => MetaCampaignLead::count(),
            'emails_sent' => MetaCampaignLead::where('email_status', 'sent')->count(),
            'emails_failed' => MetaCampaignLead::where('email_status', 'failed')->count(),
            'whatsapp_sent' => MetaCampaignLead::where('whatsapp_status', 'sent')->count(),
            'whatsapp_failed' => MetaCampaignLead::where('whatsapp_status', 'failed')->count(),
        ];

        $totalDispatched = $stats['emails_sent'] + $stats['emails_failed'] + $stats['whatsapp_sent'] + $stats['whatsapp_failed'];
        $totalSuccessful = $stats['emails_sent'] + $stats['whatsapp_sent'];
        $stats['delivery_rate'] = $totalDispatched > 0 ? round(($totalSuccessful / $totalDispatched) * 100, 1) : 100;

        return view('meta-leads.automation', compact('campaigns', 'stats'));
    }

    /**
     * Preview Google Sheet headers and sample rows before campaign creation.
     */
    public function previewSheet(Request $request, GoogleSheetService $sheetService)
    {
        $request->validate([
            'sheet_url' => 'required|url',
        ]);

        $result = $sheetService->parseSheet($request->sheet_url);

        return response()->json($result);
    }

    /**
     * Store a new campaign and import leads from Google Sheets.
     */
    public function store(Request $request, GoogleSheetService $sheetService)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sheet_url' => 'required|url',
            'duplicate_mode' => 'nullable|in:skip,update',
        ]);

        $sheetResult = $sheetService->parseSheet($request->sheet_url);
        if (!$sheetResult['success']) {
            return back()->withInput()->with('error', $sheetResult['error'] ?? 'Failed to connect to Google Sheet.');
        }

        $campaign = MetaCampaign::create([
            'name' => $request->name,
            'description' => $request->description,
            'sheet_url' => $request->sheet_url,
            'status' => 'active',
            'headers' => $sheetResult['headers'],
        ]);

        $duplicateMode = $request->input('duplicate_mode', 'skip');
        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($sheetResult['rows'] as $rawRow) {
            $normalized = $sheetService->normalizeRow($rawRow);

            // Skip if both phone and email are empty
            if (empty($normalized['phone']) && empty($normalized['email'])) {
                $skippedCount++;
                continue;
            }

            // Check for duplicate in this campaign
            $existingLead = MetaCampaignLead::where('campaign_id', $campaign->id)
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
                ->first();

            if ($existingLead) {
                if ($duplicateMode === 'update') {
                    $existingLead->update([
                        'name' => $normalized['name'] ?: $existingLead->name,
                        'city' => $normalized['city'] ?: $existingLead->city,
                        'platform' => $normalized['platform'] ?: $existingLead->platform,
                        'lead_status' => $normalized['lead_status'] ?: $existingLead->lead_status,
                        'custom_fields' => $normalized['custom_fields'],
                        'raw_data' => $normalized['raw_data'],
                    ]);
                    $updatedCount++;
                } else {
                    $skippedCount++;
                }
            } else {
                MetaCampaignLead::create([
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
                $importedCount++;
            }
        }

        $campaign->recalculateStats();

        $msg = "Campaign '{$campaign->name}' created successfully! Imported {$importedCount} leads.";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} duplicates/invalid rows skipped).";
        }
        if ($updatedCount > 0) {
            $msg .= " ({$updatedCount} existing leads updated).";
        }

        return redirect()->route('meta-leads.campaigns.show', $campaign)->with('success', $msg);
    }

    /**
     * Display a Campaign Dashboard with its imported Meta Leads.
     */
    public function show(Request $request, MetaCampaign $campaign)
    {
        $campaign->ensureWebhookToken();
        $campaign->load(['whatsappWelcomeTemplate', 'emailWelcomeTemplate']);

        $query = $campaign->leads();

        // Search Filter
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Email Status Filter
        if ($request->filled('email_status') && in_array($request->email_status, ['not_sent', 'queued', 'sending', 'sent', 'failed'])) {
            $query->where('email_status', $request->email_status);
        }

        // WhatsApp Status Filter
        if ($request->filled('whatsapp_status') && in_array($request->whatsapp_status, ['not_sent', 'queued', 'sending', 'sent', 'failed'])) {
            $query->where('whatsapp_status', $request->whatsapp_status);
        }

        // Platform Filter
        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        if ($sort === 'oldest') {
            $query->oldest('id');
        } elseif ($sort === 'name') {
            $query->orderBy('name', 'asc');
        } else {
            $query->latest('id');
        }

        $leads = $query->paginate(20)->withQueryString();

        // Status breakdowns
        $emailStats = [
            'all' => $campaign->leads()->count(),
            'not_sent' => $campaign->leads()->where('email_status', 'not_sent')->count(),
            'sending' => $campaign->leads()->where('email_status', 'sending')->count(),
            'sent' => $campaign->leads()->where('email_status', 'sent')->count(),
            'failed' => $campaign->leads()->where('email_status', 'failed')->count(),
        ];

        $whatsappStats = [
            'all' => $campaign->leads()->count(),
            'not_sent' => $campaign->leads()->where('whatsapp_status', 'not_sent')->count(),
            'sending' => $campaign->leads()->where('whatsapp_status', 'sending')->count(),
            'sent' => $campaign->leads()->where('whatsapp_status', 'sent')->count(),
            'failed' => $campaign->leads()->where('whatsapp_status', 'failed')->count(),
        ];

        // Fetch available templates for bulk dispatch modals and auto-welcome configuration
        $emailTemplates = MetaTemplate::email()->latest('id')->get();
        $whatsappTemplates = MetaTemplate::whatsapp()->latest('id')->get();
        $customVariables = array_values(array_diff($campaign->getAvailableVariables(), ['full_name', 'name', 'phone', 'email', 'city']));

        return view('meta-leads.campaign-show', compact(
            'campaign',
            'leads',
            'emailStats',
            'whatsappStats',
            'emailTemplates',
            'whatsappTemplates',
            'customVariables'
        ));
    }

    /**
     * Update an existing campaign's basic details.
     */
    public function update(Request $request, MetaCampaign $campaign)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,paused,completed,archived',
        ]);

        $campaign->update($request->only(['name', 'description', 'status']));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'campaign' => $campaign]);
        }

        return back()->with('success', 'Campaign details updated.');
    }

    /**
     * Delete or archive a campaign.
     */
    public function destroy(MetaCampaign $campaign)
    {
        $name = $campaign->name;
        $campaign->delete();

        return redirect()->route('meta-leads.automation')->with('success', "Campaign '{$name}' was deleted successfully.");
    }

    /**
     * Re-sync and pull any newly added rows from the connected Google Sheet.
     */
    public function syncSheet(MetaCampaign $campaign, GoogleSheetService $sheetService, MetaAutomationService $automationService)
    {
        if (empty($campaign->sheet_url)) {
            return back()->with('error', 'No Google Sheet URL is linked to this campaign.');
        }

        $sheetResult = $sheetService->parseSheet($campaign->sheet_url);
        if (!$sheetResult['success']) {
            return back()->with('error', $sheetResult['error'] ?? 'Failed to connect to Google Sheet.');
        }

        $newCount = 0;
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

                // If auto welcome is enabled, automatically dispatch welcome messages
                if ($campaign->auto_welcome_enabled) {
                    $automationService->dispatchAutoWelcome($newLead);
                }
            }
        }

        $campaign->recalculateStats();

        $msg = "Google Sheet synchronized. {$newCount} new lead(s) added.";
        if ($newCount > 0 && $campaign->auto_welcome_enabled) {
            $msg .= " Automatic welcome message dispatched.";
        }

        return back()->with('success', $msg);
    }

    /**
     * Send bulk email to selected leads.
     */
    public function sendBulkEmail(Request $request, MetaCampaign $campaign, MetaAutomationService $automationService)
    {
        $request->validate([
            'subject' => 'required_without:template_id|string|nullable',
            'body' => 'required_without:template_id|string|nullable',
            'template_id' => 'nullable|exists:meta_templates,id',
            'lead_ids' => 'nullable|array',
            'lead_ids.*' => 'integer|exists:meta_campaign_leads,id',
            'skip_already_sent' => 'nullable|boolean',
        ]);

        $subject = $request->subject;
        $body = $request->body;

        if ($request->filled('template_id')) {
            $template = MetaTemplate::find($request->template_id);
            if ($template) {
                $subject = $template->subject ?: $subject;
                $body = $template->body ?: $body;
            }
        }

        if (empty($subject) || empty($body)) {
            return back()->with('error', 'Subject and Body are required to send emails.');
        }

        $leadIds = $request->input('lead_ids', []);
        $skipAlreadySent = $request->boolean('skip_already_sent', true);

        $result = $automationService->bulkSendEmails($campaign, $leadIds, $subject, $body, $skipAlreadySent);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Email broadcast finished. Sent: {$result['sent']}, Failed: {$result['failed']}, Skipped: {$result['skipped']}.",
                'data' => $result,
            ]);
        }

        return back()->with('success', "Email broadcast complete. Sent: {$result['sent']}, Failed: {$result['failed']}, Skipped: {$result['skipped']}.");
    }

    /**
     * Send bulk WhatsApp messages to selected leads.
     */
    public function sendBulkWhatsApp(Request $request, MetaCampaign $campaign, MetaAutomationService $automationService)
    {
        $request->validate([
            'message' => 'required_without:template_id|string|nullable',
            'template_id' => 'nullable|exists:meta_templates,id',
            'lead_ids' => 'nullable|array',
            'lead_ids.*' => 'integer|exists:meta_campaign_leads,id',
            'skip_already_sent' => 'nullable|boolean',
        ]);

        $message = $request->message;

        if ($request->filled('template_id')) {
            $template = MetaTemplate::find($request->template_id);
            if ($template) {
                $message = $template->body ?: $message;
            }
        }

        if (empty($message)) {
            return back()->with('error', 'Message content is required to send WhatsApp messages.');
        }

        $mediaUrl = $request->media_url;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = 'wa_' . time() . '_' . uniqid() . '.' . ($file->getClientOriginalExtension() ?: 'png');
            $targetDir = public_path('uploads/meta-templates');
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $file->move($targetDir, $fileName);
            $mediaUrl = asset('uploads/meta-templates/' . $fileName);
        } elseif (empty($mediaUrl) && $request->filled('template_id')) {
            $template = MetaTemplate::find($request->template_id);
            if ($template && !empty($template->media_url)) {
                $mediaUrl = $template->media_url;
            }
        }

        $leadIds = $request->input('lead_ids', []);
        $skipAlreadySent = $request->boolean('skip_already_sent', true);

        $result = $automationService->bulkSendWhatsApp($campaign, $leadIds, $message, $skipAlreadySent, $mediaUrl);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "WhatsApp broadcast finished. Sent: {$result['sent']}, Failed: {$result['failed']}, Skipped: {$result['skipped']}.",
                'data' => $result,
            ]);
        }

        return back()->with('success', "WhatsApp broadcast complete. Sent: {$result['sent']}, Failed: {$result['failed']}, Skipped: {$result['skipped']}.");
    }

    /**
     * Send single test/live email to a specific lead.
     */
    public function sendSingleEmail(Request $request, MetaCampaign $campaign, MetaCampaignLead $lead, MetaAutomationService $automationService)
    {
        $request->validate([
            'subject' => 'required|string',
            'body' => 'required|string',
        ]);

        $result = $automationService->sendEmail($lead, $request->subject, $request->body);
        $campaign->recalculateStats();

        return response()->json($result);
    }

    /**
     * Send single test/live WhatsApp message to a specific lead.
     */
    public function sendSingleWhatsApp(Request $request, MetaCampaign $campaign, MetaCampaignLead $lead, MetaAutomationService $automationService)
    {
        $request->validate([
            'message' => 'required|string',
            'media_url' => 'nullable|string',
        ]);

        $result = $automationService->sendWhatsApp($lead, $request->message, $request->media_url);
        $campaign->recalculateStats();

        return response()->json($result);
    }

    /**
     * Update campaign auto-welcome automation settings.
     */
    public function updateAutomationSettings(Request $request, MetaCampaign $campaign)
    {
        $request->validate([
            'auto_welcome_enabled' => 'nullable|boolean',
            'auto_welcome_whatsapp' => 'nullable|boolean',
            'auto_welcome_whatsapp_template_id' => 'nullable|exists:meta_templates,id',
            'auto_welcome_whatsapp_message' => 'nullable|string',
            'auto_welcome_whatsapp_media_url' => 'nullable|string',
            'auto_welcome_email' => 'nullable|boolean',
            'auto_welcome_email_template_id' => 'nullable|exists:meta_templates,id',
            'auto_welcome_email_subject' => 'nullable|string|max:255',
            'auto_welcome_email_body' => 'nullable|string',
        ]);

        $campaign->update([
            'auto_welcome_enabled' => $request->boolean('auto_welcome_enabled'),
            'auto_welcome_whatsapp' => $request->boolean('auto_welcome_whatsapp'),
            'auto_welcome_whatsapp_template_id' => $request->input('auto_welcome_whatsapp_template_id') ?: null,
            'auto_welcome_whatsapp_message' => $request->input('auto_welcome_whatsapp_message'),
            'auto_welcome_whatsapp_media_url' => $request->input('auto_welcome_whatsapp_media_url'),
            'auto_welcome_email' => $request->boolean('auto_welcome_email'),
            'auto_welcome_email_template_id' => $request->input('auto_welcome_email_template_id') ?: null,
            'auto_welcome_email_subject' => $request->input('auto_welcome_email_subject'),
            'auto_welcome_email_body' => $request->input('auto_welcome_email_body'),
        ]);

        $campaign->ensureWebhookToken();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Auto-welcome settings updated successfully.',
                'campaign' => $campaign->fresh(['whatsappWelcomeTemplate', 'emailWelcomeTemplate']),
            ]);
        }

        return back()->with('success', 'Auto-welcome settings saved successfully.');
    }

    /**
     * Store a single newly added lead and optionally trigger auto-welcome dispatch immediately.
     */
    public function storeSingleLead(Request $request, MetaCampaign $campaign, MetaAutomationService $automationService)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:100',
            'custom_fields' => 'nullable|array',
            'send_welcome' => 'nullable|boolean',
        ]);

        if (!$request->filled('phone') && !$request->filled('email')) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'Please provide at least a phone number or an email address.'], 422);
            }
            return back()->withInput()->with('error', 'Please provide at least a phone number or an email address.');
        }

        $phone = $request->phone;
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone ?? '');
        if (strlen($cleanPhone) === 10 && in_array($cleanPhone[0] ?? '', ['6', '7', '8', '9'])) {
            $phone = '+91' . $cleanPhone;
        }

        $lead = MetaCampaignLead::create([
            'campaign_id' => $campaign->id,
            'meta_lead_id' => 'MANUAL_' . strtoupper(uniqid()),
            'name' => $request->name,
            'phone' => $phone,
            'raw_phone' => $request->phone,
            'email' => $request->email,
            'city' => $request->city,
            'platform' => $request->input('platform', 'Manual Entry'),
            'lead_status' => 'CREATED',
            'custom_fields' => $request->input('custom_fields', []),
        ]);

        $campaign->recalculateStats();

        $welcomeDispatched = false;
        $dispatchResult = null;

        // If send_welcome checkbox is checked, or if campaign has auto_welcome_enabled active
        if ($request->boolean('send_welcome') || $campaign->auto_welcome_enabled) {
            $dispatchResult = $automationService->dispatchAutoWelcome($lead);
            $welcomeDispatched = true;
        }

        // Broadcast new lead to all connected WhatsApp numbers
        try {
            app(\App\Services\WhatsAppAlertService::class)->broadcastNewLead([
                'name' => $lead->name ?: 'New Meta Lead',
                'phone' => $lead->phone ?: $lead->raw_phone,
                'email' => $lead->email,
                'project' => $campaign->name ?: 'Growth City Naigaon',
                'location' => $lead->city,
                'source' => "Meta Campaign ({$campaign->name})",
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Lead broadcast failed in storeLead: " . $e->getMessage());
        }

        $campaign->recalculateStats();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Lead '{$lead->name}' added successfully!" . ($welcomeDispatched ? ' Welcome message dispatched.' : ''),
                'lead' => $lead->fresh(),
                'dispatch' => $dispatchResult,
            ]);
        }

        $successMsg = "Lead '{$lead->name}' added successfully!";
        if ($welcomeDispatched) {
            $successMsg .= " Welcome message dispatched.";
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Dispatch auto-welcome message to all pending uncontacted leads in this campaign.
     */
    public function dispatchWelcomeToPending(Request $request, MetaCampaign $campaign, MetaAutomationService $automationService)
    {
        if (!$campaign->auto_welcome_whatsapp && !$campaign->auto_welcome_email) {
            return back()->with('error', 'Please enable WhatsApp or Email welcome and select a template first in Auto-Welcome Settings.');
        }

        $leads = $campaign->leads()
            ->where(function ($q) {
                $q->where('whatsapp_status', '!=', 'sent')
                  ->orWhere('email_status', '!=', 'sent');
            })
            ->get();

        $processed = 0;
        foreach ($leads as $lead) {
            $automationService->dispatchAutoWelcome($lead);
            $processed++;
        }

        $campaign->recalculateStats();

        $msg = "Welcome outreach completed for {$processed} pending lead(s).";
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'processed' => $processed]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Incoming Webhook endpoint for Meta Lead Ads, Zapier, Make, or web forms.
     */
    public function handleWebhookLead(Request $request, MetaCampaign $campaign, MetaAutomationService $automationService)
    {
        $token = $request->query('token') ?: $request->header('X-Campaign-Token') ?: $request->input('token');
        if (empty($token) || $token !== $campaign->webhook_token) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: Invalid campaign webhook token.'], 401);
        }

        $data = $request->all();
        $name = $data['name'] ?? $data['full_name'] ?? $data['lead_name'] ?? 'Prospect';
        $phone = $data['phone'] ?? $data['mobile'] ?? $data['phone_number'] ?? null;
        $email = $data['email'] ?? null;
        $city = $data['city'] ?? null;
        $platform = $data['platform'] ?? 'Meta Lead Webhook';
        $metaLeadId = $data['meta_lead_id'] ?? $data['lead_id'] ?? ('HOOK_' . strtoupper(uniqid()));

        // Clean phone
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone ?? '');
        $formattedPhone = $phone;
        if (strlen($cleanPhone) === 10 && in_array($cleanPhone[0] ?? '', ['6', '7', '8', '9'])) {
            $formattedPhone = '+91' . $cleanPhone;
        }

        $customFields = $data['custom_fields'] ?? [];
        if (!is_array($customFields)) {
            $customFields = [];
        }

        $lead = MetaCampaignLead::create([
            'campaign_id' => $campaign->id,
            'meta_lead_id' => $metaLeadId,
            'name' => $name,
            'phone' => $formattedPhone,
            'raw_phone' => $phone,
            'email' => $email,
            'city' => $city,
            'platform' => $platform,
            'lead_status' => 'CREATED',
            'custom_fields' => $customFields,
            'raw_data' => $data,
        ]);

        $dispatchResult = null;
        if ($campaign->auto_welcome_enabled) {
            $dispatchResult = $automationService->dispatchAutoWelcome($lead);
        }

        // Broadcast incoming webhook lead to all connected WhatsApp numbers
        try {
            app(\App\Services\WhatsAppAlertService::class)->broadcastNewLead([
                'name' => $lead->name ?: 'New Meta Lead',
                'phone' => $lead->phone ?: $lead->raw_phone,
                'email' => $lead->email,
                'project' => $campaign->name ?: 'Growth City Naigaon',
                'location' => $lead->city,
                'source' => "Meta Webhook ({$campaign->name})",
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Lead broadcast failed in handleWebhookLead: " . $e->getMessage());
        }

        $campaign->recalculateStats();

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully.',
            'lead_id' => $lead->id,
            'auto_welcome_dispatched' => (bool)$campaign->auto_welcome_enabled,
            'dispatch_results' => $dispatchResult,
        ]);
    }

    /**
     * Automated Cron endpoint for cron-job.org or cPanel curl to check Google Sheet
     * and automatically import new leads + send welcome messages.
     * Accessible via GET or POST:
     *   /api/meta-leads/campaigns/{campaign}/sync-cron?token={webhook_token}
     */
    public function syncCron(Request $request, MetaCampaign $campaign, GoogleSheetService $sheetService, MetaAutomationService $automationService)
    {
        $token = $request->query('token') ?: $request->header('X-Campaign-Token') ?: $request->input('token');
        if (empty($token) || $token !== $campaign->webhook_token) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: Invalid campaign token.'], 401);
        }

        if (empty($campaign->sheet_url)) {
            return response()->json(['success' => false, 'error' => 'No Google Sheet URL linked to this campaign.'], 400);
        }

        $sheetResult = $sheetService->parseSheet($campaign->sheet_url);
        if (!$sheetResult['success']) {
            return response()->json(['success' => false, 'error' => $sheetResult['error'] ?? 'Failed to connect to Google Sheet.'], 500);
        }

        $newCount = 0;
        $welcomedCount = 0;

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
                    $welcomedCount++;
                }

                // Broadcast imported lead to all connected WhatsApp numbers
                try {
                    app(\App\Services\WhatsAppAlertService::class)->broadcastNewLead([
                        'name' => $newLead->name ?: 'New Lead',
                        'phone' => $newLead->phone ?: $newLead->raw_phone,
                        'email' => $newLead->email,
                        'project' => $campaign->name ?: 'Growth City Naigaon',
                        'location' => $newLead->city,
                        'source' => "CSV Lead Import ({$campaign->name})",
                    ]);
                } catch (\Throwable $e) {
                    // Continue batch processing
                }
            }
        }

        $campaign->recalculateStats();

        return response()->json([
            'success' => true,
            'campaign' => $campaign->name,
            'new_leads_imported' => $newCount,
            'welcomes_dispatched' => $welcomedCount,
            'total_leads_in_campaign' => $campaign->total_leads,
            'checked_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Master Automated Cron endpoint for cron-job.org to check ALL active campaigns,
     * sync their Google Sheets, and dispatch welcome messages.
     * 
     * Route: /api/meta-leads/cron/sync-all?token={CRON_TOKEN}
     */
    public function syncAllCron(Request $request, GoogleSheetService $sheetService, MetaAutomationService $automationService)
    {
        $masterToken = env('CRON_TOKEN', 'season4_master_sync_token');
        $suppliedToken = $request->query('token') ?: $request->header('X-Cron-Token') ?: $request->input('token');

        $isValid = false;
        if (!empty($suppliedToken)) {
            if ($suppliedToken === $masterToken || $suppliedToken === md5(config('app.key')) || MetaCampaign::where('webhook_token', $suppliedToken)->exists()) {
                $isValid = true;
            }
        } elseif ($request->query('token') === $masterToken) {
            $isValid = true;
        }

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized: Invalid cron token. Please pass ?token=' . $masterToken
            ], 401);
        }

        $summary = self::runAllCampaignsSync($sheetService, $automationService);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'checked_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Reusable core synchronization method to check ALL active campaigns with Google Sheets,
     * pull new rows, create leads, and trigger auto-welcome WhatsApp and Email sequences.
     * 
     * Can be invoked by:
     * 1. The dedicated API cron route (/api/meta-leads/cron/sync-all)
     * 2. The AuthController login page (when pinged every 10 minutes by cron-job.org or site traffic)
     * 3. Artisan commands / queue workers
     */
    public static function runAllCampaignsSync(?GoogleSheetService $sheetService = null, ?MetaAutomationService $automationService = null): array
    {
        $sheetService = $sheetService ?: app(GoogleSheetService::class);
        $automationService = $automationService ?: app(MetaAutomationService::class);

        $campaigns = MetaCampaign::where('status', 'active')
            ->whereNotNull('sheet_url')
            ->where('sheet_url', '!=', '')
            ->get();

        $summary = [
            'total_campaigns_checked' => $campaigns->count(),
            'total_new_leads' => 0,
            'total_welcomes_dispatched' => 0,
            'campaign_results' => [],
            'executed_at' => now()->toDateTimeString(),
        ];

        if ($campaigns->isEmpty()) {
            return $summary;
        }

        foreach ($campaigns as $campaign) {
            try {
                $sheetResult = $sheetService->parseSheet($campaign->sheet_url);
                if (!$sheetResult['success']) {
                    $summary['campaign_results'][] = [
                        'campaign_id' => $campaign->id,
                        'campaign_name' => $campaign->name,
                        'status' => 'error',
                        'error' => $sheetResult['error'] ?? 'Failed to parse sheet',
                    ];
                    continue;
                }

                $newCount = 0;
                $welcomedCount = 0;

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
                            $welcomedCount++;
                        }

                        // Broadcast synced lead to all connected WhatsApp numbers
                        try {
                            app(\App\Services\WhatsAppAlertService::class)->broadcastNewLead([
                                'name' => $newLead->name ?: 'New Lead',
                                'phone' => $newLead->phone ?: $newLead->raw_phone,
                                'email' => $newLead->email,
                                'project' => $campaign->name ?: 'Growth City Naigaon',
                                'location' => $newLead->city,
                                'source' => "Meta Graph Sync ({$campaign->name})",
                            ]);
                        } catch (\Throwable $e) {
                            // Continue batch processing
                        }
                    }
                }

                $campaign->recalculateStats();

                $summary['total_new_leads'] += $newCount;
                $summary['total_welcomes_dispatched'] += $welcomedCount;
                $summary['campaign_results'][] = [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'status' => 'success',
                    'new_leads' => $newCount,
                    'welcomes_dispatched' => $welcomedCount,
                    'total_leads' => $campaign->total_leads,
                ];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Error syncing campaign {$campaign->id}: " . $e->getMessage());
                $summary['campaign_results'][] = [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'status' => 'exception',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }
}

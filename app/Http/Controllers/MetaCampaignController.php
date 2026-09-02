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

        // Fetch available templates for bulk dispatch modals
        $emailTemplates = MetaTemplate::email()->latest('id')->get();
        $whatsappTemplates = MetaTemplate::whatsapp()->latest('id')->get();

        return view('meta-leads.campaign-show', compact(
            'campaign',
            'leads',
            'emailStats',
            'whatsappStats',
            'emailTemplates',
            'whatsappTemplates'
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
    public function syncSheet(MetaCampaign $campaign, GoogleSheetService $sheetService)
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
                $newCount++;
            }
        }

        $campaign->recalculateStats();

        return back()->with('success', "Google Sheet synchronized. {$newCount} new lead(s) added.");
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
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MetaTemplate;
use App\Models\MetaCampaignLead;
use App\Services\MetaAutomationService;

class MetaTemplateController extends Controller
{
    /**
     * Display a listing of templates.
     */
    public function index()
    {
        $emailTemplates = MetaTemplate::email()->latest('id')->get();
        $whatsappTemplates = MetaTemplate::whatsapp()->latest('id')->get();
        $sampleLead = MetaCampaignLead::latest('id')->first();

        return view('meta-leads.templates', compact('emailTemplates', 'whatsappTemplates', 'sampleLead'));
    }

    /**
     * Store a newly created template in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,whatsapp',
            'name' => 'required|string|max:255',
            'subject' => 'nullable|required_if:type,email|string|max:255',
            'body' => 'required|string',
            'media_url' => 'nullable|string|max:1000',
            'image' => 'nullable|file|image|max:10240',
        ]);

        $mediaUrl = $request->media_url;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = 'tmpl_' . time() . '_' . uniqid() . '.' . ($file->getClientOriginalExtension() ?: 'png');
            $targetDir = public_path('uploads/meta-templates');
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $file->move($targetDir, $fileName);
            $mediaUrl = asset('uploads/meta-templates/' . $fileName);
        }

        $fullText = ($request->subject ?? '') . ' ' . $request->body;
        $variables = MetaTemplate::extractVariables($fullText);

        $template = MetaTemplate::create([
            'type' => $request->type,
            'name' => $request->name,
            'subject' => $request->subject,
            'body' => $request->body,
            'media_url' => $mediaUrl,
            'variables' => $variables,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'template' => $template]);
        }

        return back()->with('success', ucfirst($request->type) . ' template created successfully.');
    }

    /**
     * Update the specified template in storage.
     */
    public function update(Request $request, MetaTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'media_url' => 'nullable|string|max:1000',
            'image' => 'nullable|file|image|max:10240',
        ]);

        $mediaUrl = $request->media_url ?: $template->media_url;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = 'tmpl_' . time() . '_' . uniqid() . '.' . ($file->getClientOriginalExtension() ?: 'png');
            $targetDir = public_path('uploads/meta-templates');
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $file->move($targetDir, $fileName);
            $mediaUrl = asset('uploads/meta-templates/' . $fileName);
        }

        $fullText = ($request->subject ?? '') . ' ' . $request->body;
        $variables = MetaTemplate::extractVariables($fullText);

        $template->update([
            'name' => $request->name,
            'subject' => $request->subject,
            'body' => $request->body,
            'media_url' => $mediaUrl,
            'variables' => $variables,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'template' => $template]);
        }

        return back()->with('success', 'Template updated successfully.');
    }

    /**
     * Remove the specified template from storage.
     */
    public function destroy(MetaTemplate $template)
    {
        $type = ucfirst($template->type);
        $template->delete();

        return back()->with('success', "{$type} template deleted successfully.");
    }

    /**
     * Upload an image asset for use in Email or WhatsApp templates.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
        ]);

        $file = $request->file('image');
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $fileName = 'tmpl_' . time() . '_' . uniqid() . '.' . $extension;

        $targetDir = public_path('uploads/meta-templates');
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file->move($targetDir, $fileName);
        $url = asset('uploads/meta-templates/' . $fileName);

        return response()->json([
            'success' => true,
            'url' => $url,
            'file_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Send a single test WhatsApp message to any phone number.
     */
    public function testWhatsApp(Request $request, MetaAutomationService $automationService)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
            'media_url' => 'nullable|string',
            'sample_lead_id' => 'nullable|integer|exists:meta_campaign_leads,id',
        ]);

        $message = $request->message;
        if ($request->filled('sample_lead_id')) {
            $sampleLead = MetaCampaignLead::find($request->sample_lead_id);
            if ($sampleLead) {
                $message = $automationService->renderTemplate($message, $sampleLead);
            }
        }

        $result = $automationService->sendDirectWhatsApp($request->phone, $message, $request->media_url);

        return response()->json($result);
    }

    /**
     * Send a single test email to any inbox.
     */
    public function testEmail(Request $request, MetaAutomationService $automationService)
    {
        $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string',
            'body' => 'required|string',
            'sample_lead_id' => 'nullable|integer|exists:meta_campaign_leads,id',
        ]);

        $subject = $request->subject;
        $body = $request->body;
        if ($request->filled('sample_lead_id')) {
            $sampleLead = MetaCampaignLead::find($request->sample_lead_id);
            if ($sampleLead) {
                $subject = $automationService->renderTemplate($subject, $sampleLead);
                $body = $automationService->renderTemplate($body, $sampleLead);
            }
        }

        $result = $automationService->sendDirectEmail($request->email, $subject, $body);

        return response()->json($result);
    }

    /**
     * Return JSON list of all templates for async UI components.
     */
    public function apiList()
    {
        return response()->json([
            'email' => MetaTemplate::email()->latest('id')->get(),
            'whatsapp' => MetaTemplate::whatsapp()->latest('id')->get(),
        ]);
    }
}


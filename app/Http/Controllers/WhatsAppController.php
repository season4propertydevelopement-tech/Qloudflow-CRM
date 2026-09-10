<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppApiService;
use App\Services\BotSettingsService;
use App\Services\WhatsAppAlertService;
use App\Models\WhatsAppConnectedNumber;

class WhatsAppController extends Controller
{
    protected $apiService;

    public function __construct(WhatsAppApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function index()
    {
        $connectedNumbers = WhatsAppConnectedNumber::latest('id')->get();
        return view('whatsapp.connection', compact('connectedNumbers'));
    }

    public function status()
    {
        try {
            return response()->json($this->apiService->getStatus());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 'error', 'message' => 'API Unreachable']);
        }
    }

    public function qr()
    {
        try {
            return response()->json($this->apiService->getQrCode());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'QR Error']);
        }
    }

    public function connect()
    {
        try {
            return response()->json($this->apiService->connect());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Connect Error']);
        }
    }

    public function logout()
    {
        try {
            return response()->json($this->apiService->logout());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Logout Error']);
        }
    }

    public function sync(WebhookController $webhookController)
    {
        try {
            $sinceId = \Illuminate\Support\Facades\Cache::get('whatsapp_last_synced_message_id');
            $unhandled = $this->apiService->getUnhandledMessages($sinceId);
            $processedCount = 0;
            $latestId = null;

            if (!empty($unhandled['messages']) && is_array($unhandled['messages'])) {
                foreach ($unhandled['messages'] as $payload) {
                    $msgId = $payload['messageId'] ?? null;
                    if ($msgId) {
                        $latestId = $msgId;
                    }
                    $webhookController->processIncomingMessage($payload, $this->apiService);
                    $processedCount++;
                }

                if ($latestId) {
                    \Illuminate\Support\Facades\Cache::forever('whatsapp_last_synced_message_id', $latestId);
                }
            }

            return response()->json([
                'success' => true,
                'synced' => $processedCount,
                'status' => $this->apiService->getStatus()
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Show Bot Settings & Automation Schedule.
     */
    public function settings(BotSettingsService $settingsService)
    {
        $settings = $settingsService->getSettings();
        $isBotActiveNow = $settingsService->isBotActiveNow();
        $isWithinOperatingHours = $settingsService->isWithinOperatingHours($settings);

        return view('whatsapp.settings', compact('settings', 'isBotActiveNow', 'isWithinOperatingHours'));
    }

    /**
     * Update Bot Settings & Schedule.
     */
    public function updateSettings(Request $request, BotSettingsService $settingsService)
    {
        $validated = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'schedule_mode' => 'required|in:always,custom',
            'timezone' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'active_days' => 'nullable|array',
            'out_of_hours_message' => 'required|string|max:2000',
            'send_welcome_media' => 'nullable|boolean',
            'auto_lead_scoring' => 'nullable|boolean',
            'human_handoff_enabled' => 'nullable|boolean',
            'human_handoff_keywords' => 'nullable|string|max:1000',
            'typing_delay_seconds' => 'nullable|integer|min:0|max:10',
        ]);

        // Convert checkbox booleans
        $validated['is_enabled'] = $request->boolean('is_enabled');
        $validated['send_welcome_media'] = $request->boolean('send_welcome_media');
        $validated['auto_lead_scoring'] = $request->boolean('auto_lead_scoring');
        $validated['human_handoff_enabled'] = $request->boolean('human_handoff_enabled');
        $validated['active_days'] = $request->input('active_days', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']);

        $settingsService->saveSettings($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Bot settings & schedule updated successfully!',
                'settings' => $settingsService->getSettings(),
                'isBotActiveNow' => $settingsService->isBotActiveNow(),
            ]);
        }

        return redirect()->route('whatsapp.settings')->with('success', 'Bot automation settings & operating schedule saved successfully!');
    }

    /**
     * Get JSON list of connected numbers.
     */
    public function getNumbers()
    {
        return response()->json([
            'success' => true,
            'numbers' => WhatsAppConnectedNumber::latest('id')->get()
        ]);
    }

    /**
     * Add a new WhatsApp number to receive lead notifications,
     * and notify both the primary connected session and the newly added number.
     */
    public function storeNumber(Request $request, WhatsAppAlertService $alertService)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'role' => 'nullable|string|max:100',
            'notify_new_leads' => 'nullable|boolean',
        ]);

        $cleanPhone = $alertService->standardizePhone($request->phone);

        if (empty($cleanPhone) || strlen($cleanPhone) < 7) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter a valid mobile number with country code.'
                ], 422);
            }
            return back()->with('error', 'Please enter a valid mobile number with country code.');
        }

        // Check if number already registered
        $existing = WhatsAppConnectedNumber::where('phone', $cleanPhone)->first();
        if ($existing) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "WhatsApp number +{$cleanPhone} is already registered as '{$existing->name}'."
                ], 422);
            }
            return back()->with('error', "WhatsApp number +{$cleanPhone} is already registered as '{$existing->name}'.");
        }

        $number = WhatsAppConnectedNumber::create([
            'name' => trim($request->name),
            'phone' => $cleanPhone,
            'role' => trim($request->input('role', 'Sales Consultant')) ?: 'Sales Consultant',
            'is_active' => true,
            'notify_new_leads' => $request->boolean('notify_new_leads', true),
            'last_notified_at' => null,
        ]);

        // Dispatch alerts to both connected WhatsApp device and newly added number
        $notifyResults = $alertService->notifyNumberAdded($number);

        $successMsg = "WhatsApp number +{$number->phone} ({$number->name}) added successfully!";
        if ($notifyResults['primary_notified'] && $notifyResults['recipient_notified']) {
            $successMsg .= " Notification sent to connected WhatsApp device and +{$number->phone}.";
        } elseif ($notifyResults['recipient_notified']) {
            $successMsg .= " Welcome alert sent to +{$number->phone}.";
        } elseif ($notifyResults['primary_notified']) {
            $successMsg .= " Notification sent to connected WhatsApp device.";
        }

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'number' => $number,
                'notify_results' => $notifyResults,
            ]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Toggle active state or lead notification state of a connected number.
     */
    public function toggleNumber(Request $request, WhatsAppConnectedNumber $number)
    {
        if ($request->has('notify_new_leads')) {
            $number->notify_new_leads = $request->boolean('notify_new_leads');
        } else {
            $number->is_active = !$number->is_active;
        }
        $number->save();

        $statusStr = $number->is_active ? 'activated' : 'paused';

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Alerts for {$number->name} ({$number->formatted_phone}) {$statusStr}.",
                'number' => $number
            ]);
        }

        return back()->with('success', "Alerts for {$number->name} {$statusStr}.");
    }

    /**
     * Remove a connected WhatsApp number from lead broadcast.
     */
    public function destroyNumber(Request $request, WhatsAppConnectedNumber $number)
    {
        $name = $number->name;
        $phone = $number->formatted_phone;
        $number->delete();

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Removed {$name} ({$phone}) from WhatsApp lead broadcast list."
            ]);
        }

        return back()->with('success', "Removed {$name} from WhatsApp lead broadcast list.");
    }

    /**
     * Send test alert message to a connected WhatsApp number.
     */
    public function testNumberMessage(Request $request, WhatsAppConnectedNumber $number, WhatsAppAlertService $alertService)
    {
        $result = $alertService->sendTestAlert($number);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', "Could not send test message: " . ($result['error'] ?? 'Unknown error'));
    }
}

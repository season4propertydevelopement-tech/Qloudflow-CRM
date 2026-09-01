<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppApiService;
use App\Services\BotSettingsService;

class WhatsAppController extends Controller
{
    protected $apiService;

    public function __construct(WhatsAppApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function index()
    {
        return view('whatsapp.connection');
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
}

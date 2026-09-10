<?php

namespace App\Services;

use App\Models\WhatsAppConnectedNumber;
use Illuminate\Support\Facades\Log;

class WhatsAppAlertService
{
    protected WhatsAppApiService $apiService;

    public function __construct(WhatsAppApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Clean and standardize any raw phone string into pure digits.
     */
    public function standardizePhone(string $rawPhone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $rawPhone);

        // If 10 digits starting with 6, 7, 8, 9, prepend India country code 91
        if (strlen($digits) === 10 && in_array($digits[0], ['6', '7', '8', '9'])) {
            $digits = '91' . $digits;
        }

        return $digits;
    }

    /**
     * Notify connected WhatsApp session and the newly added number itself.
     */
    public function notifyNumberAdded(WhatsAppConnectedNumber $newNumber, ?string $primaryDevicePhone = null): array
    {
        $results = [
            'primary_notified' => false,
            'recipient_notified' => false,
        ];

        // 1. Identify primary connected device phone
        $primaryPhone = $primaryDevicePhone;
        if (!$primaryPhone) {
            try {
                $status = $this->apiService->getStatus();
                if (!empty($status['phone'])) {
                    $primaryPhone = $this->standardizePhone($status['phone']);
                }
            } catch (\Throwable $e) {
                Log::warning("WhatsAppAlertService: Could not fetch primary status: " . $e->getMessage());
            }
        }

        $nowFormatted = now()->format('d M Y, h:i A');

        // Message to Primary Connected WhatsApp Device
        if (!empty($primaryPhone)) {
            $primaryMsg = "🔔 *New WhatsApp Alert Recipient Added!*\n\n"
                . "A new WhatsApp number has been successfully registered to receive real-time lead broadcasts in *Qloudflow CRM*:\n\n"
                . "👤 *Name:* {$newNumber->name}\n"
                . "📱 *Number:* +{$newNumber->phone}\n"
                . "🏷️ *Role:* " . ($newNumber->role ?: 'Sales Consultant') . "\n"
                . "📅 *Registered At:* {$nowFormatted}\n\n"
                . "✅ This number will now receive instant alerts for all incoming customer leads.";

            try {
                $res = $this->apiService->sendMessage($primaryPhone, $primaryMsg);
                $results['primary_notified'] = !empty($res['success']);
            } catch (\Throwable $e) {
                Log::error("Failed to notify primary WhatsApp about added number: " . $e->getMessage());
            }
        }

        // 2. Message to the newly added WhatsApp Number itself
        $cleanRecipientPhone = $this->standardizePhone($newNumber->phone);
        $welcomeMsg = "🎉 *Welcome to Qloudflow CRM Lead Alerts!*\n\n"
            . "Hello *{$newNumber->name}*,\n\n"
            . "Your WhatsApp number has been successfully registered to receive real-time lead notifications for *The House of Abhinandan Lodha* / *Growth City Naigaon*.\n\n"
            . "🚀 You will now receive instant alerts right here on WhatsApp whenever a new customer lead or property inquiry arrives.\n\n"
            . "_Qloudflow Real-Time Lead Distribution Engine_";

        try {
            $res = $this->apiService->sendMessage($cleanRecipientPhone, $welcomeMsg);
            $results['recipient_notified'] = !empty($res['success']);
            $newNumber->update(['last_notified_at' => now()]);
        } catch (\Throwable $e) {
            Log::error("Failed to send welcome alert to newly added WhatsApp number: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Broadcast an incoming lead to ALL active connected WhatsApp numbers and the primary device.
     */
    public function broadcastNewLead(array $leadData): array
    {
        $name = trim($leadData['name'] ?? 'New Prospect');
        $phone = trim($leadData['phone'] ?? 'N/A');
        $email = trim($leadData['email'] ?? '');
        $project = trim($leadData['project'] ?? 'The House of Abhinandan Lodha — Growth City Naigaon');
        $location = trim($leadData['location'] ?? '');
        $source = trim($leadData['source'] ?? 'Inbound Customer Lead');
        $notes = trim($leadData['notes'] ?? '');
        $time = $leadData['time'] ?? now()->format('d M Y, h:i A');

        // Compose high-converting, professional WhatsApp lead alert
        $msg = "🚨 *NEW LEAD RECEIVED!* 🚨\n\n"
            . "👤 *Name:* *{$name}*\n"
            . "📱 *Phone:* *{$phone}*\n";

        if (!empty($email) && $email !== 'N/A') {
            $msg .= "📧 *Email:* {$email}\n";
        }

        $msg .= "🏢 *Project:* {$project}\n";

        if (!empty($location) && $location !== 'N/A') {
            $msg .= "📍 *Location / City:* {$location}\n";
        }

        $msg .= "🏷️ *Source:* {$source}\n";

        if (!empty($notes)) {
            $msg .= "📝 *Notes / Requirement:* {$notes}\n";
        }

        $msg .= "⏰ *Received:* {$time}\n\n"
            . "👉 _Please review and connect with this customer promptly!_";

        // Collect all distinct destination phones
        $recipients = [];

        // 1. All active registered alert numbers
        $connectedNumbers = WhatsAppConnectedNumber::notifyLeads()->get();
        foreach ($connectedNumbers as $cn) {
            $clean = $this->standardizePhone($cn->phone);
            if (!empty($clean)) {
                $recipients[$clean] = $cn;
            }
        }

        // 2. Primary paired session device phone
        try {
            $status = $this->apiService->getStatus();
            if (!empty($status['phone'])) {
                $primaryClean = $this->standardizePhone($status['phone']);
                if (!empty($primaryClean) && !isset($recipients[$primaryClean])) {
                    $recipients[$primaryClean] = 'primary';
                }
            }
        } catch (\Throwable $e) {
            Log::warning("WhatsAppAlertService: Could not include primary device in lead broadcast: " . $e->getMessage());
        }

        $dispatchSummary = [
            'total_targets' => count($recipients),
            'sent_count' => 0,
            'failed_count' => 0,
            'details' => [],
        ];

        foreach ($recipients as $targetPhone => $recipientEntity) {
            // Safety: Don't send lead alert to the customer themselves if their phone matches
            $cleanLeadPhone = $this->standardizePhone($phone);
            if ($targetPhone === $cleanLeadPhone) {
                continue;
            }

            try {
                $res = $this->apiService->sendMessage($targetPhone, $msg);
                $isSent = !empty($res['success']);
                if ($isSent) {
                    $dispatchSummary['sent_count']++;
                    if ($recipientEntity instanceof WhatsAppConnectedNumber) {
                        $recipientEntity->update(['last_notified_at' => now()]);
                    }
                } else {
                    $dispatchSummary['failed_count']++;
                }

                $dispatchSummary['details'][] = [
                    'phone' => $targetPhone,
                    'status' => $isSent ? 'sent' : 'failed',
                    'error' => $res['error'] ?? null,
                ];
            } catch (\Throwable $e) {
                $dispatchSummary['failed_count']++;
                Log::error("Failed to broadcast lead to {$targetPhone}: " . $e->getMessage());
            }
        }

        return $dispatchSummary;
    }

    /**
     * Send a test WhatsApp alert message to verify number readiness.
     */
    public function sendTestAlert(WhatsAppConnectedNumber $number): array
    {
        $clean = $this->standardizePhone($number->phone);
        $testMsg = "✅ *Qloudflow CRM Alert Test*\n\n"
            . "Hello *{$number->name}*,\n\n"
            . "This is a test notification verifying that your WhatsApp number (*+{$clean}*) is active and configured to receive real-time lead alerts from *The House of Abhinandan Lodha*.\n\n"
            . "⏰ Timestamp: " . now()->format('d M Y, h:i A');

        try {
            $res = $this->apiService->sendMessage($clean, $testMsg);
            if (!empty($res['success'])) {
                $number->update(['last_notified_at' => now()]);
                return ['success' => true, 'message' => "Test alert delivered to +{$clean}."];
            }
            return ['success' => false, 'error' => $res['error'] ?? 'Delivery failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

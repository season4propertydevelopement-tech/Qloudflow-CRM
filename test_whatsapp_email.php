<?php

/**
 * Test WhatsApp & Email Dispatcher
 * Brand: The House of Abhinandan Lodha
 * 
 * Usage from terminal:
 *   php test_whatsapp_email.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\MetaAutomationService;
use App\Services\WhatsAppApiService;

// Target recipient details
$recipientEmail = 'amarvcode@gmail.com';
$recipientPhone = '9699867990';
$recipientName = 'Amar';

$isCli = (php_sapi_name() === 'cli');
$nl = $isCli ? PHP_EOL : "<br>";

echo $isCli ? "==========================================" . PHP_EOL : "<div style='font-family: monospace; max-width: 700px; margin: 20px auto; padding: 20px; background: #0f172a; color: #f8fafc; border-radius: 12px;'>";
echo "🏛️ The House of Abhinandan Lodha - WhatsApp & Email Dispatcher" . $nl;
echo "Recipient Email: {$recipientEmail}" . $nl;
echo "Recipient Phone: {$recipientPhone}" . $nl;
echo ($isCli ? "==========================================" : "<hr style='border-color: #334155;'>") . $nl;

$automationService = app(MetaAutomationService::class);
$whatsAppService = app(WhatsAppApiService::class);

// -------------------------------------------------------------
// 1. WhatsApp Dispatch Test
// -------------------------------------------------------------
echo $nl . "📱 [1/2] Testing WhatsApp Dispatch..." . $nl;

// Check WhatsApp Gateway status
$waStatus = $whatsAppService->getStatus();
$isConnected = !empty($waStatus['connected']) || ($waStatus['status'] ?? '') === 'connected';

echo "   WhatsApp Gateway Status: " . ($waStatus['status'] ?? 'unknown') . ($isConnected ? " (CONNECTED ✅)" : " (NOT CONNECTED ⚠️)") . $nl;

$waMessage = "Namaste {$recipientName}! 🙏\n\nThank you for connecting with *The House of Abhinandan Lodha*.\n\nThis confirms your automated WhatsApp notification channel is active and operational.\nTimestamp: " . date('Y-m-d H:i:s') . "\n\nWarm regards,\n*The House of Abhinandan Lodha*";

$waResult = $automationService->sendDirectWhatsApp($recipientPhone, $waMessage);

if (!empty($waResult['success'])) {
    echo "   ✅ WhatsApp Result: SENT SUCCESSFULLY!" . $nl;
    echo "   Details: " . ($waResult['message'] ?? 'Delivered') . $nl;
} else {
    echo "   ❌ WhatsApp Result: FAILED" . $nl;
    echo "   Error: " . ($waResult['error'] ?? 'Unknown error') . $nl;
    if (!$isConnected) {
        echo "   💡 Note: Please visit http://127.0.0.1:8000/whatsapp/connection to scan the QR code and connect your WhatsApp account." . $nl;
    }
}

// -------------------------------------------------------------
// 2. Email Dispatch Test
// -------------------------------------------------------------
echo $nl . "📧 [2/2] Testing Email Dispatch..." . $nl;

$emailSubject = "Welcome {$recipientName} - The House of Abhinandan Lodha";
$emailBody = "
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px;'>
    <div style='background: linear-gradient(135deg, #0f172a, #1e293b); padding: 24px; border-radius: 12px; color: #ffffff; text-align: center;'>
        <h1 style='margin: 0; font-size: 20px; font-weight: bold; color: #f59e0b; letter-spacing: 0.5px;'>The House of Abhinandan Lodha</h1>
        <p style='margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;'>Curated Land & Premium Living</p>
    </div>
    
    <div style='padding: 24px 8px 12px 8px; color: #334155; font-size: 14px; line-height: 1.6;'>
        <p>Dear <strong>{$recipientName}</strong>,</p>
        
        <p>Thank you for expressing your interest in <strong>The House of Abhinandan Lodha</strong>.</p>
        
        <div style='background: #f8fafc; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 8px; margin: 16px 0;'>
            <p style='margin: 0; font-weight: bold; color: #0f172a;'>Dispatch Verification Details:</p>
            <ul style='margin: 8px 0 0 0; padding-left: 20px; color: #475569;'>
                <li>Recipient Email: <strong>{$recipientEmail}</strong></li>
                <li>Recipient Phone: <strong>{$recipientPhone}</strong></li>
                <li>Timestamp: <strong>" . date('Y-m-d H:i:s') . "</strong></li>
                <li>Status: <strong>Verified & Operational</strong></li>
            </ul>
        </div>
        
        <p>Our dedicated Relationship Advisor will connect with you shortly to share exclusive land development opportunities, master layouts, and curated pricing options.</p>
        
        <p style='margin-top: 24px; color: #64748b; font-size: 12px; border-top: 1px solid #f1f5f9; padding-top: 16px;'>
            Warm regards,<br>
            <strong>The House of Abhinandan Lodha</strong><br>
            Season 4 Property Development
        </p>
    </div>
</div>";

$emailResult = $automationService->sendDirectEmail($recipientEmail, $emailSubject, $emailBody);

if (!empty($emailResult['success'])) {
    echo "   ✅ Email Result: DISPATCHED SUCCESSFULLY!" . $nl;
    echo "   Details: " . ($emailResult['message'] ?? 'Dispatched') . $nl;
} else {
    echo "   ❌ Email Result: FAILED" . $nl;
    echo "   Error: " . ($emailResult['error'] ?? 'Unknown error') . $nl;
}

echo $nl . ($isCli ? "==========================================" : "<hr style='border-color: #334155;'>") . $nl;
echo "🏁 Test execution completed." . $nl;
if (!$isCli) {
    echo "</div>";
}

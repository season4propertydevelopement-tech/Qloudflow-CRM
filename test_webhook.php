<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiService = app(\App\Services\WhatsAppApiService::class);
$controller = new \App\Http\Controllers\WebhookController();

echo "===================================================\n";
echo "Testing Qloudsoft WhatsApp Bot with Scraped Data\n";
echo "===================================================\n\n";

$testQueries = [
    'Hi',
    '1',
    'Tell me about Deluxe package',
    'What is your contact number and email?',
    'I want a mobile app for my business'
];

foreach ($testQueries as $query) {
    echo "---------------------------------------------------\n";
    echo "👤 User Sent: \"{$query}\"\n";
    
    $request = \Illuminate\Http\Request::create('/api/webhooks/whatsapp/incoming', 'POST', [
        'event' => 'message.received',
        'messageId' => 'test-msg-' . rand(1000, 9999),
        'phone' => '917387517576',
        'pushName' => 'Amar Test',
        'message' => $query
    ]);

    $controller->handleIncoming($request, $apiService);
    
    $latestBotMsg = \App\Models\Message::where('direction', 'outgoing')
        ->latest('id')
        ->first();
        
    echo "🤖 Bot Replied:\n" . ($latestBotMsg ? $latestBotMsg->message : 'No reply') . "\n\n";
}

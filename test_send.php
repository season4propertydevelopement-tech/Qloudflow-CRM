<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiService = app(App\Services\WhatsAppApiService::class);
$status = $apiService->getStatus();

if (!isset($status['phone'])) {
    echo "API is not connected or phone not found.\n";
    exit;
}

$phone = $status['phone'];
echo "Testing connection by sending a message to yourself ($phone)...\n";

$result = $apiService->sendMessage($phone, "Hello! This is a test message from your Qloudflow API to verify the connection is active.");

echo "Result:\n";
print_r($result);

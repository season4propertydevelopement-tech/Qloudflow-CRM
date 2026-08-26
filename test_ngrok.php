<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$json = @file_get_contents('http://127.0.0.1:4040/api/tunnels');
$data = json_decode($json, true);
$publicUrl = null;

foreach ($data['tunnels'] as $tunnel) {
    if ($tunnel['proto'] === 'https') {
        $publicUrl = $tunnel['public_url'];
        break;
    }
}

$webhookUrl = $publicUrl . '/Qloudflow-whatsapp-manager/laravel-whatsapp-manager/public/api/webhooks/whatsapp/incoming';
echo "Updating Webhook to: " . $webhookUrl . "\n";

$apiService = app(App\Services\WhatsAppApiService::class);
$result = $apiService->configureWebhook($webhookUrl);
print_r($result);

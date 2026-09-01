<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsAppApiService;
use App\Http\Controllers\WebhookController;
use App\Models\Message;
use Carbon\Carbon;

class WhatsAppListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'whatsapp:listen {--interval=1 : Polling interval in seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Listen for and auto-process live incoming WhatsApp messages in real-time';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppApiService $apiService, WebhookController $webhookController)
    {
        $this->info("========================================================");
        $this->info(" 🟢 Qloudflow WhatsApp Gateway Live Listener Running");
        $this->info("========================================================");
        $this->line("• Gateway URL: " . config('services.whatsapp.url', env('WHATSAPP_API_URL')));
        $this->line("• Polling Interval: " . $this->option('interval') . "s");
        $this->line("• Press Ctrl+C to stop listening.\n");

        $status = $apiService->getStatus();
        if (!empty($status['status'])) {
            $this->info("📱 Connected Phone: " . ($status['phone'] ?? 'N/A') . " (" . $status['status'] . ")\n");
        }

        $interval = max(1, (int) $this->option('interval'));

        while (true) {
            try {
                $sinceId = \Illuminate\Support\Facades\Cache::get('whatsapp_last_synced_message_id');
                $response = $apiService->getUnhandledMessages($sinceId);

                if (!empty($response['messages']) && is_array($response['messages'])) {
                    $latestId = null;
                    foreach ($response['messages'] as $payload) {
                        $messageId = $payload['messageId'] ?? null;
                        if ($messageId) {
                            $latestId = $messageId;
                        }
                        
                        // Check if already processed
                        if ($messageId && Message::where('external_message_id', $messageId)->exists()) {
                            continue;
                        }

                        $phone = $payload['real_phone'] ?? $payload['phone'] ?? 'Unknown';
                        $text = $payload['message'] ?? '[Media/Attachment]';
                        $time = Carbon::now()->format('H:i:s');

                        $this->line("<fg=cyan>[{$time}]</> 📩 <fg=yellow>Incoming from {$phone}:</> \"{$text}\"");

                        // Process message and trigger auto-reply
                        $webhookController->processIncomingMessage($payload, $apiService);

                        $this->line("<fg=cyan>[{$time}]</> 🤖 <fg=green>Auto-Reply Processed & Dispatched!</>\n");
                    }

                    if ($latestId) {
                        \Illuminate\Support\Facades\Cache::forever('whatsapp_last_synced_message_id', $latestId);
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Error while fetching messages: " . $e->getMessage());
            }

            sleep($interval);
        }
    }
}

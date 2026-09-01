<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppApiService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_API_URL', 'https://qloudflow-whatsapp-manager-api.onrender.com')), '/');
        $this->apiKey = config('services.whatsapp.key', env('WHATSAPP_API_KEY', 'local-development-key'));
    }

    protected function client(int $timeout = 10): \Illuminate\Http\Client\PendingRequest
    {
        $headers = [
            'Connection' => 'keep-alive',
            'Accept' => 'application/json',
        ];
        if (!empty($this->apiKey)) {
            $headers['X-API-Key'] = $this->apiKey;
        }

        return Http::withHeaders($headers)
            ->connectTimeout(5)
            ->timeout($timeout);
    }

    public function getStatus()
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/status");
            return $response->json() ?? ['status' => 'disconnected', 'connected' => false];
        } catch (\Throwable $e) {
            Log::warning("WhatsApp API getStatus error: " . $e->getMessage());
            return ['status' => 'offline', 'connected' => false, 'error' => $e->getMessage()];
        }
    }

    public function getQrCode()
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/qr");
            return $response->json();
        } catch (\Throwable $e) {
            Log::warning("WhatsApp API getQrCode error: " . $e->getMessage());
            return ['qr' => null, 'error' => $e->getMessage()];
        }
    }

    public function connect()
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/connect");
            return $response->json();
        } catch (\Throwable $e) {
            Log::warning("WhatsApp API connect error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function logout()
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/logout");
            return $response->json();
        } catch (\Throwable $e) {
            Log::warning("WhatsApp API logout error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendMessage($phone, $message)
    {
        try {
            $response = $this->client(15)->post("{$this->baseUrl}/api/messages/send", [
                'phone' => $phone,
                'message' => $message,
            ]);
            return $response->json() ?? ['success' => true];
        } catch (\Throwable $e) {
            Log::warning("WhatsApp API sendMessage error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendMedia($phone, $mediaUrl, $caption = '', ?string $mediaPath = null)
    {
        try {
            $payload = [
                'phone' => $phone,
                'media_url' => $mediaUrl,
                'caption' => $caption,
            ];

            if ($mediaPath && file_exists($mediaPath)) {
                $payload['media_path'] = $mediaPath;
                $mime = mime_content_type($mediaPath) ?: 'application/octet-stream';
                $payload['mime_type'] = $mime;
                $payload['file_name'] = basename($mediaPath);
                if (filesize($mediaPath) <= 15 * 1024 * 1024) {
                    $payload['media_base64'] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($mediaPath));
                }
            } elseif (is_string($mediaUrl) && file_exists($mediaUrl)) {
                $payload['media_path'] = $mediaUrl;
                $mime = mime_content_type($mediaUrl) ?: 'application/octet-stream';
                $payload['mime_type'] = $mime;
                $payload['file_name'] = basename($mediaUrl);
                if (filesize($mediaUrl) <= 15 * 1024 * 1024) {
                    $payload['media_base64'] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($mediaUrl));
                }
            }

            $response = $this->client(45)->post("{$this->baseUrl}/api/messages/send-media", $payload);
            return $response->json() ?? ['success' => true];
        } catch (\Throwable $e) {
            Log::warning("WhatsApp API sendMedia error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function configureWebhook(string $webhookUrl)
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/webhook/config", [
                'webhook_url' => $webhookUrl
            ]);
            return $response->json() ?? ['success' => true];
        } catch (\Throwable $e) {
            Log::warning("WhatsApp API configureWebhook error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getWebhookConfig()
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/webhook/config");
            return $response->json();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getUnhandledMessages(?string $sinceId = null)
    {
        try {
            $query = $sinceId ? "?since={$sinceId}" : '';
            $response = $this->client()->get("{$this->baseUrl}/api/messages/unhandled{$query}");
            return $response->json() ?? ['success' => false, 'messages' => []];
        } catch (\Throwable $e) {
            return ['success' => false, 'messages' => []];
        }
    }
}

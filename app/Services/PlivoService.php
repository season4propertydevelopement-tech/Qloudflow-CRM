<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlivoService
{
    protected string $authId;
    protected string $authToken;
    protected string $defaultFromNumber;
    protected string $agentName;
    protected string $baseUrl;

    public function __construct()
    {
        $this->authId = config('services.plivo.auth_id') ?: env('PLIVO_AUTH_ID', 'MAN2MXNDE2MWUTOWY5NS');
        $this->authToken = config('services.plivo.auth_token') ?: env('PLIVO_AUTH_TOKEN', 'OTY2OWFmODUtZDNjYS00YjRjLTU1ZjEtMTAxOWVm');
        $this->defaultFromNumber = config('services.plivo.phone_number') ?: env('PLIVO_PHONE_NUMBER', '918031803464');
        $this->agentName = config('services.plivo.agent_name') ?: env('PLIVO_AGENT_NAME', 'Avni');
        $this->baseUrl = "https://api.plivo.com/v1/Account/{$this->authId}/";
    }

    /**
     * Get configured Auth ID
     */
    public function getAuthId(): string
    {
        return $this->authId;
    }

    /**
     * Get Default Caller ID / Phone Number
     */
    public function getDefaultPhoneNumber(): string
    {
        return $this->defaultFromNumber;
    }

    /**
     * Get Calling Agent Name
     */
    public function getAgentName(): string
    {
        return $this->agentName;
    }

    /**
     * Query Plivo Account Details (Account Name, Cash Credits, Verification)
     */
    public function getAccountDetails(): array
    {
        try {
            $response = Http::withBasicAuth($this->authId, $this->authToken)
                ->timeout(10)
                ->withoutVerifying()
                ->get($this->baseUrl);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'account_name' => $data['name'] ?? 'N/A',
                    'auth_id' => $data['auth_id'] ?? $this->authId,
                    'account_type' => $data['account_type'] ?? 'standard',
                    'cash_credits' => $data['cash_credits'] ?? '0.00',
                    'currency' => '$',
                    'timezone' => $data['timezone'] ?? 'UTC',
                    'phone_number' => $this->defaultFromNumber,
                    'agent_name' => $this->agentName,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? ('HTTP ' . $response->status())
            ];
        } catch (\Exception $e) {
            Log::error('Plivo getAccountDetails error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Query configured Plivo Phone Numbers
     */
    public function getPhoneNumbers(): array
    {
        try {
            $response = Http::withBasicAuth($this->authId, $this->authToken)
                ->timeout(10)
                ->withoutVerifying()
                ->get($this->baseUrl . 'Number/');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'numbers' => $data['objects'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? ('HTTP ' . $response->status())
            ];
        } catch (\Exception $e) {
            Log::error('Plivo getPhoneNumbers error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Dispatch an Outbound PSTN Phone Call via Plivo
     */
    public function makeOutboundCall(string $to, string $answerUrl, ?string $from = null, ?string $hangupUrl = null, array $extraParams = []): array
    {
        $cleanTo = preg_replace('/\D/', '', $to);
        $cleanFrom = preg_replace('/\D/', '', $from ?: $this->defaultFromNumber);

        $payload = array_merge([
            'from' => $cleanFrom,
            'to' => $cleanTo,
            'answer_url' => $answerUrl,
            'answer_method' => 'POST',
        ], $extraParams);

        if (!empty($hangupUrl)) {
            $payload['hangup_url'] = $hangupUrl;
            $payload['hangup_method'] = 'POST';
        }

        try {
            Log::info("Dispatching Plivo Call to: {$cleanTo} from: {$cleanFrom} with answer_url: {$answerUrl}");

            $response = Http::withBasicAuth($this->authId, $this->authToken)
                ->timeout(15)
                ->withoutVerifying()
                ->post($this->baseUrl . 'Call/', $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['request_uuid'])) {
                return [
                    'success' => true,
                    'request_uuid' => $data['request_uuid'],
                    'message' => $data['message'] ?? 'Call initiated successfully.',
                    'from' => $cleanFrom,
                    'to' => $cleanTo,
                    'api_id' => $data['api_id'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => $data['error'] ?? $data['message'] ?? ('HTTP ' . $response->status()),
                'raw_response' => $data
            ];
        } catch (\Exception $e) {
            Log::error('Plivo makeOutboundCall error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate Plivo XML response with Play & Speech Recognition (<GetInput>)
     * Exclusively uses Swara Neural Audio Stream
     */
    public function generateSpeechXml(string $speakText, string $actionUrl, ?string $hints = null, int $speechTimeout = 2, ?string $audioUrl = null): string
    {
        $safeActionUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $effectiveAudioUrl = $audioUrl;

        if (empty($effectiveAudioUrl)) {
            $baseUrl = config('app.url', 'https://qloudflow.qloudsoft.in');
            $effectiveAudioUrl = rtrim($baseUrl, '/') . '/conversation-audio/greeting_general.mp3';
        }

        $safeAudioUrl = htmlspecialchars($effectiveAudioUrl, ENT_QUOTES, 'UTF-8');

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            "<Response>\n" .
            "    <GetInput action=\"{$safeActionUrl}\" method=\"POST\" inputType=\"speech\" speechEndTimeout=\"2\" executionTimeout=\"35\" redirect=\"true\">\n" .
            "        <Play>{$safeAudioUrl}</Play>\n" .
            "    </GetInput>\n" .
            "</Response>";
    }

    /**
     * Generate Plivo XML response to end call politely
     * Exclusively uses Swara Neural Audio Stream
     */
    public function generateHangupXml(string $farewellText = "Thank you for speaking with Qloudsoft Solutions. Have a wonderful day!", ?string $audioUrl = null): string
    {
        $effectiveAudioUrl = $audioUrl;

        if (empty($effectiveAudioUrl)) {
            $baseUrl = config('app.url', 'https://qloudflow.qloudsoft.in');
            $effectiveAudioUrl = rtrim($baseUrl, '/') . '/conversation-audio/call_farewell.mp3';
        }

        $safeAudioUrl = htmlspecialchars($effectiveAudioUrl, ENT_QUOTES, 'UTF-8');

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            "<Response>\n" .
            "    <Play>{$safeAudioUrl}</Play>\n" .
            "    <Hangup/>\n" .
            "</Response>";
    }
}

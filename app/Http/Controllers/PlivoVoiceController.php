<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PlivoService;
use App\Services\VoiceAgentService;
use App\Models\VoiceCall;
use App\Models\Contact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlivoVoiceController extends Controller
{
    protected PlivoService $plivoService;
    protected VoiceAgentService $voiceService;

    public function __construct(PlivoService $plivoService, VoiceAgentService $voiceService)
    {
        $this->plivoService = $plivoService;
        $this->voiceService = $voiceService;
    }

    /**
     * Return Plivo Account details and balance for UI HUD
     */
    public function accountStatus()
    {
        $details = $this->plivoService->getAccountDetails();
        return response()->json($details);
    }

    /**
     * Resolve public base URL for Plivo Webhooks (prefers public APP_URL over local IP)
     */
    protected function getPublicWebhookBaseUrl(): string
    {
        $appUrl = config('app.url') ?: env('APP_URL');
        if (!empty($appUrl) && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1')) {
            return rtrim($appUrl, '/');
        }
        return rtrim(url('/'), '/');
    }

    /**
     * Initiate Outbound PSTN Phone Call via Plivo
     */
    public function dial(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'name' => 'nullable|string'
        ]);

        $phone = $request->input('phone');
        $name = $request->input('name', 'Valued Customer');
        $callerId = $request->input('from') ?: $this->plivoService->getDefaultPhoneNumber();

        // Webhook URLs (resolves to public APP_URL e.g. ngrok or domain)
        $baseUrl = $this->getPublicWebhookBaseUrl();
        $answerUrl = $baseUrl . '/voice-agent/plivo/answer?caller_name=' . urlencode($name) . '&caller_phone=' . urlencode($phone);
        $hangupUrl = $baseUrl . '/voice-agent/plivo/hangup?caller_name=' . urlencode($name) . '&caller_phone=' . urlencode($phone);

        $result = $this->plivoService->makeOutboundCall($phone, $answerUrl, $callerId, $hangupUrl);

        if ($result['success']) {
            // Initialize cached session for this call
            $callUuid = $result['request_uuid'] ?? null;
            if ($callUuid) {
                Cache::put("plivo_call_{$callUuid}", [
                    'caller_name' => $name,
                    'caller_phone' => $phone,
                    'transcript' => [],
                    'start_time' => time(),
                ], now()->addHours(2));
            }

            return response()->json([
                'success' => true,
                'message' => "Calling {$phone} via Avni (Plivo: {$callerId})...",
                'request_uuid' => $result['request_uuid'],
                'from' => $result['from'],
                'to' => $result['to'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Could not initiate Plivo outbound phone call.'
        ], 400);
    }

    /**
     * Plivo Answer Webhook - Plays Avni's initial greeting and begins speech recognition
     */
    public function answer(Request $request)
    {
        $callUuid = $request->input('CallUUID') ?: $request->input('CallId') ?: 'unknown';
        $callerName = $request->query('caller_name', 'Customer');
        $callerPhone = $request->query('caller_phone', $request->input('To', ''));

        Log::info("Plivo Answer Webhook invoked for CallUUID: {$callUuid}, Caller: {$callerName}, Phone: {$callerPhone}");

        $baseUrl = $this->getPublicWebhookBaseUrl();
        $greeting = "Namaste! Main Qloudsoft Solutions se Avni baat kar rahi hoon. Kya aap apne business ki website ya digital growth ke baare mein baat karna chahte hain?";
        $audioUrl = $baseUrl . "/conversation-audio/greeting_general.mp3";

        // Store initial turn in cache
        $sessionData = Cache::get("plivo_call_{$callUuid}", [
            'caller_name' => $callerName,
            'caller_phone' => $callerPhone,
            'transcript' => [],
            'start_time' => time(),
        ]);

        $sessionData['transcript'][] = [
            'role' => 'agent',
            'text' => $greeting,
            'timestamp' => date('H:i')
        ];

        Cache::put("plivo_call_{$callUuid}", $sessionData, now()->addHours(2));

        $actionUrl = $baseUrl . "/voice-agent/plivo/input";

        $xml = $this->plivoService->generateSpeechXml($greeting, $actionUrl, null, 3, $audioUrl);

        Log::info("Plivo Answer XML response generated:\n" . $xml);

        return response(trim($xml), 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    /**
     * Plivo Input Webhook - Handles caller's spoken words and generates AI speech reply
     */
    public function input(Request $request)
    {
        $callUuid = $request->input('CallUUID') ?: $request->query('call_uuid') ?: $request->input('CallId') ?: 'unknown';
        $callerName = $request->query('caller_name', $request->input('caller_name', 'Customer'));
        $callerPhone = $request->query('caller_phone', $request->input('To', $request->input('caller_phone', '')));
        
        // Plivo passes transcribed speech in 'Speech' or 'SpeechResult'
        $speechText = trim(
            $request->input('Speech') ?: 
            $request->input('SpeechResult') ?: 
            $request->input('UnsavedSpeech') ?: 
            $request->input('Digits', '')
        );

        Log::info("Plivo Input Webhook invoked for CallUUID: {$callUuid}, Speech: {$speechText}, All Payload: " . json_encode($request->all()));

        $sessionData = Cache::get("plivo_call_{$callUuid}", [
            'caller_name' => $callerName,
            'caller_phone' => $callerPhone,
            'transcript' => [],
            'start_time' => time(),
        ]);

        $baseUrl = $this->getPublicWebhookBaseUrl();
        $actionUrl = $baseUrl . "/voice-agent/plivo/input";

        // If no speech detected, prompt again nicely
        if (empty($speechText)) {
            $fallbackMsg = "Main sun nahi paayi. Kya aap dobara bata sakte hain ki aapko website ya marketing service chahiye?";
            $fallbackAudioUrl = $baseUrl . "/conversation-audio/fallback_repeat.mp3";
            $xml = $this->plivoService->generateSpeechXml($fallbackMsg, $actionUrl, null, 3, $fallbackAudioUrl);
            return response(trim($xml), 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        }

        // Record user speech
        $sessionData['transcript'][] = [
            'role' => 'user',
            'text' => $speechText,
            'timestamp' => date('H:i')
        ];

        // Check fast-path pre-rendered neural audio for common repetitive questions (0ms latency)
        $fastMatch = $this->getFastPreGeneratedAudio($speechText, $baseUrl);
        if ($fastMatch) {
            $agentReply = $fastMatch['text'];
            $replyAudioUrl = $fastMatch['audio_url'];
            
            $sessionData['transcript'][] = [
                'role' => 'agent',
                'text' => $agentReply,
                'timestamp' => date('H:i')
            ];
            Cache::put("plivo_call_{$callUuid}", $sessionData, now()->addHours(2));

            $xml = $this->plivoService->generateSpeechXml($agentReply, $actionUrl, null, 3, $replyAudioUrl);
            return response(trim($xml), 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        }

        // Generate Avni Voice AI response via Gemini for custom queries
        $replyResult = $this->voiceService->generateVoiceReply(
            $speechText,
            'universal',
            $sessionData['transcript'],
            null,
            $callerName,
            $callerPhone
        );

        $agentReply = $replyResult['reply'] ?? "Qloudsoft Solutions me aapka swagat hai. Main aapki kya madad kar sakti hoon?";

        $sessionData['transcript'][] = [
            'role' => 'agent',
            'text' => $agentReply,
            'timestamp' => date('H:i')
        ];

        Cache::put("plivo_call_{$callUuid}", $sessionData, now()->addHours(2));

        $replyAudioUrl = $this->getOrCreateAudioUrl($agentReply, $baseUrl);

        // Check if call should end
        if (!empty($replyResult['is_call_ended'])) {
            $xml = $this->plivoService->generateHangupXml($agentReply, $replyAudioUrl);
            return response(trim($xml), 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        }

        $xml = $this->plivoService->generateSpeechXml($agentReply, $actionUrl, null, 3, $replyAudioUrl);

        return response(trim($xml), 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    /**
     * Get or create a direct static .mp3 URL for Plivo telephony XML compatibility
     */
    protected function getOrCreateAudioUrl(string $text, string $baseUrl): string
    {
        $fast = $this->getFastPreGeneratedAudio($text, $baseUrl);
        if ($fast) {
            return $fast['audio_url'];
        }

        $hash = substr(md5(trim($text)), 0, 16);
        $filePath = public_path("conversation-audio/plivo_{$hash}.mp3");
        $fileUrl = $baseUrl . "/conversation-audio/plivo_{$hash}.mp3";

        if (file_exists($filePath) && filesize($filePath) > 1000) {
            return $fileUrl;
        }

        try {
            $piperUrl = env('PIPER_TTS_URL', 'https://qloudsoft-piper-tts.onrender.com');
            $response = Http::timeout(6)->withoutVerifying()->post(rtrim($piperUrl, '/') . '/api/tts', [
                'text' => $text,
                'speaker' => 'swara',
                'speed' => 1.0,
                'noise_scale' => 0.667,
                'noise_w' => 0.8
            ]);

            if ($response->successful() && strlen($response->body()) > 1000) {
                if (!file_exists(dirname($filePath))) {
                    @mkdir(dirname($filePath), 0777, true);
                }
                file_put_contents($filePath, $response->body());
                return $fileUrl;
            }
        } catch (\Exception $e) {
            Log::warning("Piper synthesis for Plivo audio: " . $e->getMessage());
        }

        return $baseUrl . "/conversation-audio/greeting_general.mp3";
    }

    /**
     * Match user query against pre-generated high-speed static audio responses (0ms Latency)
     */
    protected function getFastPreGeneratedAudio(string $text, string $baseUrl): ?array
    {
        $clean = strtolower(trim($text));
        
        // 1. Website Cost & Packages
        if (preg_match('/(website|web design|site).*?(cost|price|rate|kitna|charges|package)/i', $clean) || preg_match('/(kitna|charges).*?(website|site)/i', $clean)) {
            return [
                'text' => "Humare custom website packages ₹15,000 se shuru hote hain jo 4 se 7 din mein ready ho jaate hain with free domain aur hosting.",
                'audio_url' => $baseUrl . '/conversation-audio/website_cost.mp3'
            ];
        }

        // 2. Company Address / Location
        if (preg_match('/(address|location|kahan|office|where are you|mumbai|palghar)/i', $clean)) {
            return [
                'text' => "Qloudsoft Solutions Mumbai aur Palghar, Maharashtra me located hai. Hum pan-India aur global clients ko serve karte hain.",
                'audio_url' => $baseUrl . '/conversation-audio/company_address.mp3'
            ];
        }

        // 3. SEO / SMM / Social Media Marketing
        if (preg_match('/(seo|smm|social media|google rank|first page|ranking|ads|marketing)/i', $clean)) {
            return [
                'text' => "Google SEO aur Social Media Marketing ₹12,000 per month se shuru hoti hai jisse aapka business Google ke 1st page par rank karega.",
                'audio_url' => $baseUrl . '/conversation-audio/smm_seo.mp3'
            ];
        }

        // 4. Mobile App Development
        if (preg_match('/(app|application|android|ios|mobile app|flutter)/i', $clean)) {
            return [
                'text' => "Android aur iOS Mobile App development ₹35,000 se start hota hai with Play Store aur App Store launch.",
                'audio_url' => $baseUrl . '/conversation-audio/app_development.mp3'
            ];
        }

        // 5. WhatsApp Proposal / Brochure / Details
        if (preg_match('/(whatsapp|brochure|portfolio|proposal|details bhejo|send details|share)/i', $clean)) {
            return [
                'text' => "Main abhi aapke isi WhatsApp number par hamara complete portfolio aur pricing brochure share kar rahi hoon.",
                'audio_url' => $baseUrl . '/conversation-audio/whatsapp_proposal.mp3'
            ];
        }

        // 6. Pricing Overview
        if (preg_match('/(pricing|all packages|rate list|kya charges hain|sab rate)/i', $clean)) {
            return [
                'text' => "Humare Starter Website ₹15,000, E-Commerce ₹35,000, aur Google SEO ₹12,000 per month se start hote hain.",
                'audio_url' => $baseUrl . '/conversation-audio/pricing_overview.mp3'
            ];
        }

        return null;
    }

    /**
     * Plivo Hangup Webhook - Analyzes completed call and saves to CRM database
     */
    public function hangup(Request $request)
    {
        $callUuid = $request->input('CallUUID') ?: $request->input('CallId') ?: 'unknown';
        $callerName = $request->query('caller_name', $request->input('caller_name', 'Customer'));
        $callerPhone = $request->query('caller_phone', $request->input('caller_phone', $request->input('To', '')));
        $durationSec = intval($request->input('Duration', 0));

        $min = str_pad(floor($durationSec / 60), 2, '0', STR_PAD_LEFT);
        $sec = str_pad($durationSec % 60, 2, '0', STR_PAD_LEFT);
        $durationStr = "{$min}:{$sec}";

        $sessionData = Cache::get("plivo_call_{$callUuid}", [
            'caller_name' => $callerName,
            'caller_phone' => $callerPhone,
            'transcript' => [],
            'start_time' => time(),
        ]);

        $transcript = $sessionData['transcript'] ?? [];

        if (!empty($transcript)) {
            // Run post-call CRM analysis
            $analysisResult = $this->voiceService->analyzeCall(
                $transcript,
                'universal',
                $callerName,
                $callerPhone,
                $durationStr
            );

            // Auto-persist to database
            try {
                $contact = null;
                if (!empty($callerPhone)) {
                    $cleanPhone = preg_replace('/\D/', '', $callerPhone);
                    $contact = Contact::where('phone', 'like', "%{$cleanPhone}%")
                        ->orWhere('whatsapp_id', 'like', "%{$cleanPhone}%")
                        ->first();
                }

                $analysisData = $analysisResult['analysis'] ?? [];

                $voiceCall = VoiceCall::create([
                    'contact_id' => $contact ? $contact->id : null,
                    'caller_name' => $callerName,
                    'caller_phone' => $callerPhone,
                    'persona_id' => 'universal',
                    'persona_name' => 'Avni',
                    'duration' => $durationStr,
                    'status' => 'completed',
                    'call_summary' => $analysisData['call_summary'] ?? 'Plivo outbound phone call with Avni.',
                    'lead_score' => $analysisData['lead_score'] ?? 80,
                    'lead_stage' => $analysisData['lead_stage'] ?? 'Warm Prospect',
                    'sentiment' => $analysisData['sentiment'] ?? 'Positive',
                    'key_entities' => $analysisData['key_entities'] ?? null,
                    'whatsapp_followup_message' => $analysisData['whatsapp_followup_message'] ?? null,
                    'transcript' => $transcript,
                    'model_used' => $analysisResult['model'] ?? 'Plivo-PSTN',
                    'latency_ms' => $analysisResult['latency_ms'] ?? 0,
                ]);

                if ($contact && isset($analysisData['lead_score'])) {
                    $score = intval($analysisData['lead_score']);
                    if ($score >= 80) {
                        $contact->lead_status = 'hot';
                    } elseif ($score >= 50 && $contact->lead_status === 'cold') {
                        $contact->lead_status = 'warm';
                    }
                    $contact->lead_score = max($contact->lead_score, $score);
                    $contact->save();
                }
            } catch (\Exception $e) {
                Log::warning('Plivo VoiceCall save notice: ' . $e->getMessage());
            }
        }

        Cache::forget("plivo_call_{$callUuid}");

        return response('OK', 200);
    }
}

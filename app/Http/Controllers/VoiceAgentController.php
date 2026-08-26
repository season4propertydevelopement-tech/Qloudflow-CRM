<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VoiceAgentService;
use App\Services\WhatsAppApiService;
use App\Models\Contact;
use App\Models\VoiceCall;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class VoiceAgentController extends Controller
{
    protected VoiceAgentService $voiceService;

    public function __construct(VoiceAgentService $voiceService)
    {
        $this->voiceService = $voiceService;
    }

    /**
     * Main Voice Calling Agent Studio Console
     */
    public function index(Request $request)
    {
        $agent = VoiceAgentService::getUniversalAgent();
        $personas = VoiceAgentService::getPersonas();
        $selectedPersonaId = 'universal';

        $callerName = $request->query('name', 'Rahul Sharma');
        $callerPhone = $request->query('phone', '+91 98765 43210');
        $contact = null;

        // If contact ID is passed, load contact
        if ($request->has('contact_id')) {
            $contact = Contact::find($request->query('contact_id'));
            if ($contact) {
                $callerName = $contact->name ?: $callerName;
                $callerPhone = $contact->phone ?: $callerPhone;
            }
        } elseif ($request->has('phone')) {
            $cleanPhone = preg_replace('/\D/', '', $request->query('phone'));
            $contact = Contact::where('phone', 'like', "%{$cleanPhone}%")
                ->orWhere('whatsapp_id', 'like', "%{$cleanPhone}%")
                ->first();
        }

        $activeApiKey = $this->voiceService->getApiKey();

        return view('voice_agent.index', compact(
            'agent',
            'personas',
            'selectedPersonaId',
            'callerName',
            'callerPhone',
            'contact',
            'activeApiKey'
        ));
    }

    /**
     * Call CRM Intelligence & History Hub
     */
    public function analytics(Request $request)
    {
        $calls = collect([]);
        $stats = [
            'total_calls' => 0,
            'avg_lead_score' => 0,
            'hot_prospects' => 0,
            'total_duration_sec' => 0,
        ];

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('voice_calls')) {
                $callsQuery = VoiceCall::with('contact')->latest();

                if ($request->filled('persona')) {
                    $callsQuery->where('persona_id', $request->persona);
                }

                if ($request->filled('sentiment')) {
                    $callsQuery->where('sentiment', $request->sentiment);
                }

                if ($request->filled('search')) {
                    $search = $request->search;
                    $callsQuery->where(function($q) use ($search) {
                        $q->where('caller_name', 'like', "%{$search}%")
                          ->orWhere('caller_phone', 'like', "%{$search}%")
                          ->orWhere('call_summary', 'like', "%{$search}%");
                    });
                }

                $calls = $callsQuery->paginate(15)->withQueryString();

                $allCalls = VoiceCall::all();
                $stats['total_calls'] = $allCalls->count();
                $stats['avg_lead_score'] = $allCalls->count() > 0 ? round($allCalls->avg('lead_score')) : 0;
                $stats['hot_prospects'] = $allCalls->where('lead_score', '>=', 80)->count();
                
                // Estimate total seconds from duration mm:ss strings
                $totalSecs = 0;
                foreach ($allCalls as $c) {
                    $parts = explode(':', $c->duration ?? '00:00');
                    if (count($parts) === 2) {
                        $totalSecs += (intval($parts[0]) * 60) + intval($parts[1]);
                    }
                }
                $stats['total_duration_sec'] = $totalSecs;
            }
        } catch (\Exception $e) {
            Log::warning('VoiceCall table query notice: ' . $e->getMessage());
        }

        return view('voice_agent.analytics', compact('calls', 'stats'));
    }

    /**
     * Handle Live Conversational Turn
     */
    public function chat(Request $request)
    {
        $userMessage = trim($request->input('message', ''));
        $personaId = trim($request->input('persona_id', 'avni'));
        $history = $request->input('history', []);
        $customPrompt = $request->input('custom_system_prompt');
        $callerName = $request->input('caller_name');
        $callerPhone = $request->input('caller_phone');
        $customApiKey = $request->header('X-Gemini-Key') ?: $request->input('custom_api_key');
        $customModel = $request->header('X-Gemini-Model') ?: $request->input('custom_model');

        if (empty($userMessage)) {
            return response()->json([
                'success' => false,
                'error' => 'User voice input cannot be empty'
            ], 400);
        }

        $result = $this->voiceService->generateVoiceReply(
            $userMessage,
            $personaId,
            $history,
            $customPrompt,
            $callerName,
            $callerPhone,
            $customApiKey,
            $customModel
        );

        return response()->json($result);
    }

    /**
     * Post-Call Analysis & Auto-Persistence
     */
    public function analyze(Request $request)
    {
        $transcript = $request->input('transcript', []);
        $personaId = $request->input('persona_id', 'avni');
        $callerName = $request->input('caller_name', 'Guest');
        $callerPhone = $request->input('caller_phone', '');
        $duration = $request->input('duration', '00:00');
        $customApiKey = $request->header('X-Gemini-Key') ?: $request->input('custom_api_key');
        $customModel = $request->header('X-Gemini-Model') ?: $request->input('custom_model');

        if (empty($transcript) || !is_array($transcript)) {
            return response()->json([
                'success' => false,
                'error' => 'Transcript array is required for analysis.'
            ], 400);
        }

        $personas = VoiceAgentService::getPersonas();
        $persona = $personas[$personaId] ?? $personas['avni'];

        $result = $this->voiceService->analyzeCall(
            $transcript,
            $personaId,
            $callerName,
            $callerPhone,
            $duration,
            $customApiKey,
            $customModel
        );

        $savedCallId = null;

        // Auto-save voice call to database if available
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('voice_calls')) {
                $contact = null;
                if (!empty($callerPhone)) {
                    $cleanPhone = preg_replace('/\D/', '', $callerPhone);
                    $contact = Contact::where('phone', 'like', "%{$cleanPhone}%")
                        ->orWhere('whatsapp_id', 'like', "%{$cleanPhone}%")
                        ->first();
                }

                $analysisData = $result['analysis'] ?? [];

                $voiceCall = VoiceCall::create([
                    'contact_id' => $contact ? $contact->id : null,
                    'caller_name' => $callerName,
                    'caller_phone' => $callerPhone,
                    'persona_id' => $personaId,
                    'persona_name' => $persona['name'],
                    'duration' => $duration,
                    'status' => 'completed',
                    'call_summary' => $analysisData['call_summary'] ?? null,
                    'lead_score' => $analysisData['lead_score'] ?? 70,
                    'lead_stage' => $analysisData['lead_stage'] ?? 'Warm Prospect',
                    'sentiment' => $analysisData['sentiment'] ?? 'Positive',
                    'key_entities' => $analysisData['key_entities'] ?? null,
                    'whatsapp_followup_message' => $analysisData['whatsapp_followup_message'] ?? null,
                    'transcript' => $transcript,
                    'model_used' => $result['model'] ?? null,
                    'latency_ms' => $result['latency_ms'] ?? null,
                ]);

                $savedCallId = $voiceCall->id;

                // If contact exists, upgrade lead score / status if high rating
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
            }
        } catch (\Exception $e) {
            Log::warning('VoiceCall database save error: ' . $e->getMessage());
        }

        $result['call_id'] = $savedCallId;

        return response()->json($result);
    }

    /**
     * Gemini Key & Model Connectivity Test
     */
    public function testKey(Request $request)
    {
        $testKey = trim($request->input('api_key', ''));
        $model = trim($request->input('model', 'gemini-flash-lite-latest'));

        if (empty($testKey)) {
            $testKey = $this->voiceService->getApiKey();
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => 'Reply with only: OK']]
                ]
            ]
        ];

        $result = $this->voiceService->executeGeminiCall($payload, $model, $testKey, 8);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Gemini API connection active & healthy!',
                'model' => $result['model'],
                'latency_ms' => $result['latency_ms']
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Connection test failed',
            'http_code' => $result['http_code'] ?? 500,
            'latency_ms' => $result['latency_ms'] ?? 0
        ]);
    }

    /**
     * High-Definition Human-Grade TTS Audio Stream with Sub-Millisecond Disk Caching
     */
    /**
     * High-Definition Human-Grade TTS Audio Stream (Exclusively Swara Neural Piper Engine)
     */
    public function ttsAudio(Request $request)
    {
        $text = trim($request->input('text', ''));
        if (empty($text)) {
            return response('', 400);
        }

        $cleanText = preg_replace('/[*_#`~>\[\]]/', '', $text);
        $cacheKey = md5($cleanText);
        $cacheDir = storage_path('framework/cache/tts');
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $cacheFilePath = $cacheDir . '/' . $cacheKey . '.mp3';

        // 0. Instant Cache Hit from public/conversation-audio (0ms Latency)
        $staticAudioPath = public_path('conversation-audio/' . preg_replace('/\.mp3$/i', '', strtolower(trim($text))) . '.mp3');
        if (file_exists($staticAudioPath) && filesize($staticAudioPath) > 500) {
            return response()->file($staticAudioPath, [
                'Content-Type' => 'audio/mpeg',
                'Cache-Control' => 'public, max-age=604800',
                'Access-Control-Allow-Origin' => '*'
            ]);
        }

        // 1. Instant Cache Hit from local storage (0ms Latency)
        if (file_exists($cacheFilePath) && filesize($cacheFilePath) > 200) {
            return response()->file($cacheFilePath, [
                'Content-Type' => 'audio/mpeg',
                'Cache-Control' => 'public, max-age=604800',
                'Access-Control-Allow-Origin' => '*'
            ]);
        }

        // 2. High-Fidelity Realistic Hindi Neural Engine (Exclusively Swara Neural Piper)
        $piperUrl = env('RENDER_PIPER_TTS_URL') ?: 'https://qloudsoft-piper-tts.onrender.com';
        $endpoint = rtrim($piperUrl, '/') . '/tts';

        try {
            $ttsRes = Http::timeout(10)->post($endpoint, [
                'text'   => $cleanText,
                'voice'  => 'hi-IN-SwaraNeural',
                'format' => 'mp3',
                'rate'   => '+0%'
            ]);

            if ($ttsRes->successful() && strlen($ttsRes->body()) > 500) {
                @file_put_contents($cacheFilePath, $ttsRes->body());
                return response()->file($cacheFilePath, [
                    'Content-Type' => 'audio/mpeg',
                    'Cache-Control' => 'public, max-age=604800',
                    'Access-Control-Allow-Origin' => '*'
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Swara Neural Piper TTS error: " . $e->getMessage());
        }

        return response('', 500);
    }


    /**
     * Dispatch WhatsApp Follow-Up directly via WhatsApp API Service
     */
    public function sendFollowupWhatsApp(Request $request, WhatsAppApiService $apiService)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string'
        ]);

        $phone = $request->input('phone');
        $messageText = $request->input('message');

        $response = $apiService->sendMessage($phone, $messageText);

        if (isset($response['success']) && $response['success']) {
            // Find or create message log if contact exists
            $cleanPhone = preg_replace('/\D/', '', $phone);
            $contact = Contact::where('phone', 'like', "%{$cleanPhone}%")
                ->orWhere('whatsapp_id', 'like', "%{$cleanPhone}%")
                ->first();

            if ($contact) {
                $conversation = Conversation::firstOrCreate([
                    'contact_id' => $contact->id
                ]);

                Message::create([
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'external_message_id' => $response['messageId'] ?? null,
                    'direction' => 'outgoing',
                    'message' => $messageText,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'is_bot_message' => false,
                ]);

                $conversation->update(['last_message_at' => now()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Follow-up WhatsApp message sent successfully!'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $response['error'] ?? $response['message'] ?? 'Could not dispatch WhatsApp message.'
        ], 400);
    }
}

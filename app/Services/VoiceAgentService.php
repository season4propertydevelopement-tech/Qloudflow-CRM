<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoiceAgentService
{
    /**
     * Default Gemini Fallback API Key if not set in .env
     */
    const FALLBACK_KEY = 'AQ.Ab8RN6I2QsY-sWwlMz8cTV06khzfmWw3cVQwYyATusgSPgmDVA';

    /**
     * Centralized Shared Business Knowledge Base for Qloudsoft Solutions & Qloudflow Suite
     * (Shared with WhatsApp Chatbot Engine for 100% consistency)
     */
    public static function getSharedBusinessKnowledge(): string
    {
        return <<<EOT
COMPANY IDENTITY & PROFILE:
- Company Name: Qloudsoft Solutions (Qloudflow Suite)
- Location: Mumbai / Palghar, Maharashtra, India
- Website: https://qloudsoft.in
- Phone / WhatsApp: +91 73875 17576 (+917387517576)
- Email: info@qloudsoft.in | support@qloudsoft.in
- Certifications: Google Ads Search, Creative & AI Certified, Google Analytics Certified.

CORE PACKAGES & PRICING:
1. Starter Website Package: ₹15,000/-
   - Up to 5 pages responsive modern website, free 1-year domain (.com/.in) & cloud hosting, WhatsApp chat button, basic SEO, 4–7 days turnaround.
2. Business Growth Website: ₹25,000/-
   - Up to 10 pages, custom animated UI/UX, easy admin CMS panel, lead capture forms with instant alerts, speed optimization, 1-year support.
3. E-Commerce & Custom Web App: ₹35,000+
   - Online store, UPI/Razorpay payment gateway, order tracking, inventory dashboard, user accounts, highly scalable on Laravel / React.
4. Mobile App Development (Android & iOS): ₹35,000 to ₹95,000+
   - Flutter / React Native cross-platform apps, push notifications, payment integration, Play Store & App Store deployment.
5. Google SEO & Local GMB Ranking: ₹12,000 to ₹32,000/month
   - Rank on Google 1st page, Google Map 3-pack optimization, keyword ranking, monthly progress reports.
6. Digital Marketing & Paid PPC Ads (Google Ads & Meta Ads): ₹12,000 to ₹54,500/month
   - High-intent lead generation campaigns, Google Search Ads, Instagram/Facebook targeted ads, conversion tracking.
7. Qloudflow WhatsApp & AI Automation Suite:
   - Automated 24/7 WhatsApp chatbots, live inbox multi-agent handoff, CRM contact lead qualification, and real-time AI Voice Calling agents.

KEY GUARANTEES & PROCESS:
- 4 to 7 days ultra-fast project delivery.
- 2-tier quality review (Senior Manager & Lead Developer review).
- 100% mobile and speed optimized.
- Milestone-based secure payment schedule.
- When customer requests portfolio, pricing brochure, or proposal, offer to send it immediately on their WhatsApp or book a quick 15-minute discovery consultation.
EOT;
    }

    /**
     * Single Universal AI Voice Calling Consultant (Avni - Qloudsoft Solutions)
     */
    public static function getUniversalAgent(): array
    {
        return [
            'id' => 'universal',
            'name' => 'Avni',
            'gender' => 'female',
            'role' => 'Senior Growth & Digital Solutions Consultant',
            'avatar' => '👩‍💼',
            'company' => 'Qloudsoft Solutions',
            'color' => '#6366f1',
            'badge' => 'Universal Voice AI',
            'language' => 'Hinglish / Hindi',
            'lang_code' => 'hi-IN',
            'voice_tone' => 'Warm, Natural, Conversational Indian Female',
            'emotion_preset' => 'Natural & Consultative',
            'voice_pitch' => 1.0,
            'voice_rate' => 1.0,
            'greeting' => "Namaste! Main Qloudsoft Solutions se Avni baat kar rahi hoon. Kya aap apne business ki website ya digital growth ke baare mein baat karna chahte hain?",
            'system_prompt' => "You are Avni, a warm, polite, natural, and highly articulate Senior Growth Consultant at Qloudsoft Solutions (Mumbai) on a live phone call.
Speak in natural everyday conversational Hinglish (mixing Hindi and English naturally, e.g., 'Namaste! Bilkul sir, humare custom website packages ₹15,000 se start hote hain jo 4–7 days me ready ho jate hain', 'Aapka business kis industry mein hai?').
Write all Hindi words in Roman English script (Hinglish) so the speech engine sounds smooth and fluent.
Keep every response short: 1 to 2 sentences maximum.
Never use bullet points, asterisks, emojis, or markdown.
Sound like a real, warm human consultant from Mumbai. Cover all Qloudsoft services (Websites ₹15k-₹35k, Mobile Apps, Google SEO ₹12k/mo, Google & Meta Ads, WhatsApp automation), and invite them for a quick 15-min discovery call or offer to send the proposal on WhatsApp (+91 73875 17576).",
            'sample_questions' => [
                "Website banane ka kitna charge hoga aur kitne din me banegi?",
                "Google search aur Google Maps par first page ranking kaise hogi?",
                "WhatsApp par package details aur proposal bhej dijiye."
            ]
        ];
    }

    /**
     * Backward-compatible Personas getter returning the Universal Agent (Avni)
     */
    public static function getPersonas(): array
    {
        $universal = self::getUniversalAgent();
        return [
            'universal' => $universal,
            'avni' => $universal,
            'ananya' => $universal
        ];
    }

    /**
     * Resolve effective Gemini API Key
     */
    public function getApiKey(?string $customKey = null): string
    {
        if (!empty($customKey)) {
            return trim($customKey);
        }

        $sessionKey = session('custom_gemini_api_key');
        if (!empty($sessionKey)) {
            return trim($sessionKey);
        }

        $configKey = config('services.gemini.api_key');
        if (!empty($configKey)) {
            return trim($configKey);
        }

        $envKey = env('GEMINI_API_KEY');
        if (!empty($envKey)) {
            return trim($envKey);
        }

        return self::FALLBACK_KEY;
    }

    /**
     * Execute Gemini API request with multi-model failover cascade
     */
    public function executeGeminiCall(array $payload, ?string $preferredModel = null, ?string $customApiKey = null, int $timeoutSec = 5): array
    {
        $apiKey = $this->getApiKey($customApiKey);
        $activeModel = $preferredModel ?: env('GEMINI_MODEL', 'gemini-3.1-flash-lite');

        $candidateModels = array_unique([
            $activeModel,
            'gemini-3.1-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-flash-latest'
        ]);

        $lastError = 'Unknown error';
        $lastHttpCode = 0;
        $overallStart = microtime(true);

        foreach ($candidateModels as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

            try {
                $response = Http::timeout($timeoutSec)
                    ->withoutVerifying()
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $payload);

                $httpCode = $response->status();

                if ($httpCode === 200 && $response->successful()) {
                    $data = $response->json();
                    $extractedText = '';

                    if (!empty($data['candidates'][0]['content']['parts'])) {
                        foreach ($data['candidates'][0]['content']['parts'] as $part) {
                            if (isset($part['text'])) {
                                $extractedText .= $part['text'];
                            }
                        }
                    }

                    if (!empty(trim($extractedText))) {
                        $totalLatency = round((microtime(true) - $overallStart) * 1000);
                        return [
                            'success' => true,
                            'text' => trim($extractedText),
                            'model' => $model,
                            'latency_ms' => $totalLatency,
                            'http_code' => 200
                        ];
                    }
                }

                $json = $response->json();
                $lastError = $json['error']['message'] ?? ('HTTP ' . $httpCode);
                $lastHttpCode = $httpCode;
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $lastHttpCode = 500;
            }
        }

        $totalLatency = round((microtime(true) - $overallStart) * 1000);
        return [
            'success' => false,
            'error' => $lastError,
            'latency_ms' => $totalLatency,
            'http_code' => $lastHttpCode
        ];
    }

    /**
     * Process conversational live phone turn trained on shared business knowledge
     */
    public function generateVoiceReply(string $userMessage, ?string $personaId = 'universal', array $history = [], ?string $customPrompt = null, ?string $callerName = null, ?string $callerPhone = null, ?string $customApiKey = null, ?string $customModel = null): array
    {
        $agent = self::getUniversalAgent();
        $lowerMsg = strtolower(trim($userMessage));

        // Fast-Path 0ms Instant Match for common repetitive questions
        if (preg_match('/(website|web design|site).*?(cost|price|rate|kitna|charges|package)/i', $lowerMsg) || preg_match('/(kitna|charges).*?(website|site)/i', $lowerMsg)) {
            return [
                'success' => true,
                'reply' => "Humare custom website packages ₹15,000 se shuru hote hain jo 4 se 7 din mein ready ho jaate hain with free domain aur hosting.",
                'raw_reply' => "Humare custom website packages ₹15,000 se shuru hote hain jo 4 se 7 din mein ready ho jaate hain with free domain aur hosting.",
                'persona_id' => 'universal',
                'persona_name' => $agent['name'],
                'persona_avatar' => $agent['avatar'],
                'voice_pitch' => $agent['voice_pitch'],
                'voice_rate' => $agent['voice_rate'],
                'lang_code' => $agent['lang_code'],
                'sentiment' => 'Positive',
                'emotion_hud' => ['preset' => $agent['emotion_preset'], 'tone' => $agent['voice_tone'], 'speech_rate' => '1.0x'],
                'latency_ms' => 10,
                'model' => 'fast-cache'
            ];
        }

        if (preg_match('/(address|location|kahan|office|where are you|mumbai|palghar)/i', $lowerMsg)) {
            return [
                'success' => true,
                'reply' => "Qloudsoft Solutions Mumbai aur Palghar, Maharashtra me located hai. Hum pan-India aur global clients ko serve karte hain.",
                'raw_reply' => "Qloudsoft Solutions Mumbai aur Palghar, Maharashtra me located hai. Hum pan-India aur global clients ko serve karte hain.",
                'persona_id' => 'universal',
                'persona_name' => $agent['name'],
                'persona_avatar' => $agent['avatar'],
                'voice_pitch' => $agent['voice_pitch'],
                'voice_rate' => $agent['voice_rate'],
                'lang_code' => $agent['lang_code'],
                'sentiment' => 'Inquisitive',
                'emotion_hud' => ['preset' => $agent['emotion_preset'], 'tone' => $agent['voice_tone'], 'speech_rate' => '1.0x'],
                'latency_ms' => 10,
                'model' => 'fast-cache'
            ];
        }

        if (preg_match('/(seo|smm|social media|google rank|first page|ranking|ads|marketing)/i', $lowerMsg)) {
            return [
                'success' => true,
                'reply' => "Google SEO aur Social Media Marketing ₹12,000 per month se shuru hoti hai jisse aapka business Google ke 1st page par rank karega.",
                'raw_reply' => "Google SEO aur Social Media Marketing ₹12,000 per month se shuru hoti hai jisse aapka business Google ke 1st page par rank karega.",
                'persona_id' => 'universal',
                'persona_name' => $agent['name'],
                'persona_avatar' => $agent['avatar'],
                'voice_pitch' => $agent['voice_pitch'],
                'voice_rate' => $agent['voice_rate'],
                'lang_code' => $agent['lang_code'],
                'sentiment' => 'Positive',
                'emotion_hud' => ['preset' => $agent['emotion_preset'], 'tone' => $agent['voice_tone'], 'speech_rate' => '1.0x'],
                'latency_ms' => 10,
                'model' => 'fast-cache'
            ];
        }

        if (preg_match('/(whatsapp|brochure|portfolio|proposal|details bhejo|send details|share)/i', $lowerMsg)) {
            return [
                'success' => true,
                'reply' => "Main abhi aapke isi WhatsApp number par hamara complete portfolio aur pricing brochure share kar rahi hoon.",
                'raw_reply' => "Main abhi aapke isi WhatsApp number par hamara complete portfolio aur pricing brochure share kar rahi hoon.",
                'persona_id' => 'universal',
                'persona_name' => $agent['name'],
                'persona_avatar' => $agent['avatar'],
                'voice_pitch' => $agent['voice_pitch'],
                'voice_rate' => $agent['voice_rate'],
                'lang_code' => $agent['lang_code'],
                'sentiment' => 'Positive',
                'emotion_hud' => ['preset' => $agent['emotion_preset'], 'tone' => $agent['voice_tone'], 'speech_rate' => '1.0x'],
                'latency_ms' => 10,
                'model' => 'fast-cache'
            ];
        }

        if (preg_match('/(pricing|all packages|rate list|kya charges hain|sab rate)/i', $lowerMsg)) {
            return [
                'success' => true,
                'reply' => "Humare Starter Website ₹15,000, E-Commerce ₹35,000, aur Google SEO ₹12,000 per month se start hote hain.",
                'raw_reply' => "Humare Starter Website ₹15,000, E-Commerce ₹35,000, aur Google SEO ₹12,000 per month se start hote hain.",
                'persona_id' => 'universal',
                'persona_name' => $agent['name'],
                'persona_avatar' => $agent['avatar'],
                'voice_pitch' => $agent['voice_pitch'],
                'voice_rate' => $agent['voice_rate'],
                'lang_code' => $agent['lang_code'],
                'sentiment' => 'Positive',
                'emotion_hud' => ['preset' => $agent['emotion_preset'], 'tone' => $agent['voice_tone'], 'speech_rate' => '1.0x'],
                'latency_ms' => 10,
                'model' => 'fast-cache'
            ];
        }

        $systemInstructionText = !empty($customPrompt) ? $customPrompt : $agent['system_prompt'];
        $businessKnowledge = self::getSharedBusinessKnowledge();

        $telephonyRule = "\n\nCRITICAL PHONE CALL & BUSINESS RULES:\n" .
            "- This is a LIVE PHONE CALL for Qloudsoft Solutions (Mumbai).\n" .
            "- Keep your answer ULTRA SHORT: exactly 1 crisp, natural conversational sentence (maximum 15 words).\n" .
            "- Speak naturally in everyday Indian Hinglish (Roman English letters). Do not use Devanagari script.\n" .
            "- DO NOT use any markdown formatting, asterisks, bullet points, headers, or emojis.\n" .
            "- Sound natural, human, warm, conversational, and respectful ('Aap', 'Ji', 'Hum').\n" .
            "- Use the shared business knowledge accurately for packages (₹15,000 Starter to ₹35,000 E-Commerce, 4–7 days delivery, SEO ₹12k/mo, Phone: +91 73875 17576).\n" .
            "- If the caller asks to end the call or says goodbye, politely say goodbye and end your sentence with [CALL_ENDED].\n";

        if (!empty($callerName)) {
            $telephonyRule .= "- The caller's name is: " . htmlspecialchars($callerName) . ".\n";
        }
        if (!empty($callerPhone)) {
            $telephonyRule .= "- The caller's phone number is: " . htmlspecialchars($callerPhone) . ".\n";
        }

        $fullSystemInstruction = $systemInstructionText . "\n\n" . $businessKnowledge . $telephonyRule;

        $contents = [];

        // Add recent conversation history (last 4 turns for lowest latency)
        if (is_array($history)) {
            $recentHistory = array_slice($history, -4);
            foreach ($recentHistory as $turn) {
                $role = ($turn['role'] === 'agent' || $turn['role'] === 'model') ? 'model' : 'user';
                $text = trim($turn['text'] ?? ($turn['parts'][0]['text'] ?? ''));
                if (!empty($text)) {
                    $contents[] = [
                        'role' => $role,
                        'parts' => [['text' => $text]]
                    ];
                }
            }
        }

        // Add current turn
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $fullSystemInstruction]]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topP' => 0.8,
                'maxOutputTokens' => 45
            ],
            'contents' => $contents
        ];

        $result = $this->executeGeminiCall($payload, $customModel, $customApiKey, 5);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Could not generate speech response.',
                'latency_ms' => $result['latency_ms'] ?? 0,
                'http_code' => $result['http_code'] ?? 500
            ];
        }

        $rawReply = $result['text'];
        $isCallEnded = false;
        if (str_contains($rawReply, '[CALL_ENDED]')) {
            $isCallEnded = true;
            $rawReply = str_replace('[CALL_ENDED]', '', $rawReply);
        }

        $cleanSpeechText = trim(preg_replace('/[*_#`~>\[\]]/', '', $rawReply));

        // Sentiment detection for HUD
        $sentiment = 'Neutral';
        $lowerMsg = strtolower($userMessage . ' ' . $cleanSpeechText);
        if (preg_match('/(great|awesome|yes|interested|sure|perfect|love|book|schedule|deal|good|ok|haan|bilkul|theek|chahiye)/', $lowerMsg)) {
            $sentiment = 'Positive';
        } elseif (preg_match('/(no|not|expensive|bad|cancel|complaint|angry|problem|issue|bekaar|nahi|mat)/', $lowerMsg)) {
            $sentiment = 'Concerned';
        } elseif (preg_match('/(how|what|when|where|why|price|cost|timing|kya|kaise|kitna|charges)/', $lowerMsg)) {
            $sentiment = 'Inquisitive';
        }

        return [
            'success' => true,
            'reply' => $cleanSpeechText,
            'raw_reply' => $rawReply,
            'persona_id' => 'universal',
            'persona_name' => $agent['name'],
            'persona_avatar' => $agent['avatar'],
            'voice_pitch' => $agent['voice_pitch'],
            'voice_rate' => $agent['voice_rate'],
            'lang_code' => $agent['lang_code'],
            'sentiment' => $sentiment,
            'emotion_hud' => [
                'preset' => $agent['emotion_preset'],
                'tone' => $agent['voice_tone'],
                'speech_rate' => $agent['voice_rate'] . 'x',
            ],
            'is_call_ended' => $isCallEnded,
            'model' => $result['model'],
            'latency_ms' => $result['latency_ms']
        ];
    }

    /**
     * Analyze Post-Call Conversation for CRM Intelligence (Aligned with Qloudsoft Solutions)
     */
    public function analyzeCall(array $transcript, ?string $personaId, ?string $callerName, ?string $callerPhone, string $callDuration = '00:00', ?string $customApiKey = null, ?string $customModel = null): array
    {
        $agent = self::getUniversalAgent();

        $transcriptText = "";
        foreach ($transcript as $entry) {
            $speaker = ($entry['role'] === 'agent' || $entry['role'] === 'model') ? $agent['name'] : ($callerName ?: 'User');
            $msg = trim($entry['text'] ?? '');
            $transcriptText .= "[$speaker]: $msg\n";
        }

        $systemPrompt = "You are a Senior CRM Intelligence Analyst for Qloudsoft Solutions (Websites, Mobile Apps, Google SEO & Digital Ads in Mumbai, India).
Analyze the provided phone call transcript between the AI consultant and the customer.
You must return a strictly valid JSON object with NO markdown enclosing, NO backticks, and NO prefix/suffix text.

JSON Schema:
{
  \"call_summary\": \"A concise 2-3 sentence executive summary of the phone conversation regarding Qloudsoft services.\",
  \"lead_score\": 85,
  \"lead_stage\": \"Hot Qualified Prospect | Warm Follow-up | Proposal Requested | Support Resolved | Cold / Unqualified\",
  \"sentiment\": \"Positive | Neutral | Dissatisfied | Interested | High Urgency\",
  \"key_entities\": {
    \"customer_name\": \"Extracted or provided name\",
    \"phone\": \"Extracted or provided phone\",
    \"intent\": \"Primary business service inquired (e.g. Starter Website ₹15k, E-Commerce ₹35k, Google SEO, Mobile App)\",
    \"budget_or_value\": \"Discussed budget, package tier, or scope if any\",
    \"pain_point\": \"Main challenge or requirement mentioned\",
    \"next_step\": \"Agreed action item (e.g. Send WhatsApp proposal, Schedule 15-min discovery call, Share sample portfolio)\"
  },
  \"whatsapp_followup_message\": \"A personalized, warm WhatsApp message from Qloudsoft Solutions (+91 73875 17576) following up on the call. Include relevant package details or proposal link with emojis and clean formatting.\"
}";

        $userPrompt = "Caller Name: " . ($callerName ?: 'Not specified') . "\n" .
                      "Caller Phone: " . ($callerPhone ?: 'Not specified') . "\n" .
                      "Call Duration: $callDuration\n" .
                      "Agent: " . $agent['name'] . " (" . $agent['role'] . ")\n\n" .
                      "--- CONVERSATION TRANSCRIPT ---\n" .
                      $transcriptText . "\n" .
                      "--- END TRANSCRIPT ---\n\n" .
                      "Analyze this call and return ONLY the JSON object:";

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 600
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userPrompt]]
                ]
            ]
        ];

        $result = $this->executeGeminiCall($payload, $customModel, $customApiKey, 10);

        if ($result['success'] && !empty($result['text'])) {
            $rawAnalysis = $result['text'];
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawAnalysis));
            $parsed = json_decode($cleanJson, true);

            if ($parsed && is_array($parsed)) {
                return [
                    'success' => true,
                    'analysis' => $parsed,
                    'raw_transcript' => $transcriptText,
                    'duration' => $callDuration,
                    'model' => $result['model'],
                    'latency_ms' => $result['latency_ms']
                ];
            }
        }

        // Fallback default CRM report for Qloudsoft
        return [
            'success' => true,
            'analysis' => [
                'call_summary' => 'Call concluded with ' . count($transcript) . ' conversational turns. Customer discussed Qloudsoft digital solutions with ' . $agent['name'] . '.',
                'lead_score' => 80,
                'lead_stage' => 'Warm Follow-up',
                'sentiment' => 'Positive',
                'key_entities' => [
                    'customer_name' => $callerName ?: 'Guest',
                    'phone' => $callerPhone ?: 'N/A',
                    'intent' => 'Website Development & Digital Services',
                    'budget_or_value' => '₹15,000 - ₹35,000',
                    'pain_point' => 'Online business presence & lead generation',
                    'next_step' => 'Send WhatsApp package brochure and schedule review'
                ],
                'whatsapp_followup_message' => "Namaste " . ($callerName ?: 'ji') . "! 🌟 Qloudsoft Solutions (+91 73875 17576) ki taraf se " . $agent['name'] . " baat kar rahi hoon. Call par baat karke bahut accha laga! Aapke liye website packages aur proposal details ready hain. 🚀"
            ],
            'raw_transcript' => $transcriptText,
            'duration' => $callDuration,
            'model' => 'default-fallback',
            'latency_ms' => 0
        ];
    }
}

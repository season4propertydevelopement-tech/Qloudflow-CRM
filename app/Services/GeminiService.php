<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;
    protected ?string $projectId;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
        $this->projectId = config('services.gemini.project_id');
    }

    /**
     * Generate concise, natural, custom response for WhatsApp user query with auto-failover cascade.
     */
    public function generateReply(string $userMessage, array $conversationHistory = []): ?string
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $systemInstruction = $this->buildSystemInstruction();

        // Model Cascade Chain: primary -> gemini-2.0-flash -> gemini-1.5-flash -> gemini-2.5-flash
        $modelsToTry = array_unique([
            $this->model,
            'gemini-3.6-flash',
            'gemini-3.1-flash-lite',
            'gemma-4-31b-it',
        ]);

        foreach ($modelsToTry as $candidateModel) {
            try {
                $isGemma = str_starts_with($candidateModel, 'gemma-');
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$candidateModel}:generateContent?key={$this->apiKey}";

                $contents = [];

                if ($isGemma) {
                    // For Gemma models, prepend instruction into first user turn
                    $promptWithContext = "Instruction:\n{$systemInstruction}\n\n";
                    if (!empty($conversationHistory)) {
                        $promptWithContext .= "Recent Conversation:\n";
                        foreach ($conversationHistory as $historyItem) {
                            $sender = ($historyItem['direction'] === 'outgoing') ? 'Assistant' : 'Client';
                            $promptWithContext .= "{$sender}: {$historyItem['message']}\n";
                        }
                    }
                    $promptWithContext .= "\nClient Query: {$userMessage}\n\nAssistant Response (1-2 crisp sentences):";

                    $contents[] = [
                        'role' => 'user',
                        'parts' => [['text' => $promptWithContext]]
                    ];

                    $payload = [
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.5,
                            'maxOutputTokens' => 300
                        ]
                    ];
                } else {
                    // For Gemini models (3.1 flash lite, 3.5 flash)
                    foreach ($conversationHistory as $historyItem) {
                        $role = ($historyItem['direction'] === 'outgoing') ? 'model' : 'user';
                        $contents[] = [
                            'role' => $role,
                            'parts' => [['text' => $historyItem['message']]]
                        ];
                    }
                    $contents[] = [
                        'role' => 'user',
                        'parts' => [['text' => $userMessage]]
                    ];

                    $payload = [
                        'systemInstruction' => [
                            'parts' => [['text' => $systemInstruction]]
                        ],
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 350
                        ]
                    ];
                }

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ])
                ->timeout(5)
                ->post($url, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $parts = $data['candidates'][0]['content']['parts'] ?? [];
                    $reply = null;

                    // Filter out any thinking/thought parts from newer Gemini models
                    foreach ($parts as $part) {
                        if (empty($part['thought']) && !empty($part['text'])) {
                            $reply = $part['text'];
                        }
                    }

                    if (empty($reply) && !empty($parts[0]['text'])) {
                        $reply = $parts[0]['text'];
                    }

                    if (!empty($reply)) {
                        return $this->formatWhatsAppText(trim($reply));
                    }
                } else {
                    $status = $response->status();
                    Log::warning("Model [{$candidateModel}] returned {$status}, attempting failover...", [
                        'body' => $response->body()
                    ]);
                    // 429 quota/credit exhausted, 503 unavailable, or 404 -> automatically try next model in loop!
                }
            } catch (\Throwable $e) {
                Log::warning("Model [{$candidateModel}] exception: {$e->getMessage()}, shifting to next model...");
            }
        }

        return null;
    }

    /**
     * Classify lead temperature (Hot, Warm, Cold) based on cumulative conversation history.
     */
    public function classifyLeadFromHistory(array $messages): ?array
    {
        if (empty($this->apiKey) || empty($messages)) {
            return null;
        }

        $transcript = "";
        foreach ($messages as $m) {
            $sender = ($m['direction'] === 'incoming') ? 'Client' : 'Assistant';
            $text = $m['message'] ?? '';
            $transcript .= "{$sender}: {$text}\n";
        }

        $prompt = <<<EOT
Analyze the following WhatsApp conversation between a Client and an Assistant for a digital services agency (Websites, Apps, SEO).

Conversation Transcript:
{$transcript}

Evaluate the client's messages and classify into ONE category:
- "hot": Client has explicit commercial/purchase intent (asking about pricing, quotes, packages, costs, hiring, starting a project, contract, or scheduling a sales call).
- "warm": Client is asking informational questions about services, capabilities, tech stack, or portfolio samples, but has NOT asked about pricing, quote, or hiring yet.
- "cold": Client only exchanged basic greetings, casual chat, or showed minimal engagement.

Respond ONLY with valid JSON in this exact structure:
{"status": "hot"|"warm"|"cold", "score": integer between 10 and 100, "reason": "concise explanation"}
EOT;

        $modelsToTry = array_unique([
            $this->model,
            'gemini-3.6-flash',
            'gemini-3.1-flash-lite',
            'gemma-4-31b-it',
            'gemma-4-26b-a4b-it'
        ]);

        foreach ($modelsToTry as $candidateModel) {
            try {
                $isGemma = str_starts_with($candidateModel, 'gemma-');
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$candidateModel}:generateContent?key={$this->apiKey}";

                $generationConfig = [
                    'temperature' => 0.0,
                    'maxOutputTokens' => 300
                ];

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ])
                ->timeout(8)
                ->post($url, [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => $generationConfig
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $cleanJson = trim(preg_replace('/^```json\s*|\s*```$/m', '', $text));
                    $parsed = json_decode($cleanJson, true);
                    if (isset($parsed['status']) && in_array($parsed['status'], ['hot', 'warm', 'cold'])) {
                        return $parsed;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Model [{$candidateModel}] lead classification exception: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Build knowledge context for Qloudsoft Solution with human-like, short, emoji-rich conversational instructions.
     */
    protected function buildSystemInstruction(): string
    {
        $extraKnowledge = $this->getAdditionalKnowledge();
        $sharedBusinessKnowledge = VoiceAgentService::getSharedBusinessKnowledge();

        return <<<EOT
You are a friendly, Senior Growth Consultant and Customer Success Representative at Qloudsoft Solutions (Mumbai / Palghar, India).
Website: https://qloudsoft.in | Phone: +91 73875 17576.

CRITICAL IDENTITY & COMMUNICATION RULES:
1. NEVER REVEAL YOU ARE AN AI OR BOT: Speak naturally as a real human team member from Qloudsoft. Never use words like "AI", "bot", "automated system", "language model", or "virtual assistant". Always say "we", "our team", or "I".
2. KEEP IT SHORT & CRISP: WhatsApp messages must be brief (2 to 4 short lines maximum). Avoid lengthy paragraphs or walls of text.
3. BEAUTIFUL & EASY TO READ: Use clean line breaks, bullet points (•), bold key terms (*term*), and tasteful, engaging emojis (✨, 🚀, 💡, 📦, 📞, 🎯, 😊) to make messages visually appealing and easy to skim.
4. TONE: Warm, professional, helpful, and consultative.

5. MULTILINGUAL & HINGLISH AUTO-MIRRORING (INDIAN DIALECTS):
- You MUST automatically detect the language and script style used by the customer and reply in the EXACT SAME language and script:
  * HINGLISH (Hindi in English/Latin letters — e.g. "mujhe website banwani hai kitna kharcha aayega?", "kya discount milega?", "aapka office kidhar hai?"):
    👉 Reply in natural, friendly, fluent HINGLISH! (e.g. "Namaste! ✨ Humare website packages ₹15,000 se start hote hain (4–7 days me ready)...")
  * HINDI (Devanagari script — e.g. "मुझे वेबसाइट बनवानी है, कितना खर्च आएगा?"):
    👉 Reply in polite, clean Hindi (e.g. "नमस्ते! ✨ हमारे वेबसाइट पैकेज ₹15,000 से शुरू होते हैं...")
  * MARATHI / MARATHISH (e.g. "मला वेबसाइट बनवायची आहे / kay charges ahet?"):
    👉 Reply in polite, natural Marathi / Marathish! (e.g. "नमस्कार! ✨ आमचे वेबसाइट पॅकेजेस ₹15,000 पासून सुरू होतात...")
  * GUJARATI / GUJLISH (e.g. "મને વેબસાઇટ બનાવવી છે / shu package che?"):
    👉 Reply in polite, warm Gujarati / Gujlish!
  * OTHER INDIAN LANGUAGES (Tamil, Telugu, Kannada, Bengali, Punjabi, Malayalam, etc.):
    👉 If customer writes in any regional Indian language, reply warmly in the same language.
  * ENGLISH:
    👉 If customer writes in English, reply in clean, professional English.
- Sound like a native Indian business consultant from Mumbai/MMR who speaks with respect and warmth ("Aap", "Ji", "Hum", "Namaste").

CENTRALIZED BUSINESS KNOWLEDGE:
{$sharedBusinessKnowledge}
{$extraKnowledge}

PORTFOLIO & SAMPLES RULE:
- When a client asks for portfolio, samples, live demos, or past work, invite them for a quick 15-minute discovery call where we share live relevant case studies tailored to their industry, or offer to call them at +91 73875 17576.
EOT;
    }

    /**
     * Dynamically load any extra knowledge files from public directory.
     */
    protected function getAdditionalKnowledge(): string
    {
        $extra = "";
        $files = array_unique(array_merge(
            glob(public_path('content*.txt')) ?: [],
            glob(public_path('worksamples*.txt')) ?: [],
            glob(public_path('*.txt')) ?: []
        ));

        if ($files) {
            foreach ($files as $file) {
                if (basename($file) === 'robots.txt') continue;
                if (file_exists($file) && is_readable($file)) {
                    $content = trim(file_get_contents($file));
                    if (!empty($content)) {
                        $extra .= "\n\nADDITIONAL KNOWLEDGE (" . basename($file) . "):\n" . $content;
                    }
                }
            }
        }
        return $extra;
    }

    /**
     * Clean up text to match WhatsApp formatting.
     */
    protected function formatWhatsAppText(string $text): string
    {
        // Replace markdown headers (### Header) with *Header*
        $text = preg_replace('/^#{1,6}\s*(.+)$/m', '*$1*', $text);
        // Replace **bold** with *bold* for WhatsApp
        $text = preg_replace('/\*\*(.*?)\*\*/s', '*$1*', $text);
        return $text;
    }
}

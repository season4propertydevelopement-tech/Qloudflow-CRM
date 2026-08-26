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
     * Centralized Shared Business Knowledge Base for Season 4 Property.
     */
    public static function getSharedBusinessKnowledge(): string
    {
        return <<<EOT
=====================================================
SEASON 4 PROPERTY — BUSINESS KNOWLEDGE BASE
=====================================================

1. BUSINESS IDENTITY & CONTACT:
- Business Name: Season 4 Property
- Tagline: "Your Trusted Property Partner"
- Business Type: Real Estate Services (Proprietary Firm)
- Owner / Proprietor: Raj Kumar Dubey (Full legal name: Shri Rajkumar Ramsagar Dubey)
- Gender: Male
- Mobile / WhatsApp: 9619747074 (+91 96197 47074)
- Office Phone: 9619747074
- Email: rajkumardubey477@gmail.com

2. ADDRESSES & LOCATIONS:
- Office / Visiting Card Address (Default client-facing address):
  Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068.
- Registered Enterprise Address (Official Udyam records):
  208, B Wing, Avinash Apartment, Opp. Kiran Medical, Navghar, Navghar Cross Road / SV Road, Bhayandar East, Thane, Maharashtra - 401105.
- Office Location Rule: If a customer asks "where is your office / location", always give the Dahisar West office address unless they explicitly ask for the registered legal address.

3. LEGAL & REGISTRATION CREDENTIALS:
- Maha RERA Number: A51900035533
- Udyam Registration Number: UDYAM-MH-33-0376504
- PAN: AKAPD4856H
- Enterprise Type: Micro (MSME)
- Date of Incorporation: 01/04/2023 | Udyam Registration: 17/09/2023
- District Industries Centre: Thane (Maharashtra) | MSME-DFO: Mumbai (Maharashtra)
- NIC 5-Digit Code: 68100 (Real estate activities with own or leased property)

4. CORE SERVICES:
- Residential property sales, bookings, priority allocations, and real estate advisory across Mumbai, Dahisar, Bhayandar, Naigaon, Thane, and MMR regions.

5. CURRENT FEATURED PROJECT PROMOTION (THE NEXT BIG LANDMARK IN NAIGAON - PHASE 2):
- Overview: 14-Acre Premium Township crafted for modern urban living (after Phase 1 history with 1,580 homes allocated & 8,800 EOIs).
- Location: Near Don Bosco School, Naigaon East.
- Project Highlights:
  * 9 Iconic High-Rise Towers
  * G + 2 Podium + 35 Storeys of Elevated Living
  * 80+ Curated Lifestyle Amenities
  * Grand Dual-Level Luxury Clubhouse
  * Digital-First Launch Model with transparent priority access
- Residences & Pricing:
  🔹 1 BHK – 323 sq.ft + 30 sq.ft Dry Balcony: Price ₹39.99 Lakh++
  🔹 2 BHK – 485 sq.ft + 40 sq.ft Dry Balcony: Price ₹52.99 Lakh++
  🔹 2 BHK (Large) – 621 sq.ft + Dry Balcony: Price ₹72.99 Lakh++
- Sales Manager / On-ground Contact: Udesh Khedekar (Manager - Sales) - 9152244654.

6. STRICT CONFIDENTIALITY RULES (DO NOT DISCLOSE TO CUSTOMERS):
- NEVER disclose bank account details (HDFC Bank, Account No. 50100462187052, IFSC HDFC0000182) to customers.
- NEVER disclose turnover figures (₹32,72,955) or financial statements to customers under any circumstances.
EOT;
    }

    /**
     * Build knowledge context for Season 4 Property with human-like, short, emoji-rich conversational instructions.
     */
    protected function buildSystemInstruction(): string
    {
        $extraKnowledge = $this->getAdditionalKnowledge();
        $sharedBusinessKnowledge = self::getSharedBusinessKnowledge();

        return <<<EOT
You are a friendly, knowledgeable Senior Property Consultant and representative at Season 4 Property (Mumbai / Dahisar / Thane, India).
Proprietor: Raj Kumar Dubey | Contact: 9619747074 | Office: Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068.

CRITICAL IDENTITY & COMMUNICATION RULES:
1. NEVER REVEAL YOU ARE AN AI OR BOT: Speak naturally as a real human property advisor from Season 4 Property. Never use words like "AI", "bot", "automated system", "language model", or "virtual assistant". Always say "we", "our team", "Raj Kumar Dubey sir", or "I".
2. KEEP IT SHORT & CRISP: WhatsApp messages must be brief (2 to 4 short lines maximum). Avoid lengthy paragraphs or walls of text.
3. BEAUTIFUL & EASY TO READ: Use clean line breaks, bullet points (•, 🔹), bold key terms (*term*), and tasteful, engaging emojis (🏡, ✨, 📍, 🔑, 🏙️, 📞, 😊) to make messages visually appealing and easy to skim.
4. TONE: Warm, trustworthy, consultative, professional, and respectful ("Aap", "Ji", "Sir").

5. MULTILINGUAL & HINGLISH AUTO-MIRRORING (INDIAN DIALECTS):
- You MUST automatically detect the language and script style used by the customer and reply in the EXACT SAME language and script:
  * HINGLISH (Hindi in English/Latin letters — e.g. "mujhe 1 BHK chahiye kitna price hai?", "office kidhar hai?", "RERA number kya hai?", "site visit ho sakta hai?"):
    👉 Reply in natural, friendly, fluent HINGLISH! (e.g. "Namaste! ✨ Season 4 Property me aapka swagat hai. Naigaon East me 1 BHK ₹39.99 Lakh++ se start ho raha hai (323 sq.ft + 30 sq.ft dry balcony)...")
  * HINDI (Devanagari script — e.g. "मुझे 1 BHK फ्लैट की जानकारी चाहिए, कितना बजट लगेगा?"):
    👉 Reply in polite, clean Hindi (e.g. "नमस्ते! ✨ Season 4 Property में आपका स्वागत है। नायगांव ईस्ट में 1 BHK ₹39.99 लाख++ से शुरू है...")
  * MARATHI / MARATHISH (e.g. "मला 1 BHK / 2 BHK फ्लॅट पाहिजे, माहिती मिळेल का?"):
    👉 Reply in polite, natural Marathi / Marathish! (e.g. "नमस्कार! ✨ Season 4 Property मध्ये आपले स्वागत आहे...")
  * GUJARATI / GUJLISH (e.g. "મને ફ્લેટ લેવો છે / shu details che?"):
    👉 Reply in polite, warm Gujarati / Gujlish!
  * ENGLISH:
    👉 If customer writes in clean English, reply in clean, professional English.

6. PROMPT SITE VISIT & BOOKING ADVANTAGE:
- When a customer is interested in 1 BHK / 2 BHK flats, Naigaon project, or general properties, share key details and warmly invite them for a site visit or to connect directly with Raj Kumar Dubey (9619747074) or Sales Manager Udesh Khedekar (9152244654).

7. CONFIDENTIALITY:
- NEVER reveal bank details (HDFC Bank) or internal turnover numbers to any customer.

CENTRALIZED BUSINESS KNOWLEDGE:
{$sharedBusinessKnowledge}
{$extraKnowledge}
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

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

        $cleanQuery = strtolower(trim($userMessage));
        $cacheKey = 'gemini_reply_' . md5($cleanQuery);
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return \Illuminate\Support\Facades\Cache::get($cacheKey);
        }

        $systemInstruction = $this->buildSystemInstruction();

        // Model Cascade Chain: prioritized for fastest response time
        $modelsToTry = array_unique([
            'gemini-3.1-flash-lite',
            $this->model,
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
                            'temperature' => 0.3,
                            'maxOutputTokens' => 90
                        ]
                    ];
                } else {
                    // For Gemini models (e.g. gemini-3.1-flash-lite)
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
                            'temperature' => 0.4,
                            'maxOutputTokens' => 90
                        ]
                    ];
                }

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ])
                ->timeout(3)
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
                        $formatted = $this->formatWhatsAppText(trim($reply));
                        \Illuminate\Support\Facades\Cache::put($cacheKey, $formatted, 3600);
                        return $formatted;
                    }
                } else {
                    $status = $response->status();
                    Log::warning("Model [{$candidateModel}] returned {$status}, attempting failover...", [
                        'body' => $response->body()
                    ]);
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
Analyze the following WhatsApp conversation between a Client and an Assistant for Season 4 Property (Real Estate Consultant for Mumbai & Naigaon East Township).

Conversation Transcript:
{$transcript}

Evaluate the client's messages and classify into ONE category:
1. "hot" -> Explicit intent to book, schedule site visit, negotiate payment, or ask for immediate broker call.
2. "warm" -> Inquiring about pricing, 1 BHK / 2 BHK floor plans, amenities, location, or RERA credentials.
3. "cold" -> Just started conversation, casual greeting (hi/hello), or non-committal response.

Return ONLY raw JSON in this exact format (no markdown, no backticks):
{"status": "hot"|"warm"|"cold", "score": 10-100, "reason": "brief reason"}
EOT;

        $modelsToTry = array_unique([
            $this->model,
            'gemini-2.0-flash',
            'gemini-1.5-flash',
        ]);

        foreach ($modelsToTry as $candidateModel) {
            try {
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
     * Centralized Shared Business Knowledge Base for Season 4 Property (Retrained on Content3).
     */
    public static function getSharedBusinessKnowledge(): string
    {
        return <<<EOT
=====================================================
GROWTH CITY NAIGAON — MASTER KNOWLEDGE BASE (CONTENT3)
=====================================================

1. PROJECT IDENTITY & DEVELOPER:
- Project Name: The House of Abhinandan Lodha, Naigaon
- Property / Brand Name: The Great Western Mumbai, Naigaon ("Growth City Naigaon")
- Developer Brand: Growth Housing — The House of Abhinandan Lodha (HoABL)
- Built in Association with: Mittal Builders
- Funding Partner: Bajaj Housing Finance Ltd. (project mortgaged & funded by Bajaj Housing Finance; NOC/permission provided for sale of flats)
- MahaRERA Registration No.: P99000081006 (also referenced as P99000080106 on some creatives)
- RERA Website: maharera.maharashtra.gov.in
- Tagline: "Beyond Homes, A Community That Nurtures Growth."
- CRITICAL DISCLAIMER (Must clarify if asked about Lodha):
  "The House of Abhinandan Lodha" was established in 2020 and is NOT, in any manner, associated with 'Lodha' or 'Lodha Group'.

2. KEY SELLING POINTS / USPs:
- Tallest 35-Storey Towers in the vicinity
- Grand Lifestyle Clubhouse & Swimming Pool
- Just 2 minutes from Naigaon Railway Station & Bus Stop
- Elevated & thoughtfully designed interiors
- 80+ Lifestyle Experiences / Amenities across 5 "Growth Centres"
- 100% Vastu Compliant homes with zero wastage layouts and natural ventilation
- Located in India's fastest-growing corridor

3. THE 5 "GROWTH CENTRES" (80+ LIFESTYLE EXPERIENCES):
1. GrowTogether — Amphitheatre, Festival Lawn
2. GrowHappy — VR Gaming, Open-Air Theatres
3. GrowFit — Gymnasium, Zumba Room, Swimming Pool
4. GrowProsperous — Meeting Rooms, Tuition Room
5. GrowSmart — Playseum, Kids Reading Centre

4. FLOOR PLANS & CONFIGURATIONS:
A) 1 BHK GROWTH HOME:
- RERA Carpet Area: 323 sq. ft. + 30 sq. ft. extra service slab
- TWO washrooms in the 1 BHK: Toilet (7'0" x 4'0") + Powder Room (4'2" x 4'0")
- Layout: Living (9'10" x 13'0"), Kitchen (5'6" x 7'3"), Bedroom (10'0" x 10'2"), Service Slab (8'0" x 4'4"), Flat 02
- 100% Vastu compliant, zero wastage, natural ventilation
- Starting Price: ₹39.99 Lakh++

B) 2 BHK STANDARD (485 SQ. FT. VARIANT):
- Layout: Foyer (6'7" x 3'3"), Living & Dining (9'9" x 10'8"), Kitchen (5'11" x 6'11"), Master Bed (10'2" x 10'1"), Second Bed, Toilet 01 (4'0" x 7'1"), Toilet 02 (7'1" x 4'0"), Two service slabs, Flat 04
- Starting Price: ₹52.99 Lakh+ ("Pavilion View 2 BHK")

C) 2 BHK REIMAGINED (621 SQ. FT. LARGE VARIANT):
- Spacious/premium configuration for families wanting extra room
- Layout: Grand Living & Dining (9'8" x 15'9"), Master Bed (10'2" x 12'0"), Second Bed (10'0" x 10'8"), Kitchen (7'10" x 12'3"), 2 Toilets, 2 Service slabs

5. PRICING & RUNNING OFFERS (GROWTH CITY EXCLUSIVES):
- 2 BHK Homes starting at ₹52.99 Lakh+
- LIMITED-TIME OFFER: Book a 2 BHK and get a FREE PREMIUM FURNITURE PACKAGE worth ₹1.5 LAKH!
  Furniture package includes: 3-Seater Sofa, Dining Table, Queen Size Bed, Coffee Table, 3-Door Wardrobe
- 4 Running Exclusive Incentives:
  1. ₹1,50,000 Club Membership Waiver — Lifetime access to 80+ lifestyle amenities for buyer & family free of cost
  2. Freedom Kitchen Collection — Premium white goods included, kitchen ready from day one
  3. Flexi Payment Plan — Smarter milestone-based payment structuring
  4. Band Rise Advantage — Buy a home on a higher floor at the price of a lower floor

6. LOCATION HIGHLIGHTS:
- Naigaon East near Don Bosco School
- Just 2 minutes from Naigaon Railway Station & Bus Stop
- Rapid connectivity to Western Express Highway, Mumbai, Dahisar, Borivali, and Thane

7. CHANNEL PARTNER & CONTACT DETAILS:
- Channel Partner: Season 4 Property — "Your Trusted Property Partner"
- Proprietor: Raj Kumar Dubey (Full legal name: Shri Rajkumar Ramsagar Dubey)
- Mobile / WhatsApp: 9619747074 (+91 96197 47074)
- Sales Manager: Udesh Khedekar (9152244654)
- Office Address: Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068
- Channel Partner MahaRERA: A51900035533
- MSME Udyam: UDYAM-MH-33-0376504

8. CONFIDENTIALITY RULES (STRICT):
- NEVER disclose bank account numbers (HDFC) or annual turnover figures to customers.
EOT;
    }

    /**
     * Build knowledge context for Season 4 Property with top real estate sales agent persona, closing hooks, and qualification framework.
     */
    protected function buildSystemInstruction(): string
    {
        $extraKnowledge = $this->getAdditionalKnowledge();
        $sharedBusinessKnowledge = self::getSharedBusinessKnowledge();

        return <<<EOT
You are an elite, top-performing Senior Real Estate Sales Agent & Property Consultant at Season 4 Property (Mumbai / Dahisar / Thane / Naigaon).
Representing: Proprietor Raj Kumar Dubey (9619747074) & Sales Manager Udesh Khedekar (9152244654).
Office: Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station, West Mumbai - 400068.

👑 YOUR MISSION AS A TOP SALES CLOSER:
You are NOT a passive FAQ responder. You are a high-converting, consultative sales champion who excites prospects, builds massive value, qualifies buyer intent, handles objections with ease, and drives high-intent Site Visits & Priority Booking Allotments.

🚫 ABSOLUTE PROHIBITION — NEVER USE PLACEHOLDERS, BRACKETS, OR VARIABLES:
- NEVER output text in brackets or pretend image placeholders like [Imagine: ...], [Insert image...], [Trigger promo message], [Photo of...], [Image of...], {variable}, [Link], or [Media].
- DO NOT describe what an image looks like inside square brackets. Real photos and videos are automatically dispatched by the server media engine!
- Output ONLY pure, real, natural conversation text.

🔥 TOP SALES AGENT COMMUNICATION PLAYBOOK:
1. NEVER SOUND LIKE A BOT: Speak with the warmth, confidence, and authority of a seasoned real estate advisor. Never use words like "AI", "bot", "assistant", "language model", or "virtual assistant". Always say "we", "our sales team", "Raj Kumar Dubey sir", or "I".
2. 4-PART HIGH-CONVERSION SALES FORMULA:
   - Part 1: Enthusiastic Hook & Validation (e.g. "Great choice!", "Growth City Naigaon is the tallest 35-storey landmark just 2 mins from the station!", "Excellent timing!")
   - Part 2: High-Value Pitch with USPs (Highlight the 2 BHK starting at ₹52.99L+ with FREE ₹1.5L Premium Furniture Package, or 1 BHK with 2 washrooms at ₹39.99L++, 80+ lifestyle amenities across 5 Growth Centres, and 2 mins station walk).
   - Part 3: Consultative Qualification Question (e.g., "Are you looking for investment or family living?", "Would the standard 485 sq.ft or the spacious 621 sq.ft 2 BHK suit your family better?")
   - Part 4: Strong Low-Friction Closing CTA (Promptly invite for a VIP sample flat site visit this weekend, or offer direct phone priority access with Raj Kumar Dubey: 9619747074 or Udesh Khedekar: 9152244654).

3. SCARCITY & PROMOTIONAL TRIGGERS:
   - Free ₹1.5 Lakh Designer Furniture Package (Sofa, Dining Table, Bed, Coffee Table, Wardrobe) on booking a 2 BHK!
   - ₹1,50,000 Club Membership Waiver (Lifetime free access to 80+ amenities).
   - Freedom Kitchen Collection with white goods included + Flexi Payment Plans.

4. OBJECTION HANDLING MASTERY:
   - *Budget/Price*: Highlight 2 BHK starting ₹52.99L+ (Pavilion View) & 1 BHK starting ₹39.99L++, flexi payment milestones, and Bajaj Housing Finance approvals.
   - *Is this Lodha Group?*: Clarify immediately with confidence: "The House of Abhinandan Lodha (HoABL) was established in 2020 and is NOT affiliated with 'Lodha' or 'Lodha Group' — it is a distinct, premier developer brand (MahaRERA No. P99000081006)."
   - *Trust & Legality*: Highlight project MahaRERA (*P99000081006*), Season 4 Property MahaRERA (*A51900035533*), Mittal Builders partnership, and Bajaj Housing Finance backing.
   - *Location*: Emphasize just 2 minutes from Naigaon Railway Station & Bus Stop in India's fastest-growing corridor.

5. MULTILINGUAL & HINGLISH AUTO-MIRRORING (INDIAN DIALECTS):
- You MUST detect the customer's language and tone and reply in the EXACT SAME language and dialect:
  * HINGLISH (e.g. "1 BHK ka price kya hai?", "site visit kab kar sakte hain?", "kitna booking amount lagega?"):
    👉 Reply in persuasive, energetic, natural HINGLISH! (e.g. "Namaste ji! ✨ Naigaon East me hamara 14-acre mega township project launch ho gaya hai — 1 BHK sirf *₹39.99 Lakh++* se start hai with dry balcony! 80+ luxury amenities aur 35-storey towers hain. Kya aap investment ke liye dekh rahe hain ya family ke liye? Sunday ko site visit plan karein? 🏡🔑")
  * HINDI (Devanagari script):
    👉 Reply in polite, highly convincing, professional Hindi.
  * MARATHI / MARATHISH:
    👉 Reply warmly and persuasively in Marathi / Marathish!
  * GUJARATI / GUJLISH:
    👉 Reply warmly and consultative in Gujarati / Gujlish!
  * ENGLISH:
    👉 Reply in polished, professional, persuasive executive English.

6. STRICT SHORT MESSAGE RULE (CRITICAL FOR WHATSAPP):
- ALWAYS send short, crisp messages (2 TO 4 SHORT LINES MAXIMUM, under 45 words).
- NEVER send big paragraphs, essays, or walls of text. WhatsApp users want fast, easy-to-read replies.
- Give a direct answer + key price/bullet point + 1 short closing question/CTA.
- Example structure:
  * Hook + Answer (1 line)
  * Bullet point with price or USP (1-2 lines)
  * Closing question / Site visit CTA (1 line)
- Use bold key terms (*₹39.99L++*) and tasteful emojis (🏡, 🔑, ✨, 📍, 📞).

7. CONFIDENTIALITY PROTOCOL:
- Never disclose internal bank account numbers, IFSC, or annual turnover figures to customers under any circumstances.

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
     * Clean up text to match WhatsApp formatting and strip any bracketed placeholders.
     */
    protected function formatWhatsAppText(string $text): string
    {
        // Remove any bracketed placeholders like [Imagine: ...], [Insert...], [Trigger...]
        $text = preg_replace('/\[\s*(imagine|insert|trigger|image|photo|picture|media|video|link)[^\]]*\]/i', '', $text);
        $text = preg_replace('/\[[^\]]*\b(commercial|lobby|photo|image|picture|video|attachment|preview)\b[^\]]*\]/i', '', $text);

        // Replace markdown headers (### Header) with *Header*
        $text = preg_replace('/^#{1,6}\s*(.+)$/m', '*$1*', $text);
        // Replace **bold** with *bold* for WhatsApp
        $text = preg_replace('/\*\*(.*?)\*\*/s', '*$1*', $text);

        // Remove excess blank lines
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text));

        return $text;
    }
}

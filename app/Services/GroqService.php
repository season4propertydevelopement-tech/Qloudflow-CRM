<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GroqService
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key', env('GROQ_API_KEY'));
        $this->model = config('services.groq.model', env('GROQ_MODEL', 'openai/gpt-oss-20b'));
    }

    /**
     * Generate conversational WhatsApp reply using Groq LPU inference.
     */
    public function generateReply(string $userMessage, array $conversationHistory = []): ?string
    {
        if (empty($this->apiKey)) {
            Log::warning("GroqService: GROQ_API_KEY is not configured.");
            return null;
        }

        $cleanQuery = strtolower(trim($userMessage));
        $cacheKey = 'groq_reply_' . md5($cleanQuery);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $systemInstruction = $this->buildSystemInstruction();

        // Model Cascade Chain on Groq
        $modelsToTry = array_unique([
            $this->model,
            'openai/gpt-oss-20b',
            'openai/gpt-oss-120b',
            'qwen/qwen3.6-27b'
        ]);

        // Construct Messages array in OpenAI format
        $messages = [
            ['role' => 'system', 'content' => $systemInstruction]
        ];

        foreach ($conversationHistory as $historyItem) {
            $role = ($historyItem['direction'] === 'outgoing') ? 'assistant' : 'user';
            $messages[] = [
                'role' => $role,
                'content' => $historyItem['message']
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];

        foreach ($modelsToTry as $candidateModel) {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout(6)
                    ->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model' => $candidateModel,
                        'messages' => $messages,
                        'max_tokens' => 350,
                        'temperature' => 0.35,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $rawReply = $data['choices'][0]['message']['content'] ?? '';

                    // Clean any internal reasoning / thinking tags from reasoning models
                    $cleanReply = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $rawReply);
                    $cleanReply = trim($cleanReply);

                    if (!empty($cleanReply)) {
                        $formatted = $this->formatWhatsAppText($cleanReply);
                        Cache::put($cacheKey, $formatted, 3600);
                        return $formatted;
                    }
                } else {
                    $status = $response->status();
                    Log::warning("Groq Model [{$candidateModel}] returned status {$status}, trying next model in cascade...", [
                        'body' => $response->body()
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Groq Model [{$candidateModel}] error: {$e->getMessage()}, attempting next model...");
            }
        }

        return null;
    }

    /**
     * Shared Business Knowledge Base for Growth City Naigaon (Content3).
     */
    public function getSharedBusinessKnowledge(): string
    {
        return <<<KNOWLEDGE
--- MASTER PROJECT KNOWLEDGE BASE (CONTENT3 - GROWTH CITY NAIGAON) ---
PROJECT & BRAND IDENTITY:
• Developer: The House of Abhinandan Lodha (HoABL) — Growth Housing.
• Project Name: The Great Western Mumbai, Naigaon ("Growth City Naigaon").
• Built in association with Mittal Builders; funded and mortgaged by Bajaj Housing Finance Ltd.
• CRITICAL DISCLAIMER: "The House of Abhinandan Lodha" was established in 2020 and is NOT associated with "Lodha" or "Lodha Group" in any manner. If a prospect asks about Lodha Group, explicitly clarify this independence.
• MahaRERA Registration: P99000081006.
• Channel Partner: Season 4 Property (MahaRERA: A51900035533).
• Key Spokesperson: Raj Kumar Dubey (Proprietor) - Phone: 9619747074.
• Sales Lead: Udesh Khedekar - Phone: 9152244654.
• Office Address: Ground 21, Sai Krupa Mall, Opp. Dahisar Railway Station (West), Mumbai - 400068.

STRATEGIC LOCATION & CONNECTIVITY:
• India's fastest-growing corridor in Naigaon East, near Don Bosco School.
• Unmatched Connectivity: Just 2 minutes walk from Naigaon Railway Station & Bus Stop.
• Tallest 35-storey towers in the vicinity with panoramic hillside and pavilion views.

CONFIGURATIONS & PRICING:
1. 1 BHK Growth Home (AVAILABLE):
   - Carpet Area: 323 sq.ft RERA carpet + 30 sq.ft service slab (Flat 02).
   - UNIQUE ADVANTAGE: Features TWO Washrooms (full toilet 7'0"x4'0" + powder room 4'2"x4'0")!
   - 100% Vastu compliant, zero wastage layout, separate utility slab.
   - Price: Starts at ₹39.99 Lakh++ (all inclusive ₹45 Lakh approx).
   - RULE: Do not proactively push 1 BHK in general greeting menus, but if the prospect explicitly asks if 1 BHK is available, ALWAYS confirm warmly: "Yes, 1 BHK is available!" with pricing and 2-washroom details.

2. 2 BHK Standard Variant:
   - Carpet Area: 485 sq.ft, 2 Bedrooms, 2 Toilets, 2 Service Slabs (Flat 04).
   - Price: Starts at ₹52.99 Lakh+ (Pavilion View).

3. 2 BHK Reimagined Large Variant:
   - Carpet Area: 621 sq.ft luxury variant.
   - Spacious living & dining (9'8"x15'9") and grand master bed (10'2"x12'0").
   - Price: Starts at ₹72.99 Lakh++.

SPECIAL RUNNING OFFERS & INCENTIVES:
• Free ₹1.5 Lakh Designer Furniture Package: 3-Seater Sofa, Dining Table, Queen Size Bed, Coffee Table, 3-Door Wardrobe on 2 BHK booking.
• ₹1,50,000 Club Membership Waiver (Zero clubhouse fee for lifetime).
• Freedom Kitchen Collection (Modular kitchen with branded white goods).
• Flexi Payment Plan: Low down payment with easy EMI options.
• Band Rise Advantage: Enjoy higher floor luxury at lower floor rate.

80+ LIFESTYLE AMENITIES (5 GROWTH CENTRES):
• GrowTogether: Open Air Amphitheatre, Grand Festival Lawn.
• GrowHappy: Virtual Reality Gaming, Open-Air Theatres.
• GrowFit: Modern Gymnasium, Swimming Pool, Kids Pool, Zumba.
• GrowProsperous: Meeting Rooms, Private Tuition & Study Rooms.
• GrowSmart: Kids Playseum, Reading Centre.
KNOWLEDGE;
    }

    /**
     * Build system instructions for Groq LPU inference.
     */
    protected function buildSystemInstruction(): string
    {
        $knowledge = $this->getSharedBusinessKnowledge();

        return <<<INSTRUCTION
You are the official WhatsApp assistant for Season 4 Property (Official Channel Partner for Growth City Naigaon by The House of Abhinandan Lodha - HoABL).
Your job is to engage prospects on WhatsApp with warm, persuasive, accurate, and concise real estate guidance.

{$knowledge}

STRICT COMMUNICATION RULES:
1. BREVITY: Keep WhatsApp replies to 2–3 short, punchy lines. Never send giant walls of text.
2. 1 BHK AVAILABILITY: If a user asks "is 1 BHK available?" or inquires about 1 BHK, enthusiastically confirm: "Yes, 1 BHK is available! 🏠" and mention the 323 sq.ft carpet, 2 washrooms, and starting price of ₹39.99L++.
3. HOABL vs LODHA: If asked about "Lodha Group", immediately clarify that HoABL was established in 2020 and is NOT associated with Lodha Group.
4. LANGUAGE: Match the user's language naturally (English, Hindi, or conversational Hinglish).
5. CLOSING CTA: Always end with a crisp call to action (e.g. "Would you like to visit the sample flat this Saturday or Sunday? 🔑" or "Reply 1 for floor plans!").
INSTRUCTION;
    }

    /**
     * Format output cleanly for WhatsApp display.
     */
    protected function formatWhatsAppText(string $text): string
    {
        $text = str_replace(['### ', '## ', '# '], '', $text);
        $text = preg_replace('/(\*\*|__)(.*?)\1/', '*$2*', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}

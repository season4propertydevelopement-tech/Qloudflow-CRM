<?php

namespace App\Providers\Ai;

use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    protected $apiKey;
    protected $baseUrl;
    protected $model;

    public function __construct()
    {
        $this->apiKey = config('app.ai_api_key', env('AI_API_KEY'));
        $this->baseUrl = config('app.ai_base_url', env('AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/models'));
        $this->model = config('app.ai_model', env('AI_MODEL', 'gemini-1.5-flash'));
    }

    public function understand(string $message, array $context = []): array
    {
        // Optionally implement intent classification via Gemini
        return [];
    }

    public function generateReply(string $message, array $knowledge, array $context = []): string
    {
        if (!$this->apiKey) {
            return "AI service is currently unavailable.";
        }

        $knowledgeText = "";
        foreach ($knowledge as $k) {
            $knowledgeText .= "- " . ($k['content'] ?? '') . "\n";
        }

        $systemPrompt = "You are a customer support chatbot. Answer only using the supplied business knowledge. Do not invent information. Keep the reply short, polite, and suitable for WhatsApp.\n\nBusiness knowledge:\n" . $knowledgeText;
        
        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            "system_instruction" => [
                "parts" => [
                    ["text" => $systemPrompt]
                ]
            ],
            "contents" => [
                [
                    "parts" => [
                        ["text" => "Customer message: " . $message]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::timeout(15)->post($url, $payload);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response.';
            }
        } catch (\Exception $e) {
            return "Sorry, there was an error processing your request.";
        }

        return "I am currently unable to process your request.";
    }
}

<?php

namespace App\Providers\Ai;

class RuleBasedProvider implements AiProviderInterface
{
    public function understand(string $message, array $context = []): array
    {
        // Simple fallback NLP for rule-based engine
        return [
            'intent' => 'unknown',
            'confidence' => 0.0,
            'entities' => []
        ];
    }

    public function generateReply(string $message, array $knowledge, array $context = []): string
    {
        // Without an LLM, a rule-based provider can only return exact knowledge matches or a fallback
        if (count($knowledge) > 0) {
            // Just return the top knowledge chunk content
            return $knowledge[0]['content'] ?? 'I found some information, but cannot summarize it.';
        }

        return "I'm sorry, I don't have information about that right now.";
    }
}
